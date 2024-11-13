<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Entrie_Material;
use App\Models\Entry;
use App\Models\Management;
use App\Models\Material;
use App\Models\Note_Entrie;
use App\Models\NoteRequest;
use App\Models\Request_Material;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NoteRequestController extends Controller
{
    public function list_note_request(Request $request)
    {
        $page = max(0, $request->get('page', 0));
        $limit = max(1, $request->get('limit', NoteRequest::count()));
        $start = $page * $limit;
        $state = $request->input('state', '');

        $lastManagement = Management::orderByDesc('id')->first();

        if (!$lastManagement) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontró ningún management.',
            ], 404);
        }

        $query = NoteRequest::with(['materials', 'employee'])
            ->where('type_id', 1)
            ->where('management_id', $lastManagement->id)
            ->orderBy('id', 'desc');

        if ($state) {
            $query->where('state', $state);
        }

        $totalNoteRequests = $query->count();
        $noteRequests = $query->skip($start)->take($limit)->get();

        if ($noteRequests->isEmpty()) {
            return response()->json(['message' => 'No note requests found'], 404);
        }

        $response = $noteRequests->map(function ($noteRequest) {
            return [
                'id_note' => $noteRequest->id,
                'number_note' => $noteRequest->number_note,
                'state' => $noteRequest->state,
                'request_date' => $noteRequest->request_date,
                'observation' => $noteRequest->observation,
                'observation_request' => $noteRequest->observation_request,
                'employee' => $noteRequest->employee
                    ? "{$noteRequest->employee->first_name} {$noteRequest->employee->last_name} {$noteRequest->employee->mothers_last_name}"
                    : null,
                'materials' => $noteRequest->materials->map(function ($material) {
                    return [
                        'id' => $material->id,
                        'code_material' => $material->code_material,
                        'description' => $material->description,
                        'unit_material' => $material->unit_material,
                        'stock' => $material->stock,
                        'amount_request' => $material->pivot->amount_request,
                        'delivered_quantity' => $material->pivot->delivered_quantity,
                        'name_material' => $material->pivot->name_material,
                    ];
                }),
            ];
        });

        return response()->json([
            'status' => 'success',
            'total' => $totalNoteRequests,
            'page' => $page,
            'last_page' => ceil($totalNoteRequests / $limit),
            'data' => $response,
        ], 200);
    }

    public function listUserNoteRequests($userId)
    {
        $noteRequests = NoteRequest::where('user_register', $userId)->with('materials')->orderBy('id', 'desc')->get();

        if ($noteRequests->isEmpty()) {
            return response()->json(['message' => 'No note requests found for this user'], 404);
        }
        $response = $noteRequests->map(function ($noteRequest) {
            return [
                'id' => $noteRequest->id,
                'number_note' => $noteRequest->number_note,
                'state' => $noteRequest->state,
                'request_date' => $noteRequest->request_date,
                'materials' => $noteRequest->materials->map(function ($material) {
                    return [
                        'code_material' => $material->code_material,
                        'description' => $material->description,
                        'amount_request' => $material->pivot->amount_request,
                        'delivered_quantity' => $material->pivot->delivered_quantity,
                        'name_material' => $material->pivot->name_material,
                    ];
                }),
            ];
        });

        return response()->json($response);
    }

    public function create_note_request(Request $request)
    {

        $number_note = 0;
        $period = Management::latest()->first();

        $noteRequest = NoteRequest::create([
            'number_note' => $number_note,
            'state' => 'En Revision',
            'observation' => 'Ninguno',
            'user_register' => $request['id'],
            'observation_request' => $request['comments'],
            'type_id' => 1,
            'request_date' => today()->toDateString(),
            'management_id' => $period->id,
        ]);
        foreach ($request['material_request'] as $materialData) {
            $noteRequest->materials()->attach($materialData['id'], [
                'amount_request' => $materialData['quantity'],
                'name_material' => $materialData['description'],
                'delivered_quantity' => 0,
            ]);
        }
        return response()->json($noteRequest->load('materials'), 201);
    }

    public function delivered_of_material(Request $request)
    {
        if ($request->status == "Approved") {
            $materials_validate = $request->input('materials');

            foreach ($materials_validate as $material) {
                $material_stock = Material::find($material['id_material']);

                if ($material_stock->stock < $material['amount_to_deliver']) {
                    return response()->json(['status' => false, 'message' => 'No hay suficiente stock en almacenes'], 404);
                }
            }
            $noteRequestId = $request->input('noteRequestId');
            $materialsToDeliver = $request->input('materials');

            foreach ($materialsToDeliver as $material) {
                $materialId = $material['id_material'];
                $amountToDeliver = (int) $material['amount_to_deliver'];
                $amount_to_be_reduced = $amountToDeliver;
                $entries = Note_Entrie::where('state', 'Aceptado')->whereHas('materials', function ($query) use ($materialId) {
                    $query->where('materials.id', $materialId);
                })
                    ->where('state', '!=', 'Eliminado')
                    ->orderBy('delivery_date', 'asc')
                    ->get();

                $costDetails = [];

                foreach ($entries as $entry) {
                    $entry->observation = 'Inactivo';
                    $entryMaterialPivot = $entry->materials()->where('materials.id', $materialId)->first()->pivot;
                    $availableAmount = $entryMaterialPivot->request;
                    $costUnit = $entryMaterialPivot->cost_unit;
                    if ($availableAmount >= $amountToDeliver) {
                        $entryMaterialPivot->request -= $amountToDeliver;
                        $costDetails[] = "$amountToDeliver @ $costUnit";
                        $entryMaterialPivot->save();
                        break;
                    } else {
                        $amountToDeliver -= $availableAmount;
                        $costDetails[] = "$availableAmount @ $costUnit";
                        $entryMaterialPivot->request = 0;
                        $entryMaterialPivot->save();
                    }
                }

                $costDetailsString = implode(', ', $costDetails);

                $requestMaterial = Request_Material::where('note_id', $noteRequestId)
                    ->where('material_id', $materialId)
                    ->first();
                $requestMaterial->delivered_quantity = $amount_to_be_reduced;
                $requestMaterial->costDetails = $costDetailsString;
                $requestMaterial->save();

                $material = Material::find($materialId);
                $material->stock -= $amount_to_be_reduced;
                $material->save();
            }
            $number_note = NoteRequest::where('state', 'Aceptado')->count() + 1;
            $noteRequest = NoteRequest::find($noteRequestId);
            $noteRequest->state = 'Aceptado';
            $noteRequest->received_on_date = today()->toDateString();
            $noteRequest->number_note = $number_note;
            $noteRequest->save();
            return response()->json(['status' => true, 'message' => 'Solicitud Aceptada'], 200);
        } else {

            $noteRequestId = $request->input('noteRequestId');
            $noteRequest = NoteRequest::find($noteRequestId);
            $noteRequest->observation = $request->input('cancelComment');
            $noteRequest->state = 'Cancelado';
            $noteRequest->save();
            return response()->json(['status' => true, 'message' => 'Solicitud Cancelada'], 200);
        }
    }

    public function print_request(NoteRequest $note_request)
    {

        $user = User::where('employee_id', $note_request->user_register)->first();
        if ($user) {
            // $position = $user->position;
            $cargo = DB::table('public.contracts as c')
                ->join('public.positions as p', 'c.position_id', '=', 'p.id')
                ->join('public.employees as e', 'c.employee_id', '=', 'e.id')
                ->join('public.position_groups as pg', 'p.position_group_id', '=', 'pg.id')
                ->select('c.employee_id', 'e.first_name', 'e.last_name', 'e.mothers_last_name', 'p.name as position_name', 'pg.name as group_name', 'pg.id as group_id')
                ->where('c.active', true)
                ->whereNull('c.deleted_at')
                ->whereIn('pg.id', [7, 8, 9, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21])
                ->where('c.employee_id', $note_request->user_register)
                ->unionAll(
                    DB::table('public.consultant_contracts as cc')
                        ->join('public.consultant_positions as cp', 'cc.consultant_position_id', '=', 'cp.id')
                        ->join('public.employees as e', 'cc.employee_id', '=', 'e.id')
                        ->join('public.position_groups as pg', 'cp.position_group_id', '=', 'pg.id')
                        ->select('cc.employee_id', 'e.first_name', 'e.last_name', 'e.mothers_last_name', 'cp.name as position_name', 'pg.name as group_name', 'pg.id as group_id')
                        ->where('cc.active', true)
                        ->whereNull('cc.deleted_at')
                        ->whereIn('pg.id', [7, 8, 9, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21])
                        ->where('cc.employee_id', $note_request->user_register)
                )
                ->get();
            $positionName = isset($cargo[0]) ? $cargo[0]->position_name : null;
            logger($positionName);
            $employee = Employee::find($note_request->user_register);
            $file_title = 'SOLICITUD DE MATERIAL DE ALMACÉN';
            $materials = $note_request->materials()->get()->map(function ($material) {
                return [
                    'description' => $material->description,
                    'unit_material' => $material->unit_material,
                    'cost_unit' => $material->average_cost,
                    'amount_request' => $material->pivot->amount_request,
                ];
            });

            $data = [
                'title' => 'SOLICITUD DE MATERIAL DE ALMACÉN',
                'number_note' => $note_request->number_note,
                'date' => Carbon::now()->format('Y'),
                'employee' => $employee
                    ? "{$employee->first_name} {$employee->last_name} {$employee->mothers_last_name}"
                    : null,
                'position' => $positionName,
                'materials' => $materials,
                'comment_request' => $note_request->observation_request,
                'comment' => $note_request->observation,
            ];
            $options = [
                'page-width' => '216',
                'page-height' => '279',
                'margin-top' => '4',
                'margin-bottom' => '4',
                'margin-left' => '5',
                'margin-right' => '5',
                'encoding' => 'UTF-8',
            ];

            $pdf = Pdf::loadView('Material_Request.MaterialRequest', $data);
            return $pdf->download('formulario_solicitud_de_material_de_almacén.pdf');
        } else {
            $employee = Employee::where('id', $note_request->user_register)->first();
            if ($employee) {
                $position = DB::selectOne('select cp."name" 
                           from public.consultant_contracts cc, public.consultant_positions cp 
                           where cc.employee_id = ? 
                           and cp.id = cc.consultant_position_id 
                           order by cc.consultant_position_id desc 
                           limit 1', [$note_request->user_register]);

                // Asigna solo el nombre de la posición o un valor nulo si no se encuentra
                $positionName = $position ? $position->name : null;
                $employee = Employee::find($note_request->user_register);
                $file_title = 'SOLICITUD DE MATERIAL DE ALMACÉN';
                $materials = $note_request->materials()->get()->map(function ($material) {
                    return [
                        'description' => $material->description,
                        'unit_material' => $material->unit_material,
                        'cost_unit' => $material->average_cost,
                        'amount_request' => $material->pivot->amount_request,
                    ];
                });

                $data = [
                    'title' => 'SOLICITUD DE MATERIAL DE ALMACÉN',
                    'number_note' => $note_request->number_note,
                    'date' => Carbon::now()->format('Y'),
                    'employee' => $employee
                        ? "{$employee->first_name} {$employee->last_name} {$employee->mothers_last_name}"
                        : null,
                    'position' => $positionName,
                    'materials' => $materials,
                    'comment_request' => $note_request->observation_request,
                    'comment' => $note_request->observation,
                ];
                $pdf = Pdf::loadView('Material_Request.MaterialRequest', $data);
                return $pdf->download('formulario_solicitud_de_material_de_almacén.pdf');
            } else {
                return "no funciona";
            }
        }
    }

    public function print_post_request(NoteRequest $note_request)
    {
        $user = User::where('employee_id', $note_request->user_register)->first();
        if ($user) {
            // $position = $user->position;
            $cargo = DB::table('public.contracts as c')
                ->join('public.positions as p', 'c.position_id', '=', 'p.id')
                ->join('public.employees as e', 'c.employee_id', '=', 'e.id')
                ->join('public.position_groups as pg', 'p.position_group_id', '=', 'pg.id')
                ->select('c.employee_id', 'e.first_name', 'e.last_name', 'e.mothers_last_name', 'p.name as position_name', 'pg.name as group_name', 'pg.id as group_id')
                ->where('c.active', true)
                ->whereNull('c.deleted_at')
                ->whereIn('pg.id', [7, 8, 9, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21])
                ->where('c.employee_id', $note_request->user_register)
                ->unionAll(
                    DB::table('public.consultant_contracts as cc')
                        ->join('public.consultant_positions as cp', 'cc.consultant_position_id', '=', 'cp.id')
                        ->join('public.employees as e', 'cc.employee_id', '=', 'e.id')
                        ->join('public.position_groups as pg', 'cp.position_group_id', '=', 'pg.id')
                        ->select('cc.employee_id', 'e.first_name', 'e.last_name', 'e.mothers_last_name', 'cp.name as position_name', 'pg.name as group_name', 'pg.id as group_id')
                        ->where('cc.active', true)
                        ->whereNull('cc.deleted_at')
                        ->whereIn('pg.id', [7, 8, 9, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21])
                        ->where('cc.employee_id', $note_request->user_register)
                )
                ->get();
            $positionName = isset($cargo[0]) ? $cargo[0]->position_name : null;
            $employee = Employee::find($note_request->user_register);
            $file_title = 'SOLICITUD DE MATERIAL DE ALMACÉN';
            $materials = $note_request->materials()->get()->map(function ($material) {
                return [
                    'description' => $material->description,
                    'unit_material' => $material->unit_material,
                    'amount_request' => $material->pivot->amount_request,
                    'cost_unit' => $material->average_cost,
                    'delivered_quantity' => $material->pivot->delivered_quantity,
                ];
            });

            $data = [
                'title' => 'ENTREGA DE MATERIAL DE ALMACÉN',
                'number_note' => $note_request->number_note,
                'date' => Carbon::now()->format('Y'),
                'employee' => $employee
                    ? "{$employee->first_name} {$employee->last_name} {$employee->mothers_last_name}"
                    : null,
                'position' => $positionName,
                'materials' => $materials,
                'comment_request' => $note_request->observation_request,
                'comment' => $note_request->observation,
            ];
            $options = [
                'page-width' => '216',
                'page-height' => '279',
                'margin-top' => '4',
                'margin-bottom' => '4',
                'margin-left' => '5',
                'margin-right' => '5',
                'encoding' => 'UTF-8',
            ];

            $pdf = Pdf::loadView('Material_Request.MaterialDelivery', $data);
            return $pdf->download('formulario_entrega_de_material_de_almacén.pdf');
        } else {
            $employee = Employee::where('id', $note_request->user_register)->first();
            if ($employee) {
                $position = DB::selectOne('select cp."name" 
                           from public.consultant_contracts cc, public.consultant_positions cp 
                           where cc.employee_id = ? 
                           and cp.id = cc.consultant_position_id 
                           order by cc.consultant_position_id desc 
                           limit 1', [$note_request->user_register]);

                // Asigna solo el nombre de la posición o un valor nulo si no se encuentra
                $positionName = $position ? $position->name : null;
                $employee = Employee::find($note_request->user_register);
                $file_title = 'SOLICITUD DE MATERIAL DE ALMACÉN';
                $materials = $note_request->materials()->get()->map(function ($material) {
                    return [
                        'description' => $material->description,
                        'unit_material' => $material->unit_material,
                        'amount_request' => $material->pivot->amount_request,
                        'cost_unit' => $material->average_cost,
                        'delivered_quantity' => $material->pivot->delivered_quantity,
                    ];
                });

                $data = [
                    'title' => 'ENTREGA DE MATERIAL DE ALMACÉN',
                    'number_note' => $note_request->number_note,
                    'date' => Carbon::now()->format('Y'),
                    'employee' => $employee
                        ? "{$employee->first_name} {$employee->last_name} {$employee->mothers_last_name}"
                        : null,
                    'position' => $positionName,
                    'materials' => $materials,
                    'comment_request' => $note_request->observation_request,
                    'comment' => $note_request->observation,
                ];
                $options = [
                    'page-width' => '216',
                    'page-height' => '279',
                    'margin-top' => '4',
                    'margin-bottom' => '4',
                    'margin-left' => '5',
                    'margin-right' => '5',
                    'encoding' => 'UTF-8',
                ];

                $pdf = Pdf::loadView('Material_Request.MaterialDelivery', $data);
                return $pdf->download('formulario_entrega_de_material_de_almacén.pdf');
            } else {
                return "no funciona";
            }
        }
    }

    public function list_note_request_petty_cash(Request $request)
    {
        $page = max(0, $request->get('page', 0));
        $limit = max(1, $request->get('limit', NoteRequest::count()));
        $start = $page * $limit;
        $state = $request->input('state', '');

        $lastManagement = Management::orderByDesc('id')->first();

        if (!$lastManagement) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontró ningún management.',
            ], 404);
        }

        $query = NoteRequest::with(['materials', 'employee'])
            ->where('type_id', 2)
            ->where('management_id', $lastManagement->id)
            ->orderBy('id', 'desc');

        if ($state) {
            $query->where('state', $state);
        }

        $totalNoteRequests = $query->count();
        $noteRequests = $query->skip($start)->take($limit)->get();

        if ($noteRequests->isEmpty()) {
            return response()->json(['message' => 'No note requests found'], 404);
        }

        $response = $noteRequests->map(function ($noteRequest) {
            return [
                'id_note' => $noteRequest->id,
                'number_note' => $noteRequest->number_note,
                'state' => $noteRequest->state,
                'request_date' => $noteRequest->request_date,
                'observation' => $noteRequest->observation,
                'employee' => $noteRequest->employee
                    ? "{$noteRequest->employee->first_name} {$noteRequest->employee->last_name} {$noteRequest->employee->mothers_last_name}"
                    : null,
                'materials' => $noteRequest->materials->map(function ($material) {
                    return [
                        'id' => $material->id,
                        'code_material' => $material->code_material,
                        'description' => $material->description,
                        'unit_material' => $material->unit_material,
                        'stock' => $material->stock,
                        'amount_request' => $material->pivot->amount_request,
                        'delivered_quantity' => $material->pivot->delivered_quantity,
                        'name_material' => $material->pivot->name_material,
                    ];
                }),
            ];
        });

        return response()->json([
            'status' => 'success',
            'total' => $totalNoteRequests,
            'page' => $page,
            'last_page' => ceil($totalNoteRequests / $limit),
            'data' => $response,
        ], 200);
    }
}
