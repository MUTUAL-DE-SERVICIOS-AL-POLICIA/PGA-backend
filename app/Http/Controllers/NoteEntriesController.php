<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Entrie_Material;
use App\Models\Management;
use App\Models\Material;
use App\Models\Note_Entrie;
use App\Models\NoteRequest;
use App\Models\Request_Material;
use App\Models\Supplier;
use App\Models\Type;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NoteEntriesController extends Controller
{

    public function list_note_entries(Request $request)
    {
        $page = max(0, $request->get('page', 0));
        $limit = max(1, $request->get('limit', Note_Entrie::count()));
        $search = $request->get('search', '');
        $startDate = $request->input('start_date', '');
        $endDate = $request->input('end_date', '');

        $lastManagement = Management::orderByDesc('id')->first();

        if (!$lastManagement) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontró ningún management.',
            ], 404);
        }

        $query = Note_Entrie::with([
            'materials' => function ($query) {
                $query->withPivot('amount_entries', 'cost_unit', 'cost_total')->withTrashed();
            },
            'suppliers' => function ($query) {
                $query->withPivot('invoice_number');
            }
        ])
            ->where('management_id', $lastManagement->id)
            ->where('state', 'Aceptado')
            ->orderByDesc('id');

        if ($startDate && $endDate) {
            $query->whereBetween('delivery_date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->whereDate('delivery_date', '>=', $startDate);
        } elseif ($endDate) {
            $query->whereDate('delivery_date', '<=', $endDate);
        }

        if ($search != null) {
            $query->where('number_note', $search);
        }

        $notes = $query->paginate($limit);

        return response()->json([
            'status' => 'success',
            'total' => $notes->total(),
            'page' => $notes->currentPage() - 1,
            'last_page' => $notes->lastPage(),
            'data' => $notes->items(),
        ], 200);
    }

    public function list_note_entries_revision(Request $request)
    {
        $page = max(0, $request->get('page', 0));
        $limit = max(1, $request->get('limit', Note_Entrie::count()));
        $start = $page * $limit;

        $startDate = $request->input('start_date', '');
        $endDate = $request->input('end_date', '');

        $lastManagement = Management::orderByDesc('id')->first();

        if (!$lastManagement) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontró ningún management.',
            ], 404);
        }

        $query = Note_Entrie::with([
            'materials' => function ($query) {
                $query->withPivot('amount_entries', 'cost_unit', 'cost_total')->withTrashed();
            },
            'suppliers' => function ($query) {
                $query->withPivot('invoice_number');
            }
        ])
            ->where('management_id', $lastManagement->id)
            ->where('state', 'En Revision')
            ->orderByDesc('id');

        if ($startDate && $endDate) {
            $query->whereBetween('delivery_date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->whereDate('delivery_date', '>=', $startDate);
        } elseif ($endDate) {
            $query->whereDate('delivery_date', '<=', $endDate);
        }
        $totalNotes = $query->count();
        $notes = $query->skip($start)->take($limit)->get();

        return response()->json([
            'status' => 'success',
            'total' => $totalNotes,
            'page' => $page,
            'last_page' => ceil($totalNotes / $limit),
            'data' => $notes,
        ], 200);
    }

    public function create_note(Request $request)
    {
        try {
            $validateData = $request->validate([
                'type' => 'required|integer',
                'suppliers' => 'required|array',
                'suppliers.*.supplierId' => 'required|exists:suppliers,id',
                'suppliers.*.invoiceNumber' => 'required|string',
                'materials' => 'required|array',
                'materials.*.id' => 'required|exists:materials,id',
                'materials.*.name' => 'required|string',
                'materials.*.quantity' => 'required|integer',
                'materials.*.price' => 'required|numeric',
                'materials.*.unit_material' => 'required|string',
                'date_entry' => 'required|date',
                'total' => 'required|numeric',
                'authorization_number' => 'required|string',
                'id_user' => 'required|string'
            ]);
            $period = Management::latest()->first();

            $number_note = $this->generateNoteNumber();

            $noteEntrie = Note_Entrie::create([
                'number_note' => $number_note,
                'delivery_date' => $validateData['date_entry'],
                'state' => 'En Revision',
                'invoice_auth' => $validateData['authorization_number'],
                'user_register' => $validateData['id_user'],
                'observation' => 'Activo',
                'type_id' => $validateData['type'],
                'management_id' => $period->id,
            ]);

            foreach ($validateData['suppliers'] as $supplierData) {
                $noteEntrie->suppliers()->attach($supplierData['supplierId'], [
                    'invoice_number' => $supplierData['invoiceNumber'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            foreach ($validateData['materials'] as $materialData) {

                $noteEntrie->materials()->attach($materialData['id'], [
                    'amount_entries' => $materialData['quantity'],
                    'request' => $materialData['quantity'],
                    'cost_unit' => $materialData['price'],
                    'cost_total' => $materialData['quantity'] * $materialData['price'],
                    'name_material' => $materialData['name'],
                    'delivery_date_entry' => $validateData['date_entry'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if ($noteEntrie->type_id == 2) {
                $number_note_request = 0;
                $noteRequest = NoteRequest::create([
                    'number_note' => $number_note_request,
                    'state' => 'En Revision',
                    'observation' => $number_note,
                    'user_register' => $validateData['id_user'],
                    'type_id' => 2,
                    'request_date' => today()->toDateString(),
                    'management_id' => $period->id,
                ]);

                foreach ($validateData['materials'] as $materialData) {
                    $noteRequest->materials()->attach($materialData['id'], [
                        'amount_request' => $materialData['quantity'],
                        'name_material' => $materialData['name'],
                        'delivered_quantity' => 0,
                    ]);
                }
            }
            return response()->json($noteEntrie, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json($e->errors(), 422);
        }
    }

    public function aprovedded_note(Request $request)
    {
        $note = Note_Entrie::with('materials')->find($request->noteEntryId);

        $note->state = "Aceptado";
        $note->save();

        $materialsData = [];
        foreach ($request->materials as $materialData) {
            $material = Material::find($materialData['id_material']);
            if ($material) {
                $material->state = 'Habilitado';
                $material->stock += $materialData['amount_entries'];
                $material->save();
                $averageCost = DB::table('entries_material')
                    ->where('material_id', $materialData['id_material'])
                    ->avg('cost_unit');

                $material->average_cost = $averageCost;
                $material->save();
            }
            $materialsData[$materialData['id_material']] = [
                'amount_entries' => $materialData['amount_entries'],
                'cost_unit' => $materialData['cost_unit'],
                'cost_total' => $materialData['amount_entries'] * $materialData['cost_unit'],
                'delivery_date_entry' => now(),
            ];
        }
        $note->materials()->sync($materialsData);

        return response()->json($note, 201);
    }


    public function destroy(Note_Entrie $note_entry)
    {
        DB::beginTransaction();

        try {
            $note_entry->state = "Eliminado";
            $note_entry->observation = "Eliminado";
            $note_entry->save();

            foreach ($note_entry->materials as $material) {
                $entryMaterial = Entrie_Material::where('note_id', $note_entry->id)
                    ->where('material_id', $material->id)
                    ->first();

                if ($entryMaterial) {
                    $entryMaterial->delete();
                }
            }

            $note_entry->suppliers()->detach();

            $note_entry->delete();

            if ($note_entry->type_id == 2) {
                $note = NoteRequest::where('observation', $note_entry->number_note)->first();
                if ($note) {
                    $note->state = 'Cancelado';
                    $note->save();
                }
            }

            DB::commit();
            return response()->json(['message' => 'Eliminado correctamente'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al eliminar la nota: ' . $e->getMessage()], 500);
        }
    }

    public function print_note_entry(Note_Entrie $note_entry)
    {
        $file_title = 'NOTA DE INGRESO ALMACÉN';
        $suppliers = $note_entry->suppliers()->get()->map(function ($supplier) {
            return [
                'invoice_number' => $supplier->pivot->invoice_number,
                'supplier_name' => $supplier->name,
            ];
        });
        $materials = $note_entry->materials()->get()->map(function ($material) {
            return [
                'code_material' => $material->code_material,
                'unit_material' => $material->unit_material,
                'description' => $material->description,
                'amount_entries' => $material->pivot->amount_entries,
                'cost_unit' => $material->pivot->cost_unit,
                'cost_total' => $material->pivot->cost_total,
            ];
        });
        $total_cost = $materials->sum('cost_total');
        $data = [
            'header' => [
                'direction' => 'DIRECCIÓN DE ASUNTOS ADMINISTRATIVOS',
                'unity' => 'UNIDAD ADMINISTRATIVA',
                'table' => [
                    ['Tipo', 'NOTA DE INGRESO'],
                    ['Nota', $note_entry->number_note],
                    ['Año', Carbon::now()->format('Y')],
                ]
            ],
            'title' => 'NOTA DE INGRESO ALMACÉN',
            'file_title' => $file_title,
            'supplier_name' => $note_entry->name_supplier,
            'number_note' => $note_entry->number_note,
            'invoice_number' => $note_entry->invoice_number,
            'delivery_date' => $note_entry->delivery_date,
            'materials' => $materials,
            'suppliers' => $suppliers,
            'total_cost' => number_format($total_cost, 2),
        ];

        $pdf = Pdf::loadView('Note_Entry.NoteEntries', $data);


        return $pdf->download('formulario_nota_entrada.pdf');
    }

    public function services_note()
    {
        $noteEntry = Note_Entrie::factory()->create();
        return $noteEntry;
    }


    private function generateNoteNumber()
    {
        $latestManagement = Management::latest('id')->first();
        $lastNote = Note_Entrie::where('management_id', $latestManagement->id)->orderBy('number_note', 'desc')->first();
        return $lastNote ? $lastNote->number_note + 1 : 1;
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

    public function controlNote()
    {
        $note1 = Request_Material::find(1587);
        $note2 = Request_Material::find(1590);
        if (!$note1 || !$note2) {
            return;
        }
        $tempCost = $note1->costDetails;
        $note1->costDetails = $note2->costDetails;
        $note2->costDetails = $tempCost;
        $note1->save();
        $note2->save();
    }

    public function note_request_modificaciones()
    {
        $notes = NoteRequest::where('number_note', 0)->where('state', 'En Revision')->where('type_id', 2)->get();
        if ($notes) {
            foreach ($notes as $note) {
                $note->state = 'Cancelado';
                $note->save();
            }
        }
        return $notes;
    }
}
