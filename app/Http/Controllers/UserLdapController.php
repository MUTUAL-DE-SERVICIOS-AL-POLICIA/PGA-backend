<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Management;
use App\Models\NoteRequest;
use App\Models\UserStore;
use App\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserLdapController extends Controller
{
    public function list_users_rol()
    {
        $users = User::with(['roles.permissions', 'permissions'])
            ->where('active', '=', true)
            ->where('username', 'not like', 'admin')
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', ['almacenes', 'admin']);
            })
            ->get();

        $userStore = UserStore::all();
        $userStoreStatus = $userStore->pluck('active', 'name_user');

        $sortedUsers = $users->map(function ($user) use ($userStoreStatus) {
            return [
                'id' => $user->id,
                'username' => $user->username,
                'position' => $user->position,
                'roles' => $user->roles->pluck('name'),
                'all_permissions' => $user->all_permissions,
                'active' => $userStoreStatus->get($user->username, false),
            ];
        })->sortByDesc('active')
            ->values();

        return response()->json($sortedUsers);
    }

    public function list_user_new(Request $request)
    {
        $data = $request->all();

        $username = $data[0]['username'] ?? null;
        $active = $data[0]['active'] ?? false;
        $role = $data[0]['roles'][0] ?? 'default_role';
        $request = DB::table('user_stores')->insert([]);

        if ($username) {
            $userStore = DB::table('user_stores')->where('name_user', $username)->first();

            if ($userStore) {
                DB::table('user_stores')
                    ->where('name_user', $username)
                    ->update(['active' => $active]);
                return true;
            } else {
                DB::table('user_stores')->insert([
                    'name_user' => $username,
                    'rol' => $role,
                    'active' => $active,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                return true;
            }
        }
        return false;
    }

    public function list_users($userId)
    {
        $startDate = request()->query('start_date');
        $endDate = request()->query('end_date');
        $period = Management::latest()->first();


        $query = NoteRequest::with(['employee', 'materials'])
            ->where('state', 'like', 'Aceptado')
            ->where('user_register', $userId);
        if ($startDate && $endDate) {
            $query->whereBetween('received_on_date', [$startDate, $endDate])->where('management_id', '=', $period->id);
        }

        $noteRequests = $query->get();

        $employee = $noteRequests->first() ? $noteRequests->first()->employee : null;
        $materialsGrouped = [];

        if ($employee) {
            $id = $employee->id;
            $cargo = DB::table('public.contracts as c')
                ->join('public.positions as p', 'c.position_id', '=', 'p.id')
                ->join('public.employees as e', 'c.employee_id', '=', 'e.id')
                ->join('public.position_groups as pg', 'p.position_group_id', '=', 'pg.id')
                ->select('c.employee_id', 'e.first_name', 'e.last_name', 'e.mothers_last_name', 'p.name as position_name', 'pg.name as group_name', 'pg.id as group_id')
                ->where('c.active', true)
                ->whereNull('c.deleted_at')
                ->whereIn('pg.id', [7, 8, 9, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21])
                ->where('c.employee_id', $id)
                ->unionAll(
                    DB::table('public.consultant_contracts as cc')
                        ->join('public.consultant_positions as cp', 'cc.consultant_position_id', '=', 'cp.id')
                        ->join('public.employees as e', 'cc.employee_id', '=', 'e.id')
                        ->join('public.position_groups as pg', 'cp.position_group_id', '=', 'pg.id')
                        ->select('cc.employee_id', 'e.first_name', 'e.last_name', 'e.mothers_last_name', 'cp.name as position_name', 'pg.name as group_name', 'pg.id as group_id')
                        ->where('cc.active', true)
                        ->whereNull('cc.deleted_at')
                        ->whereIn('pg.id', [7, 8, 9, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21])
                        ->where('cc.employee_id', $id)
                )
                ->get();
            $positionName = isset($cargo[0]) ? $cargo[0]->position_name : null;
        } else {
            $positionName = null;
        }

        foreach ($noteRequests as $noteRequest) {
            foreach ($noteRequest->materials as $material) {
                $materialId = $material->id;

                if (isset($materialsGrouped[$materialId])) {
                    $materialsGrouped[$materialId]['amount_requested'] += $material->pivot->amount_request;
                    $materialsGrouped[$materialId]['delivered_quantity'] += $material->pivot->delivered_quantity;
                } else {
                    $materialsGrouped[$materialId] = [
                        'material_id' => $material->id,
                        'name_material' => $material->pivot->name_material,
                        'amount_requested' => $material->pivot->amount_request,
                        'delivered_quantity' => $material->pivot->delivered_quantity,
                        'unit_material' => $material->unit_material,
                        'cost' => $material->average_cost,
                    ];
                }
            }
        }
        $result = [
            'employee' => [
                'id' => $employee ? $employee->id : null,
                'name' => $employee ? $employee->fullname : null,
                'position_name' => $positionName,
            ],
            'materials' => array_values($materialsGrouped),
        ];

        return response()->json($result);
    }

    public function list_users_print($userId)
    {
        $startDate = request()->query('start_date');
        $endDate = request()->query('end_date');
        $period = Management::latest()->first();

        $query = NoteRequest::with(['employee', 'materials'])
            ->where('state', 'like', 'Aceptado')
            ->where('user_register', $userId);
        if ($startDate && $endDate) {
            $query->whereBetween('received_on_date', [$startDate, $endDate])->where('management_id', '=', $period->id);
        }

        $noteRequests = $query->get();

        $employee = $noteRequests->first() ? $noteRequests->first()->employee : null;
        $materialsGrouped = [];


        if ($employee) {
            $id = $employee->id;
            $cargo = DB::table('public.contracts as c')
                ->join('public.positions as p', 'c.position_id', '=', 'p.id')
                ->join('public.employees as e', 'c.employee_id', '=', 'e.id')
                ->join('public.position_groups as pg', 'p.position_group_id', '=', 'pg.id')
                ->select('c.employee_id', 'e.first_name', 'e.last_name', 'e.mothers_last_name', 'p.name as position_name', 'pg.name as group_name', 'pg.id as group_id')
                ->where('c.active', true)
                ->whereNull('c.deleted_at')
                ->whereIn('pg.id', [7, 8, 9, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21])
                ->where('c.employee_id', $id)
                ->unionAll(
                    DB::table('public.consultant_contracts as cc')
                        ->join('public.consultant_positions as cp', 'cc.consultant_position_id', '=', 'cp.id')
                        ->join('public.employees as e', 'cc.employee_id', '=', 'e.id')
                        ->join('public.position_groups as pg', 'cp.position_group_id', '=', 'pg.id')
                        ->select('cc.employee_id', 'e.first_name', 'e.last_name', 'e.mothers_last_name', 'cp.name as position_name', 'pg.name as group_name', 'pg.id as group_id')
                        ->where('cc.active', true)
                        ->whereNull('cc.deleted_at')
                        ->whereIn('pg.id', [7, 8, 9, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21])
                        ->where('cc.employee_id', $id)
                )
                ->get();

            $positionName = isset($cargo[0]) ? $cargo[0]->position_name : null;
        } else {
            $positionName = null;
        }

        foreach ($noteRequests as $noteRequest) {
            foreach ($noteRequest->materials as $material) {
                $materialId = $material->id;

                if (isset($materialsGrouped[$materialId])) {
                    $materialsGrouped[$materialId]['amount_requested'] += $material->pivot->amount_request;
                    $materialsGrouped[$materialId]['delivered_quantity'] += $material->pivot->delivered_quantity;
                } else {
                    $materialsGrouped[$materialId] = [
                        'material_id' => $material->id,
                        'name_material' => $material->pivot->name_material,
                        'amount_requested' => $material->pivot->amount_request,
                        'delivered_quantity' => $material->pivot->delivered_quantity,
                        'unit_material' => $material->unit_material,
                        'cost' => $material->average_cost,
                    ];
                }
            }
        }
        $result = [
            'employee' => [
                'id' => $employee ? $employee->id : null,
                'name' => $employee ? $employee->fullname : null,
                'position_name' => $positionName,
            ],
            'materials' => array_values($materialsGrouped),
        ];

        $data = [
            'title' => 'SALIDAS POR FUNCIONARIO',
            'employee' => $employee->fullname,
            'position' => $positionName,
            'date_on' => $startDate,
            'date_end' => $endDate,
            'date' => Carbon::now()->format('Y'),
            'result' => $result,
        ];
        $pdf = Pdf::loadView('Request_Employee.RequestEmployee', $data);
        return $pdf->download('Salidas_por_funcionario.pdf');
    }

    public function list_user_request()
    {
        $employees = Employee::whereHas('note_requests')
            ->orderBy('last_name')
            ->get();

        $result = $employees->map(function ($employee) {
            return [
                'id' => $employee->id,
                'full_name' => $employee->fullName(),
            ];
        });

        return response()->json($result);
    }


    public function list_users_direction($id_direction)
    {
        $startDate = request()->query('start_date');
        $endDate = request()->query('end_date');

        $period = Management::latest()->first();
        if (!$period) {
            return response()->json(['error' => 'No management period found'], 404);
        }
        $managementId = $period->id;

        if ($id_direction == 1) {
            $name = "Dirección de Estrategias Sociales e Inversiones";
            $array = [7, 8, 9];
        } elseif ($id_direction == 2) {
            $name = "Dirección de Beneficios Económicos";
            $array = [11, 12, 13];
        } elseif ($id_direction == 3) {
            $name = "Dirección de Asuntos Administrativos";
            $array = [14, 15, 16, 17, 18];
        } elseif ($id_direction == 4) {
            $name = "Dirección de Asesoramiento jurídico administrativo y defensa interinstitucional";
            $array = [19, 20, 21];
        } else {
            return response()->json(['error' => 'Invalid direction ID'], 400);
        }

        $employeeIds = DB::table('public.contracts')
            ->join('public.positions', 'public.contracts.position_id', '=', 'public.positions.id')
            ->join('public.employees', 'public.contracts.employee_id', '=', 'public.employees.id')
            ->join('public.position_groups', 'public.positions.position_group_id', '=', 'public.position_groups.id')
            ->where('public.contracts.active', true)
            ->whereNull('public.contracts.deleted_at')
            ->whereIn('public.position_groups.id', $array)
            ->pluck('public.contracts.employee_id')
            ->toArray();

        $consultantEmployeeIds = DB::table('public.consultant_contracts')
            ->join('public.consultant_positions', 'public.consultant_contracts.consultant_position_id', '=', 'public.consultant_positions.id')
            ->join('public.employees', 'public.consultant_contracts.employee_id', '=', 'public.employees.id')
            ->join('public.position_groups', 'public.consultant_positions.position_group_id', '=', 'public.position_groups.id')
            ->where('public.consultant_contracts.active', true)
            ->whereNull('public.consultant_contracts.deleted_at')
            ->whereIn('public.position_groups.id', $array)
            ->pluck('public.consultant_contracts.employee_id')
            ->toArray();

        $allEmployeeIds = array_merge($employeeIds, $consultantEmployeeIds);

        $query = NoteRequest::with('materials')
            ->whereIn('user_register', $allEmployeeIds)
            ->whereNull('deleted_at');

        if ($startDate) {
            $query->where('received_on_date', '>=', $startDate)
                ->where('management_id', '=', $managementId);
        }
        if ($endDate) {
            $query->where('received_on_date', '<=', $endDate)
                ->where('management_id', '=', $managementId);
        }

        $materials = $query->get()
            ->flatMap(function ($noteRequest) {
                return $noteRequest->materials->map(function ($material) {
                    return [
                        'material_id' => $material->id,
                        'name' => $material->pivot->name_material,
                        'unit_material' => $material->unit_material,
                        'amount_requested' => $material->pivot->delivered_quantity,
                        'cost' => ($material->pivot->delivered_quantity * $material->average_cost),
                    ];
                });
            })
            ->groupBy('material_id')
            ->map(function ($groupedMaterials) {
                $totalAmount = $groupedMaterials->sum('amount_requested');
                $cost_total = $groupedMaterials->sum('cost');
                return [
                    'material_name' => $groupedMaterials->first()['name'],
                    'unit_material' => $groupedMaterials->first()['unit_material'],
                    'total_amount_requested' => $totalAmount,
                    'cost' => $cost_total,
                ];
            })
            ->values();

        return response()->json([
            'name' => $name,
            'materials' => $materials
        ]);
    }


    public function list_direction_print($id_direction)
    {
        $startDate = request()->query('start_date');
        $endDate = request()->query('end_date');

        $period = Management::latest()->first();

        if (!$period) {
            return response()->json(['error' => 'No management period found'], 404);
        }

        $managementId = $period->id;

        if ($id_direction == 1) {
            //Direccion de Estrategias Sociales e Inversiones
            $name = "Dirección de Estrategias Sociales e Inversiones";
            $array = [7, 8, 9];
        }
        if ($id_direction == 2) {
            //Direccion de Beneficios Economicos
            $name = "Dirección de Beneficios Económicos";
            $array = [11, 12, 13];
        }
        if ($id_direction == 3) {
            //Direccion de Asuntos Administrativos
            $name = "Dirección de Asuntos Administrativos";
            $array = [14, 15, 16, 17, 18];
        }
        if ($id_direction == 4) {
            //Direccion de Asesoriamiento juridico adm y defensa inter
            $name = "Dirección de Asesoramiento jurídico administrativo y defensa interinstitucional";
            $array = [19, 20, 21];
        }
        $employeeIds = DB::table('public.contracts')
            ->join('public.positions', 'public.contracts.position_id', '=', 'public.positions.id')
            ->join('public.employees', 'public.contracts.employee_id', '=', 'public.employees.id')
            ->join('public.position_groups', 'public.positions.position_group_id', '=', 'public.position_groups.id')
            ->where('public.contracts.active', true)
            ->whereNull('public.contracts.deleted_at')
            ->whereIn('public.position_groups.id', $array)
            ->pluck('public.contracts.employee_id')
            ->toArray();

        $consultantEmployeeIds = DB::table('public.consultant_contracts')
            ->join('public.consultant_positions', 'public.consultant_contracts.consultant_position_id', '=', 'public.consultant_positions.id')
            ->join('public.employees', 'public.consultant_contracts.employee_id', '=', 'public.employees.id')
            ->join('public.position_groups', 'public.consultant_positions.position_group_id', '=', 'public.position_groups.id')
            ->where('public.consultant_contracts.active', true)
            ->whereNull('public.consultant_contracts.deleted_at')
            ->whereIn('public.position_groups.id', $array)
            ->pluck('public.consultant_contracts.employee_id')
            ->toArray();

        $allEmployeeIds = array_merge($employeeIds, $consultantEmployeeIds);

        // Construir la consulta de materiales
        $query = NoteRequest::with('materials')
            ->whereIn('user_register', $allEmployeeIds)
            ->whereNull('deleted_at');

        // Filtrar por fechas si están presentes
        if ($startDate) {
            $query->where('received_on_date', '>=', $startDate)->where('management_id', '=', $period->id);
        }
        if ($endDate) {
            $query->where('received_on_date', '<=', $endDate)->where('management_id', '=', $period->id);
        }

        $materials = $query->get()
            ->flatMap(function ($noteRequest) {
                return $noteRequest->materials->map(function ($material) {
                    return [
                        'material_id' => $material->id,
                        'name' => $material->pivot->name_material,
                        'unit_material' => $material->unit_material,
                        'amount_requested' => $material->pivot->delivered_quantity,
                        'cost' => ($material->pivot->delivered_quantity * $material->average_cost),
                    ];
                });
            })
            ->groupBy('material_id')
            ->map(function ($groupedMaterials) {
                $totalAmount = $groupedMaterials->sum('amount_requested');
                $cost_total = $groupedMaterials->sum('cost');
                return [
                    'material_name' => $groupedMaterials->first()['name'],
                    'unit_material' => $groupedMaterials->first()['unit_material'],
                    'total_amount_requested' => $totalAmount,
                    'cost' => $cost_total,
                ];
            })
            ->values();


        $data = [
            'title' => 'SALIDAS POR DIRECCIONES',
            'direction' => $name,
            'date_on' => $startDate,
            'date_end' => $endDate,
            'date' => Carbon::now()->format('Y'),
            'materials' => $materials
        ];

        $pdf = Pdf::loadView('Request_Directory.RequestDirectory', $data);
        return $pdf->download('Salidas_por_Direccion.pdf');
    }
}
