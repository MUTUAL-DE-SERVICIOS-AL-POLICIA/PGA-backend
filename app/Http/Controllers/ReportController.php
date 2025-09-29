<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Group;
use App\Models\Management;
use App\Models\Material;
use App\Models\Note_Entrie;
use App\Models\NoteRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\KardexExport;
use App\Exports\ValuedPhysicalExport;
use App\Exports\ConsolidatedValuedPhysicalInventoryExport;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function kardex($materialId)
    {
        try {
            $latestManagement = Management::latest('id')->first();

            if ($latestManagement) {
                $material_balance = Material::findOrFail($materialId);
                $balance = $material_balance->noteEntries()
                    ->where('management_id', $latestManagement->id)
                    ->where('observation', 'Saldo trasladado')
                    ->orderBy('number_note', 'asc')
                    ->get();

                $amountEntriesTotal = 0;
                $costTotalTotal = 0;

                if ($balance) {
                    foreach ($balance as $entry) {
                        $amountEntriesTotal += $entry->pivot->amount_entries;
                        $costTotalTotal += $entry->pivot->cost_total;
                    }
                }
            }
            $startDate = request()->query('start_date');
            $endDate = Carbon::parse(request()->query('end_date'))->addDay()->toDateString();
            $latestManagement = Management::latest('id')->first();
            $material = Material::findOrFail($materialId);
            $group_material = $material->group()->first()->name_group;

            $kardex = [];
            $stock = 0;
            $max_total = 0;
            $fifoQueue = [];

            $entries = $material->noteEntries()
                ->where('management_id', $latestManagement->id)
                ->where('delivery_date', '<=', $endDate)
                ->orderBy('number_note', 'asc')
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
                    'description' => 'Nota de Entrada # ' . $entry->number_note,
                    'quantity' => $entry->pivot->amount_entries,
                    'cost_unit' => $entry->pivot->cost_unit,
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

            usort($movements, fn($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));

            foreach ($movements as $movement) {
                $fecha = date('Y-m-d', strtotime($movement['date']));

                if ($movement['type'] === 'entry') {
                    $fifoQueue[] = [
                        'quantity' => $movement['quantity'],
                        'cost_unit' => $movement['cost_unit'],
                    ];
                    $stock += $movement['quantity'];
                    $importeEntrada = $movement['quantity'] * $movement['cost_unit'];
                    $max_total += $importeEntrada;

                    if (!$startDate || $fecha >= $startDate) {
                        $kardex[] = [
                            'date' => $fecha,
                            'description' => $movement['description'],
                            'entradas' => $movement['quantity'],
                            'salidas' => 0,
                            'stock_fisico' => $stock,
                            'cost_unit' => round($movement['cost_unit'], 2),
                            'importe_entrada' => round($importeEntrada, 2),
                            'importe_salida' => 0,
                            'importe_saldo' => round($max_total, 2),
                        ];
                    }
                } else {
                    $quantityToDeliver = $movement['quantity'];
                    $importeSalidaTotal = 0;

                    while ($quantityToDeliver > 0 && count($fifoQueue) > 0) {
                        $fifoItem = array_shift($fifoQueue);
                        $usedQuantity = min($quantityToDeliver, $fifoItem['quantity']);
                        $costUnit = $fifoItem['cost_unit'];
                        $importeSalida = $usedQuantity * $costUnit;
                        $importeSalidaTotal += $importeSalida;
                        $max_total -= $importeSalida;
                        $stock -= $usedQuantity;

                        if (!$startDate || $fecha >= $startDate) {
                            $kardex[] = [
                                'date' => $fecha,
                                'description' => $movement['description'],
                                'entradas' => 0,
                                'salidas' => $usedQuantity,
                                'stock_fisico' => $stock,
                                'cost_unit' => round($costUnit, 2),
                                'importe_entrada' => 0,
                                'importe_salida' => round($importeSalida, 2),
                                'importe_saldo' => round($max_total, 2),
                            ];
                        }

                        $quantityToDeliver -= $usedQuantity;

                        if ($fifoItem['quantity'] > $usedQuantity) {
                            $fifoItem['quantity'] -= $usedQuantity;
                            array_unshift($fifoQueue, $fifoItem);
                        }
                    }
                }
            }
            $totalEntradas = collect($kardex)->sum('entradas');
            $totalSalidas = collect($kardex)->sum('salidas');
            $totalStock = collect($kardex)->last()['stock_fisico'] ?? 0;
            $totalImporteEntrada = collect($kardex)->sum('importe_entrada');
            $totalImporteSalida = collect($kardex)->sum('importe_salida');
            $totalImporteSaldo = collect($kardex)->last()['importe_saldo'] ?? 0;

            $totales = [
                'entradas' => $totalEntradas,
                'salidas' => $totalSalidas,
                'stock_fisico' => $totalStock,
                'importe_entrada' => round($totalImporteEntrada, 2),
                'importe_salida' => round($totalImporteSalida, 2),
                'importe_saldo' => round($totalImporteSaldo, 2),
            ];

            return response()->json([
                'code_material' => $material->code_material,
                'description' => $material->description,
                'unit_material' => $material->unit_material,
                'group' => strtoupper($group_material),
                'kardex_de_existencia' => $kardex,
                'totales' => $totales,
                'balance_amount_entries' => $amountEntriesTotal,
                'balance_cost_total' => $costTotalTotal
            ]);
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => 'No se pudo generar el Kardex'], 500);
        }
    }

    public function print_kardex_excel($materialId)
    {
        try {
            $latestManagement = Management::latest('id')->first();

            if ($latestManagement) {
                $material_balance = Material::findOrFail($materialId);
                $balance = $material_balance->noteEntries()
                    ->where('management_id', $latestManagement->id)
                    ->where('observation', 'Saldo trasladado')
                    ->orderBy('number_note', 'asc')
                    ->get();

                $amountEntriesTotal = 0;
                $costTotalTotal = 0;

                if ($balance) {
                    foreach ($balance as $entry) {
                        $amountEntriesTotal += $entry->pivot->amount_entries;
                        $costTotalTotal += $entry->pivot->cost_total;
                    }
                }
            }


            $startDate = request()->query('start_date');
            $endDate = Carbon::parse(request()->query('end_date'))->addDay()->toDateString();
            $latestManagement = Management::latest('id')->first();
            $material = Material::findOrFail($materialId);
            $group_material = $material->group()->first()->name_group;

            $kardex = [];
            $stock = 0;
            $max_total = 0;
            $fifoQueue = [];

            $entries = $material->noteEntries()
                ->where('management_id', $latestManagement->id)
                ->where('delivery_date', '<=', $endDate)
                ->orderBy('number_note', 'asc')
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
                    'cost_unit' => $entry->pivot->cost_unit,
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

            usort($movements, fn($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));
            foreach ($movements as $movement) {
                $fecha = date('Y-m-d', strtotime($movement['date']));

                if ($movement['type'] === 'entry') {
                    $fifoQueue[] = [
                        'quantity' => $movement['quantity'],
                        'cost_unit' => $movement['cost_unit'],
                    ];
                    $stock += $movement['quantity'];
                    $importeEntrada = $movement['quantity'] * $movement['cost_unit'];
                    $max_total += $importeEntrada;

                    if (!$startDate || $fecha >= $startDate) {
                        $kardex[] = [
                            'date' => $fecha,
                            'description' => $movement['description'],
                            'entradas' => $movement['quantity'],
                            'salidas' => 0,
                            'stock_fisico' => $stock,
                            'cost_unit' => round($movement['cost_unit'], 2),
                            'importe_entrada' => round($importeEntrada, 2),
                            'importe_salida' => 0,
                            'importe_saldo' => round($max_total, 2),
                        ];
                    }
                } else {
                    $quantityToDeliver = $movement['quantity'];
                    $importeSalidaTotal = 0;

                    while ($quantityToDeliver > 0 && count($fifoQueue) > 0) {
                        $fifoItem = array_shift($fifoQueue);
                        $usedQuantity = min($quantityToDeliver, $fifoItem['quantity']);
                        $costUnit = $fifoItem['cost_unit'];
                        $importeSalida = $usedQuantity * $costUnit;
                        $importeSalidaTotal += $importeSalida;
                        $max_total -= $importeSalida;
                        $stock -= $usedQuantity;

                        if (!$startDate || $fecha >= $startDate) {
                            $kardex[] = [
                                'date' => $fecha,
                                'description' => $movement['description'],
                                'entradas' => 0,
                                'salidas' => $usedQuantity,
                                'stock_fisico' => $stock,
                                'cost_unit' => round($costUnit, 2),
                                'importe_entrada' => 0,
                                'importe_salida' => round($importeSalida, 2),
                                'importe_saldo' => round($max_total, 2),
                            ];
                        }

                        $quantityToDeliver -= $usedQuantity;

                        if ($fifoItem['quantity'] > $usedQuantity) {
                            $fifoItem['quantity'] -= $usedQuantity;
                            array_unshift($fifoQueue, $fifoItem);
                        }
                    }
                }
            }
            $totalEntradas = collect($kardex)->sum('entradas');
            $totalSalidas = collect($kardex)->sum('salidas');
            $totalStock = collect($kardex)->last()['stock_fisico'] ?? 0;
            $totalImporteEntrada = collect($kardex)->sum('importe_entrada');
            $totalImporteSalida = collect($kardex)->sum('importe_salida');
            $totalImporteSaldo = collect($kardex)->last()['importe_saldo'] ?? 0;

            $totales = [
                'entradas' => $totalEntradas,
                'salidas' => $totalSalidas,
                'stock_fisico' => $totalStock,
                'importe_entrada' => round($totalImporteEntrada, 2),
                'importe_salida' => round($totalImporteSalida, 2),
                'importe_saldo' => round($totalImporteSaldo, 2),
            ];

            // Download Excel file
            return Excel::download(new KardexExport($kardex, $totales), 'Kardex_Existencias.xlsx');
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => 'No se pudo generar el Kardex'], 500);
        }
    }

    public function print_kardex($materialId)
    {
        try {

            $latestManagement = Management::latest('id')->first();

            if ($latestManagement) {
                $material_balance = Material::findOrFail($materialId);
                $balance = $material_balance->noteEntries()
                    ->where('management_id', $latestManagement->id)
                    ->where('observation', 'Saldo trasladado')
                    ->orderBy('number_note', 'asc')
                    ->get();

                $amountEntriesTotal = 0;
                $costTotalTotal = 0;

                if ($balance) {
                    foreach ($balance as $entry) {
                        $amountEntriesTotal += $entry->pivot->amount_entries;
                        $costTotalTotal += $entry->pivot->cost_total;
                    }
                }
            }
            $startDate = request()->query('start_date');
            $endDate = Carbon::parse(request()->query('end_date'))->addDay()->toDateString();
            $dateof = request()->query('end_date');
            $latestManagement = Management::latest('id')->first();
            $material = Material::findOrFail($materialId);
            $group_material = $material->group()->first()->name_group;

            $kardex = [];
            $stock = 0;
            $max_total = 0;
            $fifoQueue = [];

            $entries = $material->noteEntries()
                ->where('management_id', $latestManagement->id)
                ->where('delivery_date', '<=', $endDate)
                ->orderBy('number_note', 'asc')
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
                    'cost_unit' => $entry->pivot->cost_unit,
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

            usort($movements, fn($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));

            foreach ($movements as $movement) {
                $fecha = date('Y-m-d', strtotime($movement['date']));

                if ($movement['type'] === 'entry') {
                    $fifoQueue[] = [
                        'quantity' => $movement['quantity'],
                        'cost_unit' => $movement['cost_unit'],
                    ];
                    $stock += $movement['quantity'];
                    $importeEntrada = $movement['quantity'] * $movement['cost_unit'];
                    $max_total += $importeEntrada;

                    if (!$startDate || $fecha >= $startDate) {
                        $kardex[] = [
                            'date' => $fecha,
                            'description' => $movement['description'],
                            'entradas' => $movement['quantity'],
                            'salidas' => 0,
                            'stock_fisico' => $stock,
                            'cost_unit' => round($movement['cost_unit'], 2),
                            'importe_entrada' => round($importeEntrada, 2),
                            'importe_salida' => 0,
                            'importe_saldo' => round($max_total, 2),
                        ];
                    }
                } else {
                    $quantityToDeliver = $movement['quantity'];
                    $importeSalidaTotal = 0;

                    while ($quantityToDeliver > 0 && count($fifoQueue) > 0) {
                        $fifoItem = array_shift($fifoQueue);
                        $usedQuantity = min($quantityToDeliver, $fifoItem['quantity']);
                        $costUnit = $fifoItem['cost_unit'];
                        $importeSalida = $usedQuantity * $costUnit;
                        $importeSalidaTotal += $importeSalida;
                        $max_total -= $importeSalida;
                        $stock -= $usedQuantity;

                        if (!$startDate || $fecha >= $startDate) {
                            $kardex[] = [
                                'date' => $fecha,
                                'description' => $movement['description'],
                                'entradas' => 0,
                                'salidas' => $usedQuantity,
                                'stock_fisico' => $stock,
                                'cost_unit' => round($costUnit, 2),
                                'importe_entrada' => 0,
                                'importe_salida' => round($importeSalida, 2),
                                'importe_saldo' => round($max_total, 2),
                            ];
                        }

                        $quantityToDeliver -= $usedQuantity;

                        if ($fifoItem['quantity'] > $usedQuantity) {
                            $fifoItem['quantity'] -= $usedQuantity;
                            array_unshift($fifoQueue, $fifoItem);
                        }
                    }
                }
            }
            $totalEntradas = collect($kardex)->sum('entradas');
            $totalSalidas = collect($kardex)->sum('salidas');
            $totalStock = collect($kardex)->last()['stock_fisico'] ?? 0;
            $totalImporteEntrada = collect($kardex)->sum('importe_entrada');
            $totalImporteSalida = collect($kardex)->sum('importe_salida');
            $totalImporteSaldo = collect($kardex)->last()['importe_saldo'] ?? 0;

            $totales = [
                'entradas' => $totalEntradas,
                'salidas' => $totalSalidas,
                'stock_fisico' => $totalStock,
                'importe_entrada' => round($totalImporteEntrada, 2),
                'importe_salida' => round($totalImporteSalida, 2),
                'importe_saldo' => round($totalImporteSaldo, 2),
            ];

            $firtsDay = date("Y-01-01");

            $data = [
                'title' => 'KARDEX DE EXISTENCIAS',
                'code_material' => $material->code_material,
                'description' => $material->description,
                'unit_material' => $material->unit_material,
                'group' => strtoupper($group_material),
                'kardex_de_existencia' => $kardex,
                'totales' => $totales,
                'start_date' => $startDate,
                'end_date' => $dateof,
                'firtsDay'=> $firtsDay,
                'balance_amount_entries' => $amountEntriesTotal,
                'balance_cost_total' => $costTotalTotal
            ];

            $pdf = Pdf::loadView('Report_Kardex.ReportKardex', $data)->setPaper('letter', 'landscape');
            return $pdf->stream('Kardex de Existencia.pdf');
        } catch (\Exception $e) {
            logger($e->getMessage());
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
                            if ($deliveredQuantity <= 0) break;

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
        $previousManagement = Management::where('id', '<', $latestManagement->id)
            ->orderByDesc('id')
            ->first();

        $saldosAnteriores = [];

        if ($previousManagement) {
            $saldosQuery = Note_Entrie::where('management_id', $previousManagement->id)
                ->where('state', 'Aceptado')
                ->with(['materials' => function ($query) {
                    $query->select('materials.id', 'materials.description', 'materials.code_material', 'materials.group_id', 'materials.unit_material');
                }, 'materials.group' => function ($query) {
                    $query->select('groups.id', 'groups.name_group', 'groups.code');
                }]);

            $saldosQuery->chunk(100, function ($notes) use (&$saldosAnteriores) {
                foreach ($notes as $note) {
                    foreach ($note->materials as $material) {
                        $saldo = (float) $material->pivot->request;
                        if ($saldo <= 0) continue;

                        $group = $material->group;
                        $groupName = $group ? $group->name_group : 'Sin grupo';
                        $groupCode = $group ? $group->code : null;
                        $materialCode = $material->code_material;
                        $materialName = $material->description;
                        $materialUnit = $material->unit_material;
                        $costUnit = (float) $material->pivot->cost_unit;
                        $deliveryDate = $note->delivery_date;

                        if (!isset($saldosAnteriores[$groupName])) {
                            $saldosAnteriores[$groupName] = [
                                'codigo_grupo' => $groupCode,
                                'materiales' => []
                            ];
                        }

                        $saldosAnteriores[$groupName]['materiales'][] = [
                            'codigo_material' => $materialCode,
                            'nombre_material' => $materialName,
                            'unidad_material' => $materialUnit,
                            'lotes' => [
                                [
                                    'fecha_ingreso' => $deliveryDate,
                                    'cantidad_restante' => $saldo,
                                    'precio_unitario' => $costUnit,
                                    'valor_restante' => $saldo * $costUnit,
                                ]
                            ]
                        ];
                    }
                }
            });
        }
        $saldosIndexados = [];
        foreach ($saldosAnteriores as $grupo) {
            foreach ($grupo['materiales'] as $material) {
                $codigo = $material['codigo_material'];
                if (!isset($saldosIndexados[$codigo])) {
                    $saldosIndexados[$codigo] = $material;
                } else {
                    $saldosIndexados[$codigo]['lotes'] = array_merge(
                        $saldosIndexados[$codigo]['lotes'],
                        $material['lotes']
                    );
                }
            }
        }

        $result = [];
        foreach ($notesData as $groupName => $groupData) {
            $groupResult = [
                'grupo' => $groupName,
                'codigo_grupo' => $groupData['codigo_grupo'],
                'materiales' => []
            ];
            $materialesOrdenados = $groupData['materiales'];
            uksort($materialesOrdenados, function ($a, $b) {
                return strcmp($a, $b);
            });

            foreach ($materialesOrdenados as $materialCode => $materialData) {
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
                    'saldo_anterior' => $saldosIndexados[$materialCode]['lotes'] ?? [],
                    'lotes' => $materialLotes
                ];
            }

            $saldoAnteriorCantidad = 0;
            $saldoAnteriorTotal = 0;
            $entradasCantidad = 0;
            $entradasTotal = 0;
            $salidasCantidad = 0;
            $salidasTotal = 0;
            $saldoFinalCantidad = 0;
            $saldoFinalTotal = 0;

            foreach ($groupResult['materiales'] as $material) {
                foreach ($material['saldo_anterior'] as $lote) {
                    $saldoAnteriorCantidad += $lote['cantidad_restante'] ?? 0;
                    $saldoAnteriorTotal += $lote['valor_restante'] ?? 0;
                }
                foreach ($material['lotes'] as $lote) {
                    $entradasCantidad += $lote['cantidad_inicial'] ?? 0;
                    $entradasTotal += $lote['cantidad_1'] ?? 0;
                    $salidasCantidad += ($lote['cantidad_inicial'] - $lote['cantidad_restante']) ?? 0;
                    $salidasTotal += $lote['cantidad_2'] ?? 0;
                    $saldoFinalCantidad += $lote['cantidad_restante'] ?? 0;
                    $saldoFinalTotal += $lote['cantidad_3'] ?? 0;
                }
            }

            $groupResult['resumen'] = [
                'saldo_anterior_cantidad' => $saldoAnteriorCantidad,
                'saldo_anterior_total' => $saldoAnteriorTotal,
                'entradas_cantidad' => $entradasCantidad,
                'entradas_total' => $entradasTotal,
                'salidas_cantidad' => $salidasCantidad,
                'salidas_total' => $salidasTotal,
                'saldo_final_cantidad' => $saldoFinalCantidad,
                'saldo_final_total' => $saldoFinalTotal,
            ];

            if (!empty($groupResult['materiales'])) {
                $result[] = $groupResult;
            }
        }

        $note = Note_Entrie::getFirstNoteOfYear();
        $formattedDate = $note ? Note_Entrie::formatDate($note->delivery_date) : null;

        return response()->json([
            'date_note' => $formattedDate,
            'data' => $result
        ]);
    }

    public function exportValuedInventoryExcel(Request $request)
    {
        $jsonData = $this->ValuedPhysical($request)->getData(true);
        return Excel::download(new ValuedPhysicalExport($jsonData['data']), 'inventario_valorado.xlsx');
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
                            if ($deliveredQuantity <= 0) break;

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

        // Obtener saldos anteriores
        $previousManagement = Management::where('id', '<', $latestManagement->id)
            ->orderByDesc('id')
            ->first();

        $saldosAnteriores = [];

        if ($previousManagement) {
            $saldosQuery = Note_Entrie::where('management_id', $previousManagement->id)
                ->where('state', 'Aceptado')
                ->with(['materials' => function ($query) {
                    $query->select('materials.id', 'materials.description', 'materials.code_material', 'materials.group_id', 'materials.unit_material');
                }, 'materials.group' => function ($query) {
                    $query->select('groups.id', 'groups.name_group', 'groups.code');
                }]);

            $saldosQuery->chunk(100, function ($notes) use (&$saldosAnteriores) {
                foreach ($notes as $note) {
                    foreach ($note->materials as $material) {
                        $saldo = (float) $material->pivot->request;
                        if ($saldo <= 0) continue;

                        $group = $material->group;
                        $groupName = $group ? $group->name_group : 'Sin grupo';
                        $groupCode = $group ? $group->code : null;
                        $materialCode = $material->code_material;
                        $materialName = $material->description;
                        $materialUnit = $material->unit_material;
                        $costUnit = (float) $material->pivot->cost_unit;
                        $deliveryDate = $note->delivery_date;

                        if (!isset($saldosAnteriores[$groupName])) {
                            $saldosAnteriores[$groupName] = [
                                'codigo_grupo' => $groupCode,
                                'materiales' => []
                            ];
                        }

                        $saldosAnteriores[$groupName]['materiales'][] = [
                            'codigo_material' => $materialCode,
                            'nombre_material' => $materialName,
                            'unidad_material' => $materialUnit,
                            'lotes' => [
                                [
                                    'fecha_ingreso' => $deliveryDate,
                                    'cantidad_restante' => $saldo,
                                    'precio_unitario' => $costUnit,
                                    'valor_restante' => $saldo * $costUnit,
                                ]
                            ]
                        ];
                    }
                }
            });
        }

        // Reindexar saldos anteriores
        $saldosIndexados = [];
        foreach ($saldosAnteriores as $grupo) {
            foreach ($grupo['materiales'] as $material) {
                $codigo = $material['codigo_material'];
                if (!isset($saldosIndexados[$codigo])) {
                    $saldosIndexados[$codigo] = $material;
                } else {
                    $saldosIndexados[$codigo]['lotes'] = array_merge(
                        $saldosIndexados[$codigo]['lotes'],
                        $material['lotes']
                    );
                }
            }
        }

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
                    'saldo_anterior' => $saldosIndexados[$materialCode]['lotes'] ?? [],
                    'lotes' => $materialLotes
                ];
            }

            // Calcular totales del grupo
            $saldoAnteriorCantidad = 0;
            $saldoAnteriorTotal = 0;
            $entradasCantidad = 0;
            $entradasTotal = 0;
            $salidasCantidad = 0;
            $salidasTotal = 0;
            $saldoFinalCantidad = 0;
            $saldoFinalTotal = 0;

            foreach ($groupResult['materiales'] as $material) {
                foreach ($material['saldo_anterior'] as $lote) {
                    $saldoAnteriorCantidad += $lote['cantidad_restante'] ?? 0;
                    $saldoAnteriorTotal += $lote['valor_restante'] ?? 0;
                }
                foreach ($material['lotes'] as $lote) {
                    $entradasCantidad += $lote['cantidad_inicial'] ?? 0;
                    $entradasTotal += $lote['cantidad_1'] ?? 0;
                    $salidasCantidad += ($lote['cantidad_inicial'] - $lote['cantidad_restante']) ?? 0;
                    $salidasTotal += $lote['cantidad_2'] ?? 0;
                    $saldoFinalCantidad += $lote['cantidad_restante'] ?? 0;
                    $saldoFinalTotal += $lote['cantidad_3'] ?? 0;
                }
            }

            $groupResult['resumen'] = [
                'saldo_anterior_cantidad' => $saldoAnteriorCantidad,
                'saldo_anterior_total' => $saldoAnteriorTotal,
                'entradas_cantidad' => $entradasCantidad,
                'entradas_total' => $entradasTotal,
                'salidas_cantidad' => $salidasCantidad,
                'salidas_total' => $salidasTotal,
                'saldo_final_cantidad' => $saldoFinalCantidad,
                'saldo_final_total' => $saldoFinalTotal,
            ];

            if (!empty($groupResult['materiales'])) {
                $result[] = $groupResult;
            }
        }

        $note = Note_Entrie::getFirstNoteOfYear();
        $formattedDate = $note ? Note_Entrie::formatDate($note->delivery_date) : null;

        $data = [
            'title' => 'INVENTARIO FISICO VALORADO ALMACENES MUSERPOL',
            'result' => $result,
            'date_note' => $formattedDate,
        ];

        $pdf = Pdf::loadView('ValuedPhysical.ValuedPhysical', $data)->setPaper('letter', 'landscape');
        return $pdf->stream('Inventario Fisico Valorado.pdf');
    }

    public function print_consolidated_valued_physical_inventory(Request $request)
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
                            if ($deliveredQuantity <= 0) break;

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

        $previousManagement = Management::where('id', '<', $latestManagement->id)
            ->orderByDesc('id')
            ->first();

        $saldosAnteriores = [];

        if ($previousManagement) {
            $saldosQuery = Note_Entrie::where('management_id', $previousManagement->id)
                ->where('state', 'Aceptado')
                ->with(['materials' => function ($query) {
                    $query->select('materials.id', 'materials.description', 'materials.code_material', 'materials.group_id', 'materials.unit_material');
                }, 'materials.group' => function ($query) {
                    $query->select('groups.id', 'groups.name_group', 'groups.code');
                }]);

            $saldosQuery->chunk(100, function ($notes) use (&$saldosAnteriores) {
                foreach ($notes as $note) {
                    foreach ($note->materials as $material) {
                        $saldo = (float) $material->pivot->request;
                        if ($saldo <= 0) continue;

                        $group = $material->group;
                        $groupName = $group ? $group->name_group : 'Sin grupo';
                        $groupCode = $group ? $group->code : null;
                        $materialCode = $material->code_material;
                        $materialName = $material->description;
                        $materialUnit = $material->unit_material;
                        $costUnit = (float) $material->pivot->cost_unit;
                        $deliveryDate = $note->delivery_date;

                        if (!isset($saldosAnteriores[$groupName])) {
                            $saldosAnteriores[$groupName] = [
                                'codigo_grupo' => $groupCode,
                                'materiales' => []
                            ];
                        }

                        $saldosAnteriores[$groupName]['materiales'][] = [
                            'codigo_material' => $materialCode,
                            'nombre_material' => $materialName,
                            'unidad_material' => $materialUnit,
                            'lotes' => [
                                [
                                    'fecha_ingreso' => $deliveryDate,
                                    'cantidad_restante' => $saldo,
                                    'precio_unitario' => $costUnit,
                                    'valor_restante' => $saldo * $costUnit,
                                ]
                            ]
                        ];
                    }
                }
            });
        }

        $saldosIndexados = [];
        foreach ($saldosAnteriores as $grupo) {
            foreach ($grupo['materiales'] as $material) {
                $codigo = $material['codigo_material'];
                if (!isset($saldosIndexados[$codigo])) {
                    $saldosIndexados[$codigo] = $material;
                } else {
                    $saldosIndexados[$codigo]['lotes'] = array_merge(
                        $saldosIndexados[$codigo]['lotes'],
                        $material['lotes']
                    );
                }
            }
        }

        $result = [];
        foreach ($notesData as $groupName => $groupData) {
            $groupResult = [
                'grupo' => $groupName,
                'codigo_grupo' => $groupData['codigo_grupo'],
            ];

            $materialesTemp = [];

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

                $materialesTemp[] = [
                    'saldo_anterior' => $saldosIndexados[$materialCode]['lotes'] ?? [],
                    'lotes' => $materialLotes
                ];
            }

            $saldoAnteriorCantidad = 0;
            $saldoAnteriorTotal = 0;
            $entradasCantidad = 0;
            $entradasTotal = 0;
            $salidasCantidad = 0;
            $salidasTotal = 0;
            $saldoFinalCantidad = 0;
            $saldoFinalTotal = 0;

            foreach ($materialesTemp as $material) {
                foreach ($material['saldo_anterior'] as $lote) {
                    $saldoAnteriorCantidad += $lote['cantidad_restante'] ?? 0;
                    $saldoAnteriorTotal += $lote['valor_restante'] ?? 0;
                }
                foreach ($material['lotes'] as $lote) {
                    $entradasCantidad += $lote['cantidad_inicial'] ?? 0;
                    $entradasTotal += $lote['cantidad_1'] ?? 0;
                    $salidasCantidad += ($lote['cantidad_inicial'] - $lote['cantidad_restante']) ?? 0;
                    $salidasTotal += $lote['cantidad_2'] ?? 0;
                    $saldoFinalCantidad += $lote['cantidad_restante'] ?? 0;
                    $saldoFinalTotal += $lote['cantidad_3'] ?? 0;
                }
            }

            $groupResult['resumen'] = [
                'saldo_anterior_cantidad' => $saldoAnteriorCantidad,
                'saldo_anterior_total' => $saldoAnteriorTotal,
                'entradas_cantidad' => $entradasCantidad,
                'entradas_total' => $entradasTotal,
                'salidas_cantidad' => $salidasCantidad,
                'salidas_total' => $salidasTotal,
                'saldo_final_cantidad' => $saldoFinalCantidad,
                'saldo_final_total' => $saldoFinalTotal,
            ];

            $result[] = $groupResult;
        }

        $note = Note_Entrie::getFirstNoteOfYear();


        $data = [
            'title' => 'INVENTARIO FISICO VALORADO CONSOLIDADO',
            'results' => $result,
            'start_date' => $startDate,
            'end_date' => $endDate,
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

    public function consolidated_inventory($request)
    {
        $requestTotal = $this->ValuedPhysical($request);
        return $requestTotal;
    }


    public function consolidated_inventory_modificaded(Request $request)
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
                            if ($deliveredQuantity <= 0) break;

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

        $previousManagement = Management::where('id', '<', $latestManagement->id)
            ->orderByDesc('id')
            ->first();

        $saldosAnteriores = [];

        if ($previousManagement) {
            $saldosQuery = Note_Entrie::where('management_id', $previousManagement->id)
                ->where('state', 'Aceptado')
                ->with(['materials' => function ($query) {
                    $query->select('materials.id', 'materials.description', 'materials.code_material', 'materials.group_id', 'materials.unit_material');
                }, 'materials.group' => function ($query) {
                    $query->select('groups.id', 'groups.name_group', 'groups.code');
                }]);

            $saldosQuery->chunk(100, function ($notes) use (&$saldosAnteriores) {
                foreach ($notes as $note) {
                    foreach ($note->materials as $material) {
                        $saldo = (float) $material->pivot->request;
                        if ($saldo <= 0) continue;

                        $group = $material->group;
                        $groupName = $group ? $group->name_group : 'Sin grupo';
                        $groupCode = $group ? $group->code : null;
                        $materialCode = $material->code_material;
                        $materialName = $material->description;
                        $materialUnit = $material->unit_material;
                        $costUnit = (float) $material->pivot->cost_unit;
                        $deliveryDate = $note->delivery_date;

                        if (!isset($saldosAnteriores[$groupName])) {
                            $saldosAnteriores[$groupName] = [
                                'codigo_grupo' => $groupCode,
                                'materiales' => []
                            ];
                        }

                        $saldosAnteriores[$groupName]['materiales'][] = [
                            'codigo_material' => $materialCode,
                            'nombre_material' => $materialName,
                            'unidad_material' => $materialUnit,
                            'lotes' => [
                                [
                                    'fecha_ingreso' => $deliveryDate,
                                    'cantidad_restante' => $saldo,
                                    'precio_unitario' => $costUnit,
                                    'valor_restante' => $saldo * $costUnit,
                                ]
                            ]
                        ];
                    }
                }
            });
        }

        $saldosIndexados = [];
        foreach ($saldosAnteriores as $grupo) {
            foreach ($grupo['materiales'] as $material) {
                $codigo = $material['codigo_material'];
                if (!isset($saldosIndexados[$codigo])) {
                    $saldosIndexados[$codigo] = $material;
                } else {
                    $saldosIndexados[$codigo]['lotes'] = array_merge(
                        $saldosIndexados[$codigo]['lotes'],
                        $material['lotes']
                    );
                }
            }
        }

        $result = [];
        foreach ($notesData as $groupName => $groupData) {
            $groupResult = [
                'grupo' => $groupName,
                'codigo_grupo' => $groupData['codigo_grupo'],
            ];

            $materialesTemp = [];

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

                $materialesTemp[] = [
                    'saldo_anterior' => $saldosIndexados[$materialCode]['lotes'] ?? [],
                    'lotes' => $materialLotes
                ];
            }

            $saldoAnteriorCantidad = 0;
            $saldoAnteriorTotal = 0;
            $entradasCantidad = 0;
            $entradasTotal = 0;
            $salidasCantidad = 0;
            $salidasTotal = 0;
            $saldoFinalCantidad = 0;
            $saldoFinalTotal = 0;

            foreach ($materialesTemp as $material) {
                foreach ($material['saldo_anterior'] as $lote) {
                    $saldoAnteriorCantidad += $lote['cantidad_restante'] ?? 0;
                    $saldoAnteriorTotal += $lote['valor_restante'] ?? 0;
                }
                foreach ($material['lotes'] as $lote) {
                    $entradasCantidad += $lote['cantidad_inicial'] ?? 0;
                    $entradasTotal += $lote['cantidad_1'] ?? 0;
                    $salidasCantidad += ($lote['cantidad_inicial'] - $lote['cantidad_restante']) ?? 0;
                    $salidasTotal += $lote['cantidad_2'] ?? 0;
                    $saldoFinalCantidad += $lote['cantidad_restante'] ?? 0;
                    $saldoFinalTotal += $lote['cantidad_3'] ?? 0;
                }
            }

            $groupResult['resumen'] = [
                'saldo_anterior_cantidad' => $saldoAnteriorCantidad,
                'saldo_anterior_total' => $saldoAnteriorTotal,
                'entradas_cantidad' => $entradasCantidad,
                'entradas_total' => $entradasTotal,
                'salidas_cantidad' => $salidasCantidad,
                'salidas_total' => $salidasTotal,
                'saldo_final_cantidad' => $saldoFinalCantidad,
                'saldo_final_total' => $saldoFinalTotal,
            ];

            $result[] = $groupResult;
        }

        $note = Note_Entrie::getFirstNoteOfYear();
        // $formattedDate = $note ? Note_Entrie::formatDate($note->delivery_date) : null;

        return response()->json([
            'data' => $result
        ]);
    }

    public function consolidated_inventory_excel(Request $request)
    {
        $jsonData = $this->consolidated_inventory_modificaded($request)->getData(true);

        return Excel::download(new ConsolidatedValuedPhysicalInventoryExport($jsonData['data']), 'inventario_valorado.xlsx');
    }
}
