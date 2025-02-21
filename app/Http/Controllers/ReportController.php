<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Group;
use App\Models\Management;
use App\Models\Material;
use App\Models\Note_Entrie;
use App\Models\NoteRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{

    public function kardex($materialId)
    {
        try {
            $endDate = request()->query('end_date');
            $latestManagement = Management::latest('id')->first();
            $material = Material::findOrFail($materialId);
            $group_material = $material->group()->first()->name_group;


            $kardex = [];
            $stock = 0;
            $totalValuation = 0;
            $max_total = 0;
            $entries = $material->noteEntries()
                ->where('management_id', $latestManagement->id)
                ->where('delivery_date', '<=', $endDate)
                ->orderBy('delivery_date', 'asc')
                ->get();

            $requests = $material->noteRequests()
                ->where('management_id', $latestManagement->id)
                ->where('state', '=', 'Aceptado')
                ->where('received_on_date', '<=', $endDate)
                ->orderBy('received_on_date', 'asc')
                ->get();

            $movements = [];
            foreach ($entries as $entry) {
                $movements[] = [
                    'date' => $entry->pivot->created_at,
                    'type' => 'entry',
                    'description' => $entry->name_supplier . ' - Nota de Entrada #' . $entry->number_note,
                    'quantity' => $entry->pivot->amount_entries,
                    'cost_unit' => number_format($entry->pivot->cost_unit, 2),
                ];
            }

            foreach ($requests as $request) {
                $employee = Employee::find($request->user_register);
                $movements[] = [
                    'date' => $request->pivot->created_at,
                    'type' => 'exit',
                    'description' => ucwords(strtolower("{$employee->first_name} {$employee->last_name} {$employee->mothers_last_name}")) . ' - Solicitud #' . $request->id,
                    'quantity' => $request->pivot->delivered_quantity,
                    'cost_unit' => null,
                ];
            }
            usort($movements, function ($a, $b) {
                return strtotime($a['date']) - strtotime($b['date']);
            });

            $fifoQueue = [];

            foreach ($movements as $movement) {
                if ($movement['type'] === 'entry') {
                    $fifoQueue[] = [
                        'quantity' => $movement['quantity'],
                        'cost_unit' => $movement['cost_unit'],
                    ];
                    $stock += $movement['quantity'];
                    $totalValuation = $movement['quantity'] * $movement['cost_unit'];
                    $max_total = $max_total + $totalValuation;

                    $kardex[] = [
                        'date' => date('Y-m-d', strtotime($movement['date'])),
                        'description' => $movement['description'],
                        'entradas' => $movement['quantity'],
                        'salidas' => 0,
                        'stock_fisico' => $stock,
                        'cost_unit' => number_format($movement['cost_unit'], 2),
                        'cost_total' => number_format($max_total, 2),
                    ];
                } elseif ($movement['type'] === 'exit') {
                    $quantityToDeliver = $movement['quantity'];
                    $costTotal = 0;

                    while ($quantityToDeliver > 0 && count($fifoQueue) > 0) {
                        $fifoItem = array_shift($fifoQueue);

                        if ($fifoItem['quantity'] > $quantityToDeliver) {
                            $costUnit = $fifoItem['cost_unit'];
                            $costTotal = $quantityToDeliver * $costUnit;
                            $max_total = $max_total - $costTotal;

                            $kardex[] = [
                                'date' => date('Y-m-d', strtotime($movement['date'])),
                                'description' => $movement['description'],
                                'entradas' => 0,
                                'salidas' => $quantityToDeliver,
                                'stock_fisico' => $stock - $quantityToDeliver,
                                'cost_unit' => number_format($costUnit, 2),
                                'cost_total' => number_format($max_total, 2),
                            ];

                            $fifoItem['quantity'] -= $quantityToDeliver;
                            array_unshift($fifoQueue, $fifoItem);
                            $stock -= $quantityToDeliver;
                            $totalValuation = max($totalValuation - $costTotal, 0);
                            $quantityToDeliver = 0;
                        } else {
                            $costUnit = $fifoItem['cost_unit'];
                            $costTotal = $fifoItem['quantity'] * $costUnit;
                            $max_total = $max_total - $costTotal;

                            $kardex[] = [
                                'date' => date('Y-m-d', strtotime($movement['date'])),
                                'description' => $movement['description'],
                                'entradas' => 0,
                                'salidas' => $fifoItem['quantity'],
                                'stock_fisico' => $stock - $fifoItem['quantity'],
                                'cost_unit' => number_format($costUnit, 2),
                                'cost_total' => number_format($max_total, 2),
                            ];

                            $quantityToDeliver -= $fifoItem['quantity'];
                            $stock -= $fifoItem['quantity'];
                            $totalValuation = max($totalValuation - $costTotal, 0);
                        }
                    }
                }
            }
            return response()->json([
                'code_material' => $material->code_material,
                'description' => $material->description,
                'unit_material' => $material->unit_material,
                'group' => strtoupper($group_material),
                'kardex_de_existencia' => $kardex
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No se pudo generar el Kardex'], 500);
        }
    }

    public function print_kardex($materialId)
    {

        try {
            $endDate = request()->query('end_date');
            $latestManagement = Management::latest('id')->first();
            $material = Material::findOrFail($materialId);
            $group_material = $material->group()->first()->name_group;

            $kardex = [];
            $stock = 0;
            $totalValuation = 0;
            $max_total = 0;

            $entries = $material->noteEntries()
                ->where('management_id', $latestManagement->id)
                ->where('delivery_date', '<=', $endDate)
                ->orderBy('delivery_date', 'asc')
                ->get();

            $requests = $material->noteRequests()
                ->where('management_id', $latestManagement->id)
                ->where('state', '=', 'Aceptado')
                ->where('received_on_date', '<=', $endDate)
                ->orderBy('received_on_date', 'asc')
                ->get();

            $movements = [];

            foreach ($entries as $entry) {
                $movements[] = [
                    'date' => $entry->pivot->created_at,
                    'type' => 'entry',
                    'description' => $entry->name_supplier . ' - Nota de Entrada #' . $entry->number_note,
                    'quantity' => $entry->pivot->amount_entries,
                    'cost_unit' => number_format($entry->pivot->cost_unit, 2),
                ];
            }

            foreach ($requests as $request) {
                $employee = Employee::find($request->user_register);
                $movements[] = [
                    'date' => $request->pivot->created_at,
                    'type' => 'exit',
                    'description' => ucwords(strtolower("{$employee->first_name} {$employee->last_name} {$employee->mothers_last_name}")) . ' - Solicitud #' . $request->id,
                    'quantity' => $request->pivot->delivered_quantity,
                    'cost_unit' => null,
                ];
            }


            usort($movements, function ($a, $b) {
                return strtotime($a['date']) - strtotime($b['date']);
            });

            $fifoQueue = [];

            foreach ($movements as $movement) {
                if ($movement['type'] === 'entry') {
                    $fifoQueue[] = [
                        'quantity' => $movement['quantity'],
                        'cost_unit' => $movement['cost_unit'],
                    ];
                    $stock += $movement['quantity'];
                    $totalValuation = $movement['quantity'] * $movement['cost_unit'];
                    $max_total = $max_total + $totalValuation;

                    $kardex[] = [
                        'date' => date('Y-m-d', strtotime($movement['date'])),
                        'description' => $movement['description'],
                        'entradas' => $movement['quantity'],
                        'salidas' => 0,
                        'stock_fisico' => $stock,
                        'cost_unit' => number_format($movement['cost_unit'], 2),
                        'cost_total' => number_format($max_total, 2),
                    ];
                } elseif ($movement['type'] === 'exit') {
                    $quantityToDeliver = $movement['quantity'];
                    $costTotal = 0;

                    while ($quantityToDeliver > 0 && count($fifoQueue) > 0) {
                        $fifoItem = array_shift($fifoQueue);

                        if ($fifoItem['quantity'] > $quantityToDeliver) {
                            $costUnit = $fifoItem['cost_unit'];
                            $costTotal = $quantityToDeliver * $costUnit;
                            $max_total = $max_total - $costTotal;

                            $kardex[] = [
                                'date' => date('Y-m-d', strtotime($movement['date'])),
                                'description' => $movement['description'],
                                'entradas' => 0,
                                'salidas' => $quantityToDeliver,
                                'stock_fisico' => $stock - $quantityToDeliver,
                                'cost_unit' => number_format($costUnit, 2),
                                'cost_total' => number_format($max_total, 2),
                            ];

                            $fifoItem['quantity'] -= $quantityToDeliver;
                            array_unshift($fifoQueue, $fifoItem);
                            $stock -= $quantityToDeliver;
                            $totalValuation = max($totalValuation - $costTotal, 0);
                            $quantityToDeliver = 0;
                        } else {
                            $costUnit = $fifoItem['cost_unit'];
                            $costTotal = $fifoItem['quantity'] * $costUnit;
                            $max_total = $max_total - $costTotal;

                            $kardex[] = [
                                'date' => date('Y-m-d', strtotime($movement['date'])),
                                'description' => $movement['description'],
                                'entradas' => 0,
                                'salidas' => $fifoItem['quantity'],
                                'stock_fisico' => $stock - $fifoItem['quantity'],
                                'cost_unit' => number_format($costUnit, 2),
                                'cost_total' => number_format($max_total, 2),
                            ];

                            $quantityToDeliver -= $fifoItem['quantity'];
                            $stock -= $fifoItem['quantity'];
                            $totalValuation = max($totalValuation - $costTotal, 0);
                        }
                    }
                }
            }

            $data = [
                'title' => 'KARDEX DE EXISTENCIAS',
                'code_material' => $material->code_material,
                'description' => $material->description,
                'unit_material' => $material->unit_material,
                'group' => strtoupper($group_material),
                'kardex_de_existencia' => $kardex
            ];

            $pdf = Pdf::loadView('Report_Kardex.ReportKardex', $data)->setPaper('letter', 'landscape');
            return $pdf->stream('Kardex de Existencia.pdf');
        } catch (\Exception $e) {
            return response()->json(['error' => 'No se pudo generar el Kardex'], 500);
        }
    }

    public function dashboard_data()
    {
        $period = Management::latest()->first();
        $num_material = Material::where('stock', '>', 0)->count();
        $num_material_total = Material::all()->count();
        $num_order_total = NoteRequest::where('management_id', '=', $period->id)->count();
        $num_order = NoteRequest::where('state', '=', 'En Revision')->where('management_id', '=', $period->id)->count();
        $num_delivery = NoteRequest::where('state', '=', 'Aceptado')->where('management_id', '=', $period->id)->count();

        return response()->json([
            'num_material' => $num_material,
            'num_material_total' => $num_material_total,
            'num_order' => $num_order,
            'num_order_total' => $num_order_total,
            'num_delivery' => $num_delivery,
        ]);
    }

    // public function kardexGeneral()
    // {
    //     try {
    //         $materials = Material::all();
    //         $kardexGeneral = [];

    //         foreach ($materials as $material) {
    //             $stock = 0;
    //             $totalValuation = 0;

    //             $entries = $material->noteEntries()->orderBy('delivery_date', 'asc')->get();
    //             $requests = $material->noteRequests()->where('state', '=', 'Aceptado')->orderBy('received_on_date', 'asc')->get();

    //             $movements = [];

    //             foreach ($entries as $entry) {
    //                 $movements[] = [
    //                     'date' => $entry->pivot->created_at,
    //                     'type' => 'entry',
    //                     'description' => $entry->name_supplier . ' - Nota de Entrada #' . $entry->number_note,
    //                     'quantity' => $entry->pivot->amount_entries,
    //                     'cost_unit' => number_format($entry->pivot->cost_unit, 2),
    //                 ];
    //             }

    //             foreach ($requests as $request) {
    //                 $employee = Employee::find($request->user_register);
    //                 $movements[] = [
    //                     'date' => $request->pivot->created_at,
    //                     'type' => 'exit',
    //                     'description' => ucwords(strtolower("{$employee->first_name} {$employee->last_name} {$employee->mothers_last_name}")) . ' - Solicitud #' . $request->id,
    //                     'quantity' => $request->pivot->delivered_quantity,
    //                     'cost_unit' => null,
    //                 ];
    //             }
    //             if (count($movements) === 0) {
    //                 continue;
    //             }
    //             usort($movements, function ($a, $b) {
    //                 return strtotime($b['date']) - strtotime($a['date']);
    //             });

    //             $fifoQueue = [];
    //             $kardex = [];

    //             foreach ($movements as $movement) {
    //                 if ($movement['type'] === 'entry') {
    //                     $fifoQueue[] = [
    //                         'quantity' => $movement['quantity'],
    //                         'cost_unit' => $movement['cost_unit'],
    //                     ];
    //                     $stock += $movement['quantity'];
    //                     $totalValuation += $movement['quantity'] * $movement['cost_unit'];

    //                     $kardex[] = [
    //                         'date' => date('Y-m-d', strtotime($movement['date'])),
    //                         'material' => $material->description,
    //                         'description' => $movement['description'],
    //                         'entradas' => $movement['quantity'],
    //                         'salidas' => 0,
    //                         'stock_fisico' => $stock,
    //                         'cost_unit' => number_format($movement['cost_unit'], 2),
    //                         'cost_total' => number_format(max($totalValuation, 0), 2),
    //                     ];
    //                 } elseif ($movement['type'] === 'exit') {
    //                     $quantityToDeliver = $movement['quantity'];
    //                     $costTotal = 0;

    //                     while ($quantityToDeliver > 0 && count($fifoQueue) > 0) {
    //                         $fifoItem = array_shift($fifoQueue);

    //                         if ($fifoItem['quantity'] > $quantityToDeliver) {
    //                             $costUnit = $fifoItem['cost_unit'];
    //                             $costTotal += $quantityToDeliver * $costUnit;

    //                             $kardex[] = [
    //                                 'date' => date('Y-m-d', strtotime($movement['date'])),
    //                                 'material' => $material->description,
    //                                 'description' => $movement['description'],
    //                                 'entradas' => 0,
    //                                 'salidas' => $quantityToDeliver,
    //                                 'stock_fisico' => $stock - $quantityToDeliver,
    //                                 'cost_unit' => number_format($costUnit, 2),
    //                                 'cost_total' => number_format(max($totalValuation - $costTotal, 0), 2),
    //                             ];

    //                             $fifoItem['quantity'] -= $quantityToDeliver;
    //                             array_unshift($fifoQueue, $fifoItem);
    //                             $stock -= $quantityToDeliver;
    //                             $totalValuation = max($totalValuation - $costTotal, 0);
    //                             $quantityToDeliver = 0;
    //                         } else {
    //                             $costUnit = $fifoItem['cost_unit'];
    //                             $costTotal += $fifoItem['quantity'] * $costUnit;

    //                             $kardex[] = [
    //                                 'date' => date('Y-m-d', strtotime($movement['date'])),
    //                                 'material' => $material->description,
    //                                 'description' => $movement['description'],
    //                                 'entradas' => 0,
    //                                 'salidas' => $fifoItem['quantity'],
    //                                 'stock_fisico' => $stock - $fifoItem['quantity'],
    //                                 'cost_unit' => number_format($costUnit, 2),
    //                                 'cost_total' => number_format(max($totalValuation - $costTotal, 0), 2),
    //                             ];

    //                             $quantityToDeliver -= $fifoItem['quantity'];
    //                             $stock -= $fifoItem['quantity'];
    //                             $totalValuation = max($totalValuation - $costTotal, 0);
    //                         }
    //                     }
    //                 }
    //             }
    //             if (!empty($kardex)) {
    //                 $kardexGeneral = array_merge($kardexGeneral, $kardex);
    //             }
    //         }
    //         usort($kardexGeneral, function ($a, $b) {
    //             return strtotime($b['date']) - strtotime($a['date']);
    //         });
    //         $kardexGeneral = array_slice($kardexGeneral, 0, 10);

    //         return response()->json($kardexGeneral);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'error' => 'No se pudo generar el Kardex general',
    //             'message' => $e->getMessage(),
    //             'line' => $e->getLine(),
    //             'file' => $e->getFile()
    //         ], 500);
    //     }
    // }

    public function kardexGeneral()
    {
        try {
            $materials = Material::all();
            $kardexGeneral = [];

            foreach ($materials as $material) {
                $stock = 0;
                $totalValuation = 0;

                $entries = $material->noteEntries()->orderBy('delivery_date', 'asc')->get();
                $requests = $material->noteRequests()->where('state', '=', 'Aceptado')->orderBy('received_on_date', 'asc')->get();

                $movements = [];

                foreach ($entries as $entry) {
                    $quantity = is_numeric($entry->pivot->amount_entries) ? floatval($entry->pivot->amount_entries) : 0;
                    $costUnit = is_numeric($entry->pivot->cost_unit) ? floatval($entry->pivot->cost_unit) : 0;

                    $movements[] = [
                        'date' => $entry->pivot->created_at,
                        'type' => 'entry',
                        'description' => $entry->name_supplier . ' - Nota de Entrada #' . $entry->number_note,
                        'quantity' => $quantity,
                        'cost_unit' => $costUnit,
                    ];
                }

                foreach ($requests as $request) {
                    $employee = Employee::find($request->user_register);
                    $quantity = is_numeric($request->pivot->delivered_quantity) ? floatval($request->pivot->delivered_quantity) : 0;

                    $movements[] = [
                        'date' => $request->pivot->created_at,
                        'type' => 'exit',
                        'description' => ucwords(strtolower("{$employee->first_name} {$employee->last_name} {$employee->mothers_last_name}")) . ' - Solicitud #' . $request->id,
                        'quantity' => $quantity,
                        'cost_unit' => null,
                    ];
                }

                if (count($movements) === 0) {
                    continue;
                }

                usort($movements, function ($a, $b) {
                    return strtotime($b['date']) - strtotime($a['date']);
                });

                $fifoQueue = [];
                $kardex = [];

                foreach ($movements as $movement) {
                    if ($movement['type'] === 'entry') {
                        $fifoQueue[] = [
                            'quantity' => $movement['quantity'],
                            'cost_unit' => $movement['cost_unit'],
                        ];

                        $stock += $movement['quantity'];
                        $totalValuation += $movement['quantity'] * $movement['cost_unit'];

                        $kardex[] = [
                            'date' => date('Y-m-d', strtotime($movement['date'])),
                            'material' => $material->description,
                            'description' => $movement['description'],
                            'entradas' => $movement['quantity'],
                            'salidas' => 0,
                            'stock_fisico' => $stock,
                            'cost_unit' => number_format($movement['cost_unit'], 2),
                            'cost_total' => number_format(max($totalValuation, 0), 2),
                        ];
                    } elseif ($movement['type'] === 'exit') {
                        $quantityToDeliver = $movement['quantity'];
                        $costTotal = 0;

                        while ($quantityToDeliver > 0 && count($fifoQueue) > 0) {
                            $fifoItem = array_shift($fifoQueue);
                            $costUnit = is_numeric($fifoItem['cost_unit']) ? floatval($fifoItem['cost_unit']) : 0;

                            if ($fifoItem['quantity'] > $quantityToDeliver) {
                                $costTotal += $quantityToDeliver * $costUnit;

                                $kardex[] = [
                                    'date' => date('Y-m-d', strtotime($movement['date'])),
                                    'material' => $material->description,
                                    'description' => $movement['description'],
                                    'entradas' => 0,
                                    'salidas' => $quantityToDeliver,
                                    'stock_fisico' => $stock - $quantityToDeliver,
                                    'cost_unit' => number_format($costUnit, 2),
                                    'cost_total' => number_format(max($totalValuation - $costTotal, 0), 2),
                                ];

                                $fifoItem['quantity'] -= $quantityToDeliver;
                                array_unshift($fifoQueue, $fifoItem);
                                $stock -= $quantityToDeliver;
                                $totalValuation = max($totalValuation - $costTotal, 0);
                                $quantityToDeliver = 0;
                            } else {
                                $costTotal += $fifoItem['quantity'] * $costUnit;

                                $kardex[] = [
                                    'date' => date('Y-m-d', strtotime($movement['date'])),
                                    'material' => $material->description,
                                    'description' => $movement['description'],
                                    'entradas' => 0,
                                    'salidas' => $fifoItem['quantity'],
                                    'stock_fisico' => $stock - $fifoItem['quantity'],
                                    'cost_unit' => number_format($costUnit, 2),
                                    'cost_total' => number_format(max($totalValuation - $costTotal, 0), 2),
                                ];

                                $quantityToDeliver -= $fifoItem['quantity'];
                                $stock -= $fifoItem['quantity'];
                                $totalValuation = max($totalValuation - $costTotal, 0);
                            }
                        }
                    }
                }

                if (!empty($kardex)) {
                    $kardexGeneral = array_merge($kardexGeneral, $kardex);
                }
            }

            usort($kardexGeneral, function ($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });

            $kardexGeneral = array_slice($kardexGeneral, 0, 10);

            return response()->json($kardexGeneral);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'No se pudo generar el Kardex general',
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }


    public function ValuedPhysical(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $latestManagement = Management::latest('id')->first();

        $notesQuery = Note_Entrie::where('management_id', $latestManagement->id)
            ->where('state', 'Aceptado')
            ->with(['materials' => function ($query) {
                $query->select('materials.id', 'materials.description', 'materials.code_material', 'materials.group_id', 'materials.unit_material');
            }, 'materials.group' => function ($query) {
                $query->select('groups.id', 'groups.name_group', 'groups.code');
            }]);

        if ($startDate && $endDate) {
            $notesQuery->whereBetween('delivery_date', [$startDate, $endDate]);
        }

        $notesData = [];
        $notesQuery->chunk(100, function ($notes) use (&$notesData) {
            foreach ($notes as $note) {
                foreach ($note->materials as $material) {
                    $group = $material->group;
                    $groupName = $group ? $group->name_group : 'Sin grupo';
                    $groupCode = $group ? $group->code : null;

                    $materialCode = $material->code_material;
                    $materialName = $material->description;
                    $materialUnit = $material->unit_material;
                    $amountEntries = (float) $material->pivot->amount_entries;
                    $costUnit = (float) $material->pivot->cost_unit;
                    $deliveryDate = $note->delivery_date;

                    if (!isset($notesData[$groupName])) {
                        $notesData[$groupName] = [
                            'codigo_grupo' => $groupCode,
                            'materiales' => []
                        ];
                    }

                    if (!isset($notesData[$groupName]['materiales'][$materialCode])) {
                        $notesData[$groupName]['materiales'][$materialCode] = [
                            'codigo_material' => $materialCode,
                            'nombre_material' => $materialName,
                            'unidad_material' => $materialUnit,
                            'lotes' => []
                        ];
                    }

                    $notesData[$groupName]['materiales'][$materialCode]['lotes'][] = [
                        'fecha_ingreso' => $deliveryDate,
                        'cantidad_inicial' => $amountEntries,
                        'cantidad' => $amountEntries,
                        'precio_unitario' => $costUnit
                    ];
                }
            }
        });

        $requestsQuery = NoteRequest::where('management_id', $latestManagement->id)
            ->with(['materials' => function ($query) {
                $query->select('materials.id', 'materials.code_material', 'materials.group_id');
            }, 'materials.group' => function ($query) {
                $query->select('groups.id', 'groups.name_group', 'groups.code');
            }])->where('state', 'Aceptado');

        if ($startDate && $endDate) {
            $requestsQuery->whereBetween('received_on_date', [$startDate, $endDate]);
        }

        $requestsQuery->chunk(100, function ($requests) use (&$notesData) {
            foreach ($requests as $request) {
                foreach ($request->materials as $material) {
                    $group = $material->group;
                    $groupName = $group ? $group->name_group : 'Sin grupo';
                    $materialCode = $material->code_material;
                    $deliveredQuantity = (float) $material->pivot->delivered_quantity;

                    if (isset($notesData[$groupName]['materiales'][$materialCode])) {
                        $lotes = &$notesData[$groupName]['materiales'][$materialCode]['lotes'];

                        foreach ($lotes as &$lote) {
                            if ($deliveredQuantity <= 0) {
                                break;
                            }

                            if ($lote['cantidad'] >= $deliveredQuantity) {
                                $lote['cantidad'] -= $deliveredQuantity;
                                $deliveredQuantity = 0;
                            } else {
                                $deliveredQuantity -= $lote['cantidad'];
                                $lote['cantidad'] = 0;
                            }
                        }
                    }
                }
            }
        });

        $result = [];
        foreach ($notesData as $groupName => $groupData) {
            $groupResult = [
                'grupo' => $groupName,
                'codigo_grupo' => $groupData['codigo_grupo'],
                'materiales' => []
            ];

            foreach ($groupData['materiales'] as $materialCode => $materialData) {
                $materialLotes = array_map(function ($lote) {
                    return [
                        'fecha_ingreso' => $lote['fecha_ingreso'],
                        'cantidad_inicial' => $lote['cantidad_inicial'],
                        'cantidad_restante' => $lote['cantidad'],
                        'precio_unitario' => $lote['precio_unitario'],
                        'cantidad_1' => $lote['cantidad_inicial'] * $lote['precio_unitario'],
                        'cantidad_2' => ($lote['cantidad_inicial'] - $lote['cantidad']) * $lote['precio_unitario'],
                        'cantidad_3' => $lote['cantidad'] * $lote['precio_unitario'],
                    ];
                }, $materialData['lotes']);

                $groupResult['materiales'][] = [
                    'codigo_material' => $materialData['codigo_material'],
                    'nombre_material' => $materialData['nombre_material'],
                    'unidad_material' => $materialData['unidad_material'],
                    'lotes' => $materialLotes
                ];
            }

            $result[] = $groupResult;
        }

        $note = Note_Entrie::getFirstNoteOfYear();
        $formattedDate = $note ? Note_Entrie::formatDate($note->delivery_date) : null;

        return response()->json([
            'date_note' => $formattedDate,
            'data' => $result,
        ]);
    }

    public function PrintValuedPhysical(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $latestManagement = Management::latest('id')->first();

        $notesQuery = Note_Entrie::where('management_id', $latestManagement->id)
            ->where('state', 'Aceptado')
            ->with(['materials' => function ($query) {
                $query->select('materials.id', 'materials.description', 'materials.code_material', 'materials.group_id', 'materials.unit_material');
            }, 'materials.group' => function ($query) {
                $query->select('groups.id', 'groups.name_group', 'groups.code');
            }]);

        if ($startDate && $endDate) {
            $notesQuery->whereBetween('delivery_date', [$startDate, $endDate]);
        }

        $notesData = [];
        $notesQuery->chunk(100, function ($notes) use (&$notesData) {
            foreach ($notes as $note) {
                foreach ($note->materials as $material) {
                    $group = $material->group;
                    $groupName = $group ? $group->name_group : 'Sin grupo';
                    $groupCode = $group ? $group->code : null;

                    $materialCode = $material->code_material;
                    $materialName = $material->description;
                    $materialUnit = $material->unit_material;
                    $amountEntries = (float) $material->pivot->amount_entries;
                    $costUnit = (float) $material->pivot->cost_unit;
                    $deliveryDate = $note->delivery_date;

                    if (!isset($notesData[$groupName])) {
                        $notesData[$groupName] = [
                            'codigo_grupo' => $groupCode,
                            'materiales' => []
                        ];
                    }

                    if (!isset($notesData[$groupName]['materiales'][$materialCode])) {
                        $notesData[$groupName]['materiales'][$materialCode] = [
                            'codigo_material' => $materialCode,
                            'nombre_material' => $materialName,
                            'unidad_material' => $materialUnit,
                            'lotes' => []
                        ];
                    }

                    $notesData[$groupName]['materiales'][$materialCode]['lotes'][] = [
                        'fecha_ingreso' => $deliveryDate,
                        'cantidad_inicial' => $amountEntries,
                        'cantidad' => $amountEntries,
                        'precio_unitario' => $costUnit
                    ];
                }
            }
        });

        $requestsQuery = NoteRequest::where('management_id', $latestManagement->id)
            ->with(['materials' => function ($query) {
                $query->select('materials.id', 'materials.code_material', 'materials.group_id');
            }, 'materials.group' => function ($query) {
                $query->select('groups.id', 'groups.name_group', 'groups.code');
            }])->where('state', 'Aceptado');

        if ($startDate && $endDate) {
            $requestsQuery->whereBetween('received_on_date', [$startDate, $endDate]);
        }

        $requestsQuery->chunk(100, function ($requests) use (&$notesData) {
            foreach ($requests as $request) {
                foreach ($request->materials as $material) {
                    $group = $material->group;
                    $groupName = $group ? $group->name_group : 'Sin grupo';
                    $materialCode = $material->code_material;
                    $deliveredQuantity = (float) $material->pivot->delivered_quantity;

                    if (isset($notesData[$groupName]['materiales'][$materialCode])) {
                        $lotes = &$notesData[$groupName]['materiales'][$materialCode]['lotes'];

                        foreach ($lotes as &$lote) {
                            if ($deliveredQuantity <= 0) {
                                break;
                            }

                            if ($lote['cantidad'] >= $deliveredQuantity) {
                                $lote['cantidad'] -= $deliveredQuantity;
                                $deliveredQuantity = 0;
                            } else {
                                $deliveredQuantity -= $lote['cantidad'];
                                $lote['cantidad'] = 0;
                            }
                        }
                    }
                }
            }
        });

        $result = [];
        foreach ($notesData as $groupName => $groupData) {
            $groupResult = [
                'grupo' => $groupName,
                'codigo_grupo' => $groupData['codigo_grupo'],
                'materiales' => []
            ];

            foreach ($groupData['materiales'] as $materialCode => $materialData) {
                $materialLotes = array_map(function ($lote) {
                    return [
                        'fecha_ingreso' => $lote['fecha_ingreso'],
                        'cantidad_inicial' => $lote['cantidad_inicial'],
                        'cantidad_restante' => $lote['cantidad'],
                        'precio_unitario' => $lote['precio_unitario'],
                        'cantidad_1' => $lote['cantidad_inicial'] * $lote['precio_unitario'],
                        'cantidad_2' => ($lote['cantidad_inicial'] - $lote['cantidad']) * $lote['precio_unitario'],
                        'cantidad_3' => $lote['cantidad'] * $lote['precio_unitario'],
                    ];
                }, $materialData['lotes']);

                $groupResult['materiales'][] = [
                    'codigo_material' => $materialData['codigo_material'],
                    'nombre_material' => $materialData['nombre_material'],
                    'unidad_material' => $materialData['unidad_material'],
                    'lotes' => $materialLotes
                ];
            }

            $result[] = $groupResult;
        }

        $note = Note_Entrie::getFirstNoteOfYear();
        $formattedDate = $note ? Note_Entrie::formatDate($note->delivery_date) : null;

        $data = [
            'title' => 'INVENTARIO FISICO VALORADO ALMACENES MUSERPOL',
            'results' => $result,
            'date_note' => $formattedDate,
        ];

        $pdf = Pdf::loadView('ValuedPhysical.ValuedPhysical', $data)->setPaper('letter', 'landscape');
        return $pdf->stream('Inventario Fisico Valorado.pdf');
    }

    public function ValuedPhysicalPrevis(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $latestManagement = Management::latest('id')->first();

        $notesQuery = Note_Entrie::where('management_id', $latestManagement->id)
            ->where('state', 'Aceptado')
            ->with(['materials' => function ($query) {
                $query->select('materials.id', 'materials.group_id');
            }, 'materials.group' => function ($query) {
                $query->select('groups.id', 'groups.name_group', 'groups.code');
            }]);

        if ($startDate && $endDate) {
            $notesQuery->whereBetween('delivery_date', [$startDate, $endDate]);
        }

        $groupTotals = [];

        $notesQuery->chunk(100, function ($notes) use (&$groupTotals) {
            foreach ($notes as $note) {
                foreach ($note->materials as $material) {
                    $group = $material->group;
                    $groupName = $group ? $group->name_group : 'Sin grupo';
                    $groupCode = $group ? $group->code : null;

                    $amountEntries = $material->pivot->amount_entries;
                    $costUnit = $material->pivot->cost_unit;
                    $totalCost = $amountEntries * $costUnit;

                    if (!isset($groupTotals[$groupName])) {
                        $groupTotals[$groupName] = [
                            'codigo_grupo' => $groupCode,
                            'total_cantidad' => 0,
                            'total_presupuesto' => 0
                        ];
                    }

                    $groupTotals[$groupName]['total_cantidad'] += $amountEntries;
                    $groupTotals[$groupName]['total_presupuesto'] += $totalCost;
                }
            }
        });

        $requestsQuery = NoteRequest::where('management_id', $latestManagement->id)
            ->with(['materials' => function ($query) {
                $query->select('materials.id', 'materials.group_id');
            }, 'materials.group' => function ($query) {
                $query->select('groups.id', 'groups.name_group', 'groups.code');
            }])
            ->where('state', 'Aceptado');

        if ($startDate && $endDate) {
            $requestsQuery->whereBetween('received_on_date', [$startDate, $endDate]);
        }

        $requestsQuery->chunk(100, function ($requests) use (&$groupTotals) {
            foreach ($requests as $request) {
                foreach ($request->materials as $material) {
                    $group = $material->group;
                    $groupName = $group ? $group->name_group : 'Sin grupo';
                    $deliveredQuantity = $material->pivot->delivered_quantity;

                    if (isset($groupTotals[$groupName])) {
                        $groupTotals[$groupName]['total_cantidad'] -= $deliveredQuantity;
                        $groupTotals[$groupName]['total_presupuesto'] -= $deliveredQuantity * $material->pivot->cost_unit;
                    }
                }
            }
        });

        $result = array_map(function ($groupName, $data) {
            return [
                'grupo' => $groupName,
                'codigo_grupo' => $data['codigo_grupo'],
                'total_cantidad' => $data['total_cantidad'],
                'total_presupuesto' => number_format($data['total_presupuesto'], 2)
            ];
        }, array_keys($groupTotals), $groupTotals);

        return response()->json([
            'data' => $result,
        ]);
    }

    public function consolidated_valued_physical_inventory($idManagement)
    {
        $latestManagement = Management::where('id', $idManagement)->latest('id')->first();
        $previousManagement = Management::where('id', '<', $latestManagement->id)->latest('id')->first();

        $latestManagementId = $latestManagement ? $latestManagement->id : null;
        $previousManagementId = $previousManagement ? $previousManagement->id : null;

        $latestGroups = Group::whereHas('materials')
            ->with(['materials.noteRequests' => function ($query) use ($latestManagementId) {
                $query->where('management_id', $latestManagementId);
            }, 'materials.noteEntries' => function ($query) use ($latestManagementId) {
                $query->where('management_id', $latestManagementId)->where('state', 'Aceptado');
            }])
            ->get()
            ->map(function ($group) {
                $totalSum = 0;
                $totalCost = 0;

                foreach ($group->materials as $material) {
                    $deliveredSum = $material->noteRequests->sum('pivot.delivered_quantity') ?: 0;
                    $entrySum = $material->noteEntries->sum('pivot.amount_entries') ?: 0;
                    $averageCost = $material->average_cost ?: 0;

                    if ($entrySum > 0 && $averageCost > 0) {
                        $totalMaterialCost = ($entrySum - $deliveredSum) * $averageCost;
                    } else {
                        $totalMaterialCost = 0;
                    }

                    $totalSum += ($entrySum - $deliveredSum);
                    $totalCost += $totalMaterialCost;
                }

                return [
                    'group_id' => $group->id,
                    'code' => $group->code,
                    'name_group' => $group->name_group,
                    'latest_total_sum' => $totalSum,
                    'latest_total_cost' => number_format($totalCost, 2)
                ];
            });

        $previousGroups = $previousManagement
            ? Group::whereHas('materials')
            ->with(['materials.noteRequests' => function ($query) use ($previousManagementId) {
                $query->where('management_id', $previousManagementId);
            }, 'materials.noteEntries' => function ($query) use ($previousManagementId) {
                $query->where('management_id', $previousManagementId);
            }])
            ->get()
            ->map(function ($group) {
                $totalSum = 0;
                $totalCost = 0;

                foreach ($group->materials as $material) {
                    $deliveredSum = $material->noteRequests->sum('pivot.delivered_quantity') ?: 0;
                    $entrySum = $material->noteEntries->sum('pivot.amount_entries') ?: 0;
                    $averageCost = $material->average_cost ?: 0;

                    if ($entrySum > 0 && $averageCost > 0) {
                        $totalMaterialCost = ($entrySum - $deliveredSum) * $averageCost;
                    } else {
                        $totalMaterialCost = 0;
                    }

                    $totalSum += ($entrySum - $deliveredSum);
                    $totalCost += $totalMaterialCost;
                }

                return [
                    'group_id' => $group->id,
                    'code' => $group->code,
                    'name_group' => $group->name_group,
                    'previous_total_sum' => $totalSum,
                    'previous_total_cost' => number_format($totalCost, 2),
                ];
            })
            : collect();


        $latestRequests = DB::select('
            SELECT tmp2.group_id, 
                   SUM(tmp2.suma_entregado) AS total, 
                   SUM(tmp2.suma_entregado * tmp.promedio) AS total_cost
            FROM (
                SELECT em.material_id, 
                       AVG(em.cost_unit) AS promedio
                FROM store.note_entries ne 
                JOIN store.entries_material em ON ne.id = em.note_id
                WHERE ne.management_id = ?
                GROUP BY em.material_id
            ) AS tmp
            JOIN (
                SELECT m.group_id, 
                       m.id, 
                       SUM(rm.delivered_quantity) AS suma_entregado
                FROM store.note_requests nr 
                JOIN store.request_material rm ON nr.id = rm.note_id
                JOIN store.materials m ON rm.material_id = m.id
                WHERE nr.management_id = ?
                GROUP BY m.group_id, m.id
            ) AS tmp2 ON tmp.material_id = tmp2.id
            GROUP BY tmp2.group_id
        ', [$latestManagementId, $latestManagementId]);

        $requestMap = collect($latestRequests)->mapWithKeys(function ($item) {
            return [$item->group_id => [
                'latest_request_sum' => $item->total,
                'latest_request_cost' => number_format($item->total_cost, 2)
            ]];
        });

        $result = $latestGroups->map(function ($latestGroup) use ($requestMap, $previousGroups) {
            $previousGroup = $previousGroups->firstWhere('group_id', $latestGroup['group_id']) ?? [
                'previous_total_sum' => 0,
                'previous_total_cost' => 0
            ];

            $requestData = $requestMap->get($latestGroup['group_id'], [
                'latest_request_sum' => 0,
                'latest_request_cost' => '0.00'
            ]);

            return array_merge($latestGroup, $previousGroup, $requestData);
        });

        return response()->json($result);
    }


    public function print_consolidated_valued_physical_inventory($idManagement)
    {

        $latestManagement = Management::where('id', $idManagement)->latest('id')->first();
        $previousManagement = Management::where('id', '<', $latestManagement->id)->latest('id')->first();

        $groupTotals = [];

        $notesQuery = Note_Entrie::where('management_id', $latestManagement->id)
            ->where('state', 'Aceptado')
            ->with(['materials' => function ($query) {
                $query->select('materials.id', 'materials.group_id');
            }, 'materials.group' => function ($query) {
                $query->select('groups.id', 'groups.name_group', 'groups.code');
            }]);

        $notesQuery->chunk(100, function ($notes) use (&$groupTotals) {
            foreach ($notes as $note) {
                foreach ($note->materials as $material) {
                    $group = $material->group;
                    $groupName = $group ? $group->name_group : 'Sin grupo';
                    $groupCode = $group ? $group->code : null;

                    $amountEntries = $material->pivot->amount_entries;
                    $amountRequest = $material->pivot->request;
                    $costUnit = $material->pivot->cost_unit;
                    $totalCost = $amountRequest * $costUnit;

                    if (!isset($groupTotals[$groupName])) {
                        $groupTotals[$groupName] = [
                            'codigo_grupo' => $groupCode,
                            'total_cantidad' => 0,
                            'total_presupuesto' => 0,
                            'cantidad_entregada' => 0,
                            'suma_cost_detail' => 0,
                            'total_cantidad_anterior' => 0,
                            'total_presupuesto_anterior' => 0
                        ];
                    }

                    $groupTotals[$groupName]['total_cantidad'] += $amountEntries;
                    $groupTotals[$groupName]['total_presupuesto'] += $totalCost;
                }
            }
        });

        if ($previousManagement) {
            $previousNotesQuery = Note_Entrie::where('management_id', $previousManagement->id)
                ->where('state', 'Aceptado')
                ->with(['materials' => function ($query) {
                    $query->select('materials.id', 'materials.group_id');
                }, 'materials.group' => function ($query) {
                    $query->select('groups.id', 'groups.name_group', 'groups.code');
                }]);

            $previousNotesQuery->chunk(100, function ($notes) use (&$groupTotals) {
                foreach ($notes as $note) {
                    foreach ($note->materials as $material) {
                        $group = $material->group;
                        $groupName = $group ? $group->name_group : 'Sin grupo';
                        $groupCode = $group ? $group->code : null;

                        $amountRequest = $material->pivot->request;
                        $costUnit = $material->pivot->cost_unit;
                        $totalCost = $amountRequest * $costUnit;

                        if (!isset($groupTotals[$groupName])) {
                            $groupTotals[$groupName] = [
                                'codigo_grupo' => $groupCode,
                                'total_cantidad' => 0,
                                'total_presupuesto' => 0,
                                'cantidad_entregada' => 0,
                                'suma_cost_detail' => 0,
                                'total_cantidad_anterior' => 0,
                                'total_presupuesto_anterior' => 0
                            ];
                        }

                        $groupTotals[$groupName]['total_cantidad_anterior'] += $amountRequest;
                        $groupTotals[$groupName]['total_presupuesto_anterior'] += $totalCost;
                    }
                }
            });
        }

        $requestsQuery = NoteRequest::where('management_id', $latestManagement->id)
            ->with(['materials' => function ($query) {
                $query->select('materials.id', 'materials.group_id');
            }, 'materials.group' => function ($query) {
                $query->select('groups.id', 'groups.name_group', 'groups.code');
            }])
            ->where('state', 'Aceptado');

        $requestsQuery->chunk(100, function ($requests) use (&$groupTotals) {
            foreach ($requests as $request) {
                foreach ($request->materials as $material) {
                    $group = $material->group;
                    $groupName = $group ? $group->name_group : 'Sin grupo';
                    $deliveredQuantity = $material->pivot->delivered_quantity ?? 0;

                    if (!isset($groupTotals[$groupName])) {
                        $groupTotals[$groupName] = [
                            'codigo_grupo' => $group ? $group->code : null,
                            'total_cantidad' => 0,
                            'total_presupuesto' => 0,
                            'cantidad_entregada' => 0,
                            'suma_cost_detail' => 0,
                            'total_cantidad_anterior' => 0,
                            'total_presupuesto_anterior' => 0
                        ];
                    }

                    $groupTotals[$groupName]['total_cantidad'] -= $deliveredQuantity;
                    $groupTotals[$groupName]['total_presupuesto'] -= $deliveredQuantity * $material->pivot->cost_unit;
                    $groupTotals[$groupName]['cantidad_entregada'] += $deliveredQuantity;

                    $costDetails = $material->pivot->costDetails ?? 0;
                    $groupTotals[$groupName]['suma_cost_detail'] += $costDetails;
                }
            }
        });

        $result = array_map(function ($groupName, $data) {
            return [
                'grupo' => $groupName,
                'codigo_grupo' => $data['codigo_grupo'],
                'total_cantidad_anterior' => $data['total_cantidad_anterior'],
                'total_presupuesto_anterior' => number_format($data['total_presupuesto_anterior'], 2),
                'total_cantidad' => $data['total_cantidad'],
                'total_presupuesto' => number_format($data['total_presupuesto'], 2),
                'cantidad_entregada' => $data['cantidad_entregada'],
                'suma_cost_detail' => number_format($data['suma_cost_detail'], 2)
            ];
        }, array_keys($groupTotals), $groupTotals);


        $data = [
            'title' => 'INVENTARIO FISICO VALORADO CONSOLIDADO',
            'results' => $result
        ];


        $pdf = Pdf::loadView('ConsolidatedValuedPhysicalInventory.ConsolidatedValued', $data)->setPaper('letter', 'landscape');
        return $pdf->stream('Inventario Fisico Valorado Consolidado.pdf');
    }

    public function management_closure()
    {
        try {
            DB::transaction(function () {
                $latestManagement = Management::latest('id')->first();

                $pendingNotes = NoteRequest::where('management_id', $latestManagement->id)
                    ->where('state', 'like', 'En Revision')
                    ->exists();

                $pendingNotes2 = Note_Entrie::where('management_id', $latestManagement->id)
                    ->where('state', 'like', 'En Revision')
                    ->exists();

                if ($pendingNotes || $pendingNotes2) {
                    throw new \Exception('No se puede cerrar la gestión. Existen notas de solicitud pendientes en estado "En Revisión".');
                }

                $latestManagement->state = "Cerrado";
                $latestManagement->close_date = now()->format('Y-m-d');
                $latestManagement->save();
                $newManagement = Management::create([
                    'period_name' => now()->format('Y'),
                    'start_date' => now()->format('Y-m-d'),
                    'state' => 'Abierto',
                ]);

                $notesWithBalances = Note_Entrie::where('management_id', $latestManagement->id)
                    ->whereHas('materials', function ($query) {
                        $query->where('request', '>', 0);
                    })
                    ->with('materials')
                    ->get();

                foreach ($notesWithBalances as $note) {
                    $newNote = Note_Entrie::create([
                        'number_note' => $this->generateNoteNumber(),
                        'invoice_number' => $note->invoice_number,
                        'delivery_date' => $note->delivery_date,
                        'state' => 'Aceptado',
                        'invoice_auth' => $note->invoice_auth,
                        'user_register' => $note->user_register,
                        'observation' => 'Saldo trasladado',
                        'type_id' => $note->type_id,
                        'suppliers_id' => $note->suppliers_id,
                        'name_supplier' => 'Generado por Cierre de Gestión',
                        'management_id' => $newManagement->id,
                    ]);

                    foreach ($note->materials as $material) {
                        if ($material->pivot->request > 0) {
                            $newNote->materials()->attach($material->id, [
                                'amount_entries' => $material->pivot->request,
                                'request' => $material->pivot->request,
                                'cost_unit' => $material->pivot->cost_unit,
                                'cost_total' => $material->pivot->request * $material->pivot->cost_unit,
                                'name_material' => $material->pivot->name_material,
                                'delivery_date_entry' => now()->format('Y-m-d'),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            });

            return response()->json(['message' => 'Gestión cerrada y nueva gestión creada exitosamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }


    private function generateNoteNumber()
    {
        $lastNote = Note_Entrie::orderBy('number_note', 'desc')->first();
        return $lastNote ? $lastNote->number_note + 1 : 1;
    }

    public function calculateMaterialCost(Material $material)
    {
        $latestManagement = Management::latest('id')->first();
        $previousManagement = Management::where('id', '<', $latestManagement->id)
            ->orderBy('id', 'desc')
            ->first();
        if (!$previousManagement) {
            return 0;
        }

        $averageCostUnit = $material->noteEntries()
            ->where('management_id', $previousManagement->id)
            ->avg('entries_material.cost_unit');

        return ($averageCostUnit);
    }

    public function list_mangement()
    {
        $management = Management::all();
        return ($management);
    }


    public function consolidated_inventory($idManagement)
    {
        $latestManagement = Management::where('id', $idManagement)->latest('id')->first();
        $previousManagement = Management::where('id', '<', $latestManagement->id)->latest('id')->first();

        $groupTotals = [];

        $notesQuery = Note_Entrie::where('management_id', $latestManagement->id)
            ->where('state', 'Aceptado')
            ->with(['materials' => function ($query) {
                $query->select('materials.id', 'materials.group_id');
            }, 'materials.group' => function ($query) {
                $query->select('groups.id', 'groups.name_group', 'groups.code');
            }]);

        $notesQuery->chunk(100, function ($notes) use (&$groupTotals) {
            foreach ($notes as $note) {
                foreach ($note->materials as $material) {
                    $group = $material->group;
                    $groupName = $group ? $group->name_group : 'Sin grupo';
                    $groupCode = $group ? $group->code : null;

                    $amountEntries = $material->pivot->amount_entries;
                    $amountRequest = $material->pivot->request;
                    $costUnit = $material->pivot->cost_unit;
                    $totalCost = $amountRequest * $costUnit;

                    if (!isset($groupTotals[$groupName])) {
                        $groupTotals[$groupName] = [
                            'codigo_grupo' => $groupCode,
                            'total_cantidad' => 0,
                            'total_presupuesto' => 0,
                            'cantidad_entregada' => 0,
                            'suma_cost_detail' => 0,
                            'total_cantidad_anterior' => 0,
                            'total_presupuesto_anterior' => 0
                        ];
                    }

                    $groupTotals[$groupName]['total_cantidad'] += $amountEntries;
                    $groupTotals[$groupName]['total_presupuesto'] += $totalCost;
                }
            }
        });

        if ($previousManagement) {
            $previousNotesQuery = Note_Entrie::where('management_id', $previousManagement->id)
                ->where('state', 'Aceptado')
                ->with(['materials' => function ($query) {
                    $query->select('materials.id', 'materials.group_id');
                }, 'materials.group' => function ($query) {
                    $query->select('groups.id', 'groups.name_group', 'groups.code');
                }]);

            $previousNotesQuery->chunk(100, function ($notes) use (&$groupTotals) {
                foreach ($notes as $note) {
                    foreach ($note->materials as $material) {
                        $group = $material->group;
                        $groupName = $group ? $group->name_group : 'Sin grupo';
                        $groupCode = $group ? $group->code : null;

                        $amountRequest = $material->pivot->request;
                        $costUnit = $material->pivot->cost_unit;
                        $totalCost = $amountRequest * $costUnit;

                        if (!isset($groupTotals[$groupName])) {
                            $groupTotals[$groupName] = [
                                'codigo_grupo' => $groupCode,
                                'total_cantidad' => 0,
                                'total_presupuesto' => 0,
                                'cantidad_entregada' => 0,
                                'suma_cost_detail' => 0,
                                'total_cantidad_anterior' => 0,
                                'total_presupuesto_anterior' => 0
                            ];
                        }

                        $groupTotals[$groupName]['total_cantidad_anterior'] += $amountRequest;
                        $groupTotals[$groupName]['total_presupuesto_anterior'] += $totalCost;
                    }
                }
            });
        }

        $requestsQuery = NoteRequest::where('management_id', $latestManagement->id)
            ->with(['materials' => function ($query) {
                $query->select('materials.id', 'materials.group_id');
            }, 'materials.group' => function ($query) {
                $query->select('groups.id', 'groups.name_group', 'groups.code');
            }])
            ->where('state', 'Aceptado');

        $requestsQuery->chunk(100, function ($requests) use (&$groupTotals) {
            foreach ($requests as $request) {
                foreach ($request->materials as $material) {
                    $group = $material->group;
                    $groupName = $group ? $group->name_group : 'Sin grupo';
                    $deliveredQuantity = $material->pivot->delivered_quantity ?? 0;

                    if (!isset($groupTotals[$groupName])) {
                        $groupTotals[$groupName] = [
                            'codigo_grupo' => $group ? $group->code : null,
                            'total_cantidad' => 0,
                            'total_presupuesto' => 0,
                            'cantidad_entregada' => 0,
                            'suma_cost_detail' => 0,
                            'total_cantidad_anterior' => 0,
                            'total_presupuesto_anterior' => 0
                        ];
                    }

                    $groupTotals[$groupName]['total_cantidad'] -= $deliveredQuantity;
                    $groupTotals[$groupName]['total_presupuesto'] -= $deliveredQuantity * $material->pivot->cost_unit;
                    $groupTotals[$groupName]['cantidad_entregada'] += $deliveredQuantity;

                    $costDetails = $material->pivot->costDetails ?? 0;
                    $groupTotals[$groupName]['suma_cost_detail'] += $costDetails;
                }
            }
        });

        $result = array_map(function ($groupName, $data) {
            return [
                'grupo' => $groupName,
                'codigo_grupo' => $data['codigo_grupo'],
                'total_cantidad_anterior' => $data['total_cantidad_anterior'],
                'total_presupuesto_anterior' => number_format($data['total_presupuesto_anterior'], 2),
                'total_cantidad' => $data['total_cantidad'],
                'total_presupuesto' => number_format($data['total_presupuesto'], 2),
                'cantidad_entregada' => $data['cantidad_entregada'],
                'suma_cost_detail' => number_format($data['suma_cost_detail'], 2)
            ];
        }, array_keys($groupTotals), $groupTotals);

        return $result;
    }




    // public function consolidated_inventory($idManagement)
    // {
    //     $latestManagement = Management::where('id', $idManagement)->latest('id')->first();
    //     $previousManagement = Management::where('id', '<', $latestManagement->id)->latest('id')->first();


    //     $notesQuery = Note_Entrie::where('management_id', $latestManagement->id)
    //         ->where('state', 'Aceptado')
    //         ->with(['materials' => function ($query) {
    //             $query->select('materials.id', 'materials.group_id');
    //         }, 'materials.group' => function ($query) {
    //             $query->select('groups.id', 'groups.name_group', 'groups.code');
    //         }]);

    //     $groupTotals = [];

    //     $notesQuery->chunk(100, function ($notes) use (&$groupTotals) {
    //         foreach ($notes as $note) {
    //             foreach ($note->materials as $material) {
    //                 $group = $material->group;
    //                 $groupName = $group ? $group->name_group : 'Sin grupo';
    //                 $groupCode = $group ? $group->code : null;

    //                 $amountEntries = $material->pivot->amount_entries;
    //                 $amountRequest = $material->pivot->request;
    //                 $costUnit = $material->pivot->cost_unit;
    //                 $totalCost = $amountRequest * $costUnit;

    //                 if (!isset($groupTotals[$groupName])) {
    //                     $groupTotals[$groupName] = [
    //                         'codigo_grupo' => $groupCode,
    //                         'total_cantidad' => 0,
    //                         'total_presupuesto' => 0,
    //                         'cantidad_entregada' => 0,
    //                         'suma_cost_detail' => 0
    //                     ];
    //                 }

    //                 $groupTotals[$groupName]['total_cantidad'] += $amountEntries;
    //                 $groupTotals[$groupName]['total_presupuesto'] += $totalCost;
    //             }
    //         }
    //     });

    //     $requestsQuery = NoteRequest::where('management_id', $latestManagement->id)
    //         ->with(['materials' => function ($query) {
    //             $query->select('materials.id', 'materials.group_id');
    //         }, 'materials.group' => function ($query) {
    //             $query->select('groups.id', 'groups.name_group', 'groups.code');
    //         }])
    //         ->where('state', 'Aceptado');

    //     $requestsQuery->chunk(100, function ($requests) use (&$groupTotals) {
    //         foreach ($requests as $request) {
    //             foreach ($request->materials as $material) {
    //                 $group = $material->group;
    //                 $groupName = $group ? $group->name_group : 'Sin grupo';
    //                 $deliveredQuantity = $material->pivot->delivered_quantity ?? 0;

    //                 if (!isset($groupTotals[$groupName])) {
    //                     $groupTotals[$groupName] = [
    //                         'codigo_grupo' => $group ? $group->code : null,
    //                         'total_cantidad' => 0,
    //                         'total_presupuesto' => 0,
    //                         'cantidad_entregada' => 0,
    //                         'suma_cost_detail' => 0
    //                     ];
    //                 }

    //                 $groupTotals[$groupName]['total_cantidad'] -= $deliveredQuantity;
    //                 $groupTotals[$groupName]['total_presupuesto'] -= $deliveredQuantity * $material->pivot->cost_unit;
    //                 $groupTotals[$groupName]['cantidad_entregada'] += $deliveredQuantity;
    //                 $costDetails = $material->pivot->costDetails ?? 0;
    //                 $groupTotals[$groupName]['suma_cost_detail'] += $costDetails;
    //             }
    //         }
    //     });

    //     $result = array_map(function ($groupName, $data) {
    //         return [
    //             'grupo' => $groupName,
    //             'codigo_grupo' => $data['codigo_grupo'],
    //             'total_cantidad' => $data['total_cantidad'],
    //             'total_presupuesto' => number_format($data['total_presupuesto'], 2),
    //             'cantidad_entregada' => $data['cantidad_entregada'],
    //             'suma_cost_detail' => number_format($data['suma_cost_detail'], 2)
    //         ];
    //     }, array_keys($groupTotals), $groupTotals);

    //     return $result;
    // }
}
