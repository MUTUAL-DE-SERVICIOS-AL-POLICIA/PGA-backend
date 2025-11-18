<?php

namespace App\Http\Controllers;

use App\Helpers\Ldap;
use App\Models\Departure;
use App\Models\Employee;
use App\Models\Fund;
use App\Models\Group;
use App\Models\Management;
use App\Models\PettyCash;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\TypeCancellation;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PettycashController extends Controller
{
    public function Accountability_sheet()
    {

        $fund = Fund::where('type', 'ASIGNACIÓN DE FONDOS DE CAJA CHICA')
            ->whereNotNull('reception_date')
            ->orderBy('id', 'desc')
            ->first(['id']);

        $pettyCashes = PettyCash::where('state', 'Finalizado')
            ->where('fund_id', '>=', $fund->id)
            ->with([
                'products' => function ($q) {
                    $q->select('products.id', 'products.description', 'products.group_id', 'products.cost_object');
                },
                'products.group:id,code',
            ])
            ->get(['id', 'number_note', 'delivery_date']);

        $allProducts = $pettyCashes->flatMap(function ($pc) {
            return $pc->products->map(function ($product) use ($pc) {
                return [
                    'number_note' => $pc->number_note,
                    'delivery_date' => $pc->delivery_date,
                    'id' => $product->id,
                    'description' => $product->description,
                    'code' => optional($product->group)->code,
                    'id_group' => $product->group_id,
                    'invoice_number' => $product->pivot->number_invoice,
                    'costDetail' => number_format($product->pivot->costDetails, 2, '.', ''),
                    'costTotal' => number_format($product->pivot->costTotal, 2, '.', ''),
                ];
            });
        })->values();

        $pettyCashTickets = PettyCash::where('state', 'Finalizado')
            ->where('fund_id', '>=', $fund->id)
            ->whereHas('tickets')
            ->with([
                'tickets' => function ($q) {
                    $q->select('id', 'pettycash_id', 'from', 'to', 'cost', 'id_permission', 'ticket_invoice', 'permission_day');
                }
            ])
            ->get(['id', 'number_note', 'delivery_date']);

        $allTickets = $pettyCashTickets->flatMap(function ($pc) {
            return $pc->tickets->map(function ($ticket) use ($pc) {
                return [
                    'number_note' => $pc->number_note,
                    'delivery_date' => $pc->delivery_date,
                    'id' => $ticket->id,
                    'description' => 'TRANSPORTE DEL PERSONAL',
                    'code' => '22600',
                    'id_group' => 41,
                    'invoice_number' => $ticket->ticket_invoice,
                    'costDetail' => number_format($ticket->cost, 2, '.', ''),
                    'costTotal' => number_format($ticket->cost, 2, '.', ''),
                ];
            });
        })->values();

        $combined = $allProducts
            ->concat($allTickets)
            ->sortBy(function ($item) {
                return $item['delivery_date'];
            })
            ->values();


        $grandTotal = $combined->sum(function ($item) {
            return (float) str_replace([','], [''], $item['costTotal']);
        });
        $grandTotalFormatted = number_format($grandTotal, 2, '.', '');

        $funds = Fund::whereNotNull('reception_date')->where('id', '>=', $fund->id)->get();

        $fundsTotalReceived = $funds->sum(function ($f) {
            return (float) str_replace([','], [''], $f->received_amount);
        });
        $fundsTotalReceivedFormatted = number_format($fundsTotalReceived, 2, '.', '');

        $fundsVsSpentDiff = $fundsTotalReceived - $grandTotal;
        $fundsVsSpentDiffFormatted = number_format($fundsVsSpentDiff, 2, '.', '');


        $allGroups = Group::all();

        $groupsSummary = $allGroups->map(function ($group) use ($combined) {
            $groupedProducts = $combined->where('id_group', $group->id);
            return [
                'code' => $group->code,
                'name_group' => $group->name_group,
                'total_amount' => $groupedProducts->sum('costTotal'),
            ];
        });

        $data = [
            'products' => $combined,
            'grand_total' => $grandTotalFormatted,
            'funds_total_received' => $fundsTotalReceivedFormatted,
            'funds_vs_spent_diff' => $fundsVsSpentDiffFormatted,
            'groups' => $groupsSummary,
        ];

        return $data;
    }

    public function Print_Accountability_sheet(Request $request)
    {
        $fund = Fund::latest()->first();
        $previousFund = Fund::where('id', '<', optional($fund)->id)
            ->orderByDesc('id')
            ->first();

        $start = optional($previousFund)->discharge_date;

        $end = $request->input('end_date') ? Carbon::parse($request->input('end_date'))->endOfDay() : null;

        $pettyCashesQuery = PettyCash::where('state', 'Finalizado')
            ->with([
                'products' => function ($q) {
                    $q->select('products.id', 'products.description', 'products.group_id', 'products.cost_object');
                },
                'products.group:id,code',
            ]);
        if ($start && $end) {
            $pettyCashesQuery->whereBetween('delivery_date', [$start, $end]);
        } elseif ($start && !$end) {
            $pettyCashesQuery->whereDate('delivery_date', '>=', $start);
        } elseif (!$start && $end) {
            $pettyCashesQuery->whereDate('delivery_date', '<=', $end);
        }

        $pettyCashes = $pettyCashesQuery->get(['id', 'number_note', 'delivery_date', 'updated_at']);

        $allProducts = $pettyCashes->flatMap(function ($pc) {
            return $pc->products->map(function ($product) use ($pc) {
                return [
                    'number_note' => $pc->number_note,
                    'delivery_date' => $pc->delivery_date,
                    'updated_at' => $pc->updated_at,
                    'id' => $product->id,
                    'description' => $product->description,
                    'code' => optional($product->group)->code,
                    'id_group' => $product->group_id,
                    'invoice_number' => $product->pivot->number_invoice,
                    'costDetail' => number_format($product->pivot->costDetails, 2, '.', ''),
                    'costTotal' => number_format($product->pivot->costTotal, 2, '.', ''),
                ];
            });
        })->values();


        $pettyCashesQuery = PettyCash::where('state', 'Finalizado')
            ->whereHas('tickets')
            ->with([
                'tickets' => function ($q) {
                    $q->select('id', 'pettycash_id', 'from', 'to', 'cost', 'id_permission', 'ticket_invoice', 'permission_day');
                }
            ]);
        if ($start && $end) {
            $pettyCashesQuery->whereBetween('delivery_date', [$start, $end]);
        } elseif ($start && !$end) {
            $pettyCashesQuery->whereDate('delivery_date', '>=', $start);
        } elseif (!$start && $end) {
            $pettyCashesQuery->whereDate('delivery_date', '<=', $end);
        }

        $pettyCashTickets = $pettyCashesQuery->get(['id', 'number_note', 'delivery_date', 'updated_at']);

        $allTickets = $pettyCashTickets->flatMap(function ($pc) {
            return $pc->tickets->map(function ($ticket) use ($pc) {
                return [
                    'number_note' => $pc->number_note,
                    'delivery_date' => $pc->delivery_date,
                    'updated_at' => $pc->updated_at,
                    'id' => $ticket->id,
                    'description' => 'TRANSPORTE DEL PERSONAL',
                    'code' => '22600',
                    'id_group' => 41,
                    'invoice_number' => $ticket->ticket_invoice,
                    'costDetail' => number_format($ticket->cost, 2, '.', ''),
                    'costTotal' => number_format($ticket->cost, 2, '.', ''),
                ];
            });
        })->values();

        $combined = $allProducts
            ->concat($allTickets)
            ->sortBy(function ($item) {
                return $item['updated_at'];
            })
            ->values();


        $grandTotal = $combined->sum(function ($item) {
            return (float) str_replace([','], [''], $item['costTotal']);
        });


        $grandTotalFormatted = number_format($grandTotal, 2, '.', '');

        $funds = Fund::orderBy('created_at', 'asc')->get();

        $funds = $funds->filter(function ($row) use ($end) {
            if (is_null($end)) {
                return true;
            }
            return $row->created_at->lte($end);
        });


        $startIndex = $funds->reverse()->search(function ($row) {
            return $row->type === 'ASIGNACIÓN DE RECURSOS';
        });

        $startIndex = $funds->count() - 1 - $startIndex;

        $funds = $funds->slice($startIndex)->values();

        $fundsTotalReceived = $funds->sum(function ($f) {
            return (float) str_replace([','], [''], $f->received_amount);
        });
        $fundsTotalReceivedFormatted = number_format($fundsTotalReceived, 2, '.', '');

        $fundsVsSpentDiff = $fundsTotalReceived - $grandTotal;
        $fundsVsSpentDiffFormatted = number_format($fundsVsSpentDiff, 2, '.', '');


        $allGroups = Group::all();

        $groupsSummary = $allGroups->map(function ($group) use ($combined) {
            $groupedProducts = $combined->where('id_group', $group->id);
            return [
                'code' => $group->code,
                'name_group' => $group->name_group,
                'total_amount' => $groupedProducts->sum('costTotal'),
            ];
        });

        $fundConfig = Fund::latest()->first();
        $fundConfig->discharge_date = today()->toDateString();
        $fundConfig->current_amount = $grandTotalFormatted;
        $fundConfig->save();

        Fund::create([
            'received_amount' => $grandTotalFormatted,
            'current_amount' => $grandTotalFormatted,
            'name_responsible' => 'WILLIAM ITURRALDE QUISBERT',
            'username_responsible' => 'witurralde',
            'type' => 'REPOSICIÓN DE FONDOS',
        ]);

        $data = [
            'title' => 'PLANILLA DE RENDICIÓN DE CUENTAS',
            'date' => Carbon::now()->format('Y'),
            'date_first' => Carbon::now()->format('Y-m-d'),
            'area' => 'UNIDAD ADMINISTRATIVA',
            'products' => $combined,
            'grand_total'  => $grandTotalFormatted,
            'funds_total_received' => $fundsTotalReceivedFormatted,
            'funds_vs_spent_diff'  => $fundsVsSpentDiffFormatted,
            'groups' => $groupsSummary,
        ];

        $pdf = Pdf::loadView('NotePettyCash.AccountabilitySheet', $data);
        return $pdf->download('Planilla_de_rendicion_de_cuentas.pdf');
    }

    public function Petty_Cash_Record_Book()
    {
        $pettyCashes = PettyCash::where('state', 'Finalizado')
            ->with(['products' => function ($query) {
                $query->select('products.id', 'description', 'group_id', 'cost_object');
            }])
            ->get(['id', 'user_register', 'number_note', 'approximate_cost', 'replacement_cost', 'delivery_date']);

        $formatted = $pettyCashes->map(function ($pettyCash) {
            $employee = Employee::find($pettyCash->user_register);
            return [
                'user_register' => $employee
                    ? "{$employee->first_name} {$employee->last_name} {$employee->mothers_last_name}"
                    : null,
                'number_note' => $pettyCash->number_note,
                'approximate_cost' => $pettyCash->approximate_cost,
                'replacement_cost' => $pettyCash->replacement_cost,
                'delivery_date' => $pettyCash->delivery_date,
                'products' => $pettyCash->products->map(function ($product) {
                    $group = Group::where('id', $product->group_id)->first();
                    $codeGroup = $group ? $group->code : null;
                    return [
                        'id' => $product->id,
                        'description' => $product->description,
                        'code' => $codeGroup,
                        'supplier' => $product->pivot->supplier,
                        'invoce_number' => $product->pivot->number_invoice,
                        'costDetail' => number_format($product->pivot->costDetails, 2),
                        'costTotal' => number_format($product->pivot->costTotal, 2),
                    ];
                }),
            ];
        });

        $funds = Fund::all();

        $fundEntries = $funds->map(function ($f) {
            return [
                'user_register' => $f->name_responsible,
                'number_note' => null,
                'approximate_cost' => null,
                'replacement_cost' => null,
                'delivery_date' => $f->reception_date,
                'products' => [
                    [
                        'id' => $f->id,
                        'description' => $f->type,
                        'ingresos' => number_format($f->received_amount, 2),
                    ]
                ],
            ];
        });

        $merged = $formatted
            ->concat($fundEntries)
            ->sortBy(function ($item) {
                return $item['delivery_date'];
            })
            ->values();


        return response()->json($merged);
    }

    public function Print_Petty_Cash_Record_Book(Request $request)
    {

        $start = $request->input('start_date') ? Carbon::parse($request->input('start_date'))->startOfDay() : null;
        $end = $request->input('end_date')   ? Carbon::parse($request->input('end_date'))->endOfDay()   : null;

        $toFloat = function ($v) {
            if ($v === null || $v === '') return 0.0;
            return (float) str_replace(',', '', $v);
        };
        $nf = function ($n) {
            return number_format((float)$n, 2, '.', ',');
        };
        $fmtDate = function ($d) {
            if (!$d) return '-';
            try {
                return Carbon::parse($d)->format('d/m/Y');
            } catch (\Throwable $e) {
                return (string)$d;
            }
        };

        $fund = Fund::where('type', 'ASIGNACIÓN DE FONDOS DE CAJA CHICA')
            ->whereNotNull('reception_date')
            ->orderBy('id', 'desc')
            ->first(['id']);

        $pettyCashes = PettyCash::where('state', 'Finalizado')
            ->whereHas('products')
            ->where('fund_id', '>=', $fund->id)
            ->with(['products' => function ($query) {
                $query->select('products.id', 'description', 'group_id', 'cost_object');
            }])
            ->get(['id', 'user_register', 'number_note', 'approximate_cost', 'replacement_cost', 'delivery_date', 'updated_at']);


        $formatted = $pettyCashes->map(function ($pettyCash) use ($toFloat, $nf) {
            $employee = Employee::find($pettyCash->user_register);
            return [
                'user_register' => $employee ? "{$employee->first_name} {$employee->last_name} {$employee->mothers_last_name}" : null,
                'number_note' => $pettyCash->number_note,
                'approximate_cost' => $pettyCash->approximate_cost,
                'replacement_cost' => $pettyCash->replacement_cost,
                'delivery_date' => $pettyCash->delivery_date,
                'updated_at' => $pettyCash->updated_at,
                'products' => $pettyCash->products->map(function ($product) use ($toFloat, $nf) {
                    $group = Group::where('id', $product->group_id)->first();
                    $codeGroup = $group ? $group->code : null;

                    $supplier = optional($product->pivot)->supplier;
                    $number_invoice = optional($product->pivot)->number_invoice;
                    $costDetails = optional($product->pivot)->costDetails;
                    $costTotal = optional($product->pivot)->costTotal;

                    return [
                        'id' => $product->id,
                        'description' => $product->description,
                        'code' => $codeGroup,
                        'supplier' => $supplier,
                        'invoice_number' => $number_invoice,
                        'costDetail_raw' => $toFloat($costDetails),
                        'costTotal_raw' => $toFloat($costTotal),
                        'costDetail' => $nf($toFloat($costDetails)),
                        'costTotal' => $nf($toFloat($costTotal)),
                        'invoce_number' => $number_invoice,
                    ];
                }),
            ];
        });

        $pettyCashTickets = PettyCash::where('state', 'Finalizado')
            ->where('fund_id', '>=', $fund->id)
            ->whereHas('tickets')
            ->with(['tickets' => function ($q) {
                $q->select('id', 'pettycash_id', 'from', 'to', 'cost', 'id_permission', 'ticket_invoice', 'permission_day');
            }])
            ->get(['id', 'user_register', 'number_note', 'approximate_cost', 'replacement_cost', 'delivery_date', 'updated_at']);

        $ticketPettyCash = $pettyCashTickets->map(function ($f) use ($toFloat, $nf) {
            $employee = Employee::find($f->user_register);
            return [
                'user_register' => $employee ? "{$employee->first_name} {$employee->last_name} {$employee->mothers_last_name}" : null,
                'number_note' => $f->number_note,
                'approximate_cost' => $f->approximate_cost,
                'replacement_cost' => $f->replacement_cost,
                'delivery_date' => $f->delivery_date,
                'updated_at' => $f->updated_at,
                'products' => [
                    [
                        'id' => $f->id,
                        'description' => "TRANSPORTE DE PERSONAL",
                        'code' => null,
                        'supplier' => null,
                        'invoice_number' => null,
                        'costDetail_raw' => $toFloat($f->approximate_cost),
                        'costTotal_raw' => $toFloat($f->approximate_cost),
                        'costDetail' => $nf($toFloat($f->approximate_cost)),
                        'costTotal' => $nf($toFloat($f->approximate_cost)),
                        'invoce_number'  => null,
                    ]
                ],
            ];
        });

        $funds = Fund::whereNotNull('reception_date')->where('id', '>=', $fund->id)->get();

        $fundEntries = $funds->map(function ($f) use ($toFloat, $nf) {
            return [
                'user_register' => $f->name_responsible,
                'number_note' => null,
                'approximate_cost' => null,
                'replacement_cost' => null,
                'delivery_date' => $f->created_at,
                'updated_at' => $f->updated_at,
                'products' => [
                    [
                        'id' => $f->id,
                        'description' => $f->type,
                        'code' => null,
                        'supplier' => null,
                        'invoice_number' => null,
                        'ingresos_raw' => $toFloat($f->received_amount),
                        'ingresos' => $nf($toFloat($f->received_amount)),
                        'invoce_number' => null,
                    ]
                ],
            ];
        });

        $merged = $formatted
            ->concat($fundEntries)
            ->concat($ticketPettyCash)
            ->sortBy(function ($item) {
                return $item['updated_at'];
            })
            ->values();

        $saldo = 0.0;
        $bookDiary = $merged->map(function ($entry) use (&$saldo, $toFloat, $nf, $fmtDate) {

            $ent = $toFloat($entry['approximate_cost'] ?? 0);
            $rep = $toFloat($entry['replacement_cost'] ?? 0);
            $importeDevueltoRaw = ($ent || $rep) ? ($ent - $rep) : 0.0;

            $products = collect($entry['products'] ?? [])->map(function ($p) use (&$saldo, $toFloat, $nf) {

                $ingreso = isset($p['ingresos_raw'])
                    ? $toFloat($p['ingresos_raw'])
                    : (isset($p['ingresos']) ? $toFloat($p['ingresos']) : 0.0);

                $gasto = isset($p['costTotal_raw'])
                    ? $toFloat($p['costTotal_raw'])
                    : (isset($p['costTotal']) ? $toFloat($p['costTotal']) : 0.0);

                $saldo += ($ingreso - $gasto);

                return [
                    'supplier' => $p['supplier'] ?? '-',
                    'invoice_number' => $p['invoice_number'] ?? ($p['invoce_number'] ?? '-'),
                    'description' => $p['description'] ?? '-',
                    'code' => $p['code'] ?? '-',

                    'gasto_raw' => $gasto,
                    'ingreso_raw' => $ingreso,
                    'saldo_raw' => $saldo,

                    'gasto' => $gasto > 0 ? $nf($gasto)   : '-',
                    'ingreso' => $ingreso > 0 ? $nf($ingreso) : '-',
                    'saldo' => $nf($saldo),
                ];
            });

            $saldoActualFila = $saldo;

            return [
                'delivery_date_raw' => $entry['delivery_date'],
                'delivery_date' => $fmtDate($entry['delivery_date']),
                'user_register' => $entry['user_register'] ?? '-',
                'number_note' => $entry['number_note'] ?? '-',

                'importe_entregado_raw' => $ent,
                'importe_devuelto_raw' => $importeDevueltoRaw,

                'importe_entregado' => $ent  ? $nf($ent)  : '-',
                'importe_devuelto' => ($ent || $rep) ? $nf($importeDevueltoRaw) : '-',

                'products' => $products,
                'saldo_current' => $nf($saldoActualFila),
                'saldo_current_raw' => $saldoActualFila,
            ];
        });

        $bookDiaryVisible = $bookDiary->when($start && $end, function ($col) use ($start, $end) {
            return $col->filter(function ($row) use ($start, $end) {
                try {
                    $d = Carbon::parse($row['delivery_date_raw']);
                } catch (\Throwable $e) {
                    return false;
                }
                return $d->betweenIncluded($start, $end);
            });
        })->values();

        $data = [
            'name' => 'WILLIAM ITURRALDE QUISBERT',
            'area' => 'UNIDAD ADMINISTRATIVA',
            'date' => Carbon::now()->format('Y-m-d'),
            'book_diary' => $bookDiaryVisible,
            'total_saldo' => $nf($saldo),
        ];



        $pdf = Pdf::loadView('NotePettyCash.PettyCashRecordBook', $data);
        return $pdf->download('libro_de_registros_finalizados.pdf');
    }

    public function Petty_Cash_Record_Book_Dates()
    {
        $fund = Fund::latest()->first();

        $fundsDate = Fund::where('type', 'ASIGNACIÓN DE FONDOS DE CAJA CHICA')
            ->latest()
            ->first();

        $data_import = $this->Accountability_sheet();
        $discharges = $data_import['grand_total'];
        $sum_rep = $data_import['funds_vs_spent_diff'];
        $sum_total = $data_import['funds_total_received'];
        $percent_discharges = $sum_total > 0 ? ($discharges / $sum_total) * 100 : 0;
        $percent_balance = $sum_total > 0 ? ($sum_rep / $sum_total) * 100 : 0;
        $has_no_reception_date = is_null($fund->reception_date);
        $designation = $fundsDate->received_amount;

        $percent_balance_vs_designation = $designation > 0
            ? ($sum_rep / $designation) * 100
            : 0;

        $percent_discharges_vs_designation = 100 - $percent_balance_vs_designation;

        $disabledEndManagement = ProductController::disableFunds();

        $dataPettyCash = [
            'date' => Carbon::now()->format('Y-m-d'),
            'name_responsibility' => 'WILLIAM ITURRALDE',
            'amount' => $sum_total,
            'discharges' => $discharges,
            'balance' => $sum_rep,
            'amount_replacement' => $fund->received_amount,
            'designation' => $designation,
            'has_no_reception_date' => $has_no_reception_date,
            'disabledEndManagement' => $disabledEndManagement,
            'percentages' => [
                'discharges' => round($percent_discharges, 2),
                'balance' => round($percent_balance, 2),

                'balance_vs_designation' => round($percent_balance_vs_designation, 2),
                'discharges_vs_designation' => round($percent_discharges_vs_designation, 2),
            ],
        ];

        return response()->json([
            'dataPettyCash' => $dataPettyCash,
        ]);
    }

    public function PaymentOrder(Request $request)
    {

        $fund = Fund::latest()->first();
        $date_day = Carbon::now()->format('Y-m-d');
        $date_send = Carbon::parse($date_day)->locale('es')->isoFormat('DD [de] MMMM [de] YYYY');
        $grand_total = $fund->received_amount;
        $number_literal = ProductController::numero_a_letras($grand_total);

        $data = [
            'title' => 'ORDEN DE PAGO',
            'date_send' => $date_send,
            'amount' => $grand_total,
            'amount_literal' => $number_literal,
            'responsible' => $fund->name_responsible,
            'routeSheet' => $request->routeSheet

        ];

        $pdf = Pdf::loadView('NotePettyCash.PaymentOrder', $data);
        return $pdf->download('Orden_de_Pago.pdf');
    }

    public function CreateDischarge(Request $request)
    {
        $fund = Fund::latest()->first();
        $fund->reception_date = today()->toDateString();
        $fund->save();
        return response()->json($fund, 201);
    }

    public function listNotePettyCashes(Request $request)
    {
        $page = max(0, $request->get('page', 0));
        $limit = max(1, $request->get('limit', PettyCash::count()));
        $start = $page * $limit;

        $notes = PettyCash::with([
            'products' => function ($q) {
                $q->select('products.id', 'description');
            },
            'tickets' => function ($q) {
                $q->select('pettycash_id', 'from', 'to', 'cost', 'id_permission', 'ticket_invoice', 'permission_day');
            },
        ])
            ->orderByDesc('id')
            ->get(['id', 'number_note', 'concept', 'request_date', 'approximate_cost', 'state', 'comment_recived', 'user_register', 'delivery_date', 'type_cash_id']);

        $totalNotePetty = $notes->count();

        $notes = $notes->slice($start, $limit)->values();

        $data = $notes->map(function ($n) {
            $positionName = $this->titlePerson($n->user_register);

            $user = User::where('employee_id', $n->user_register)->first();

            if (!$user) {
                $employee = Employee::where('id', $n->user_register)->first();
            } else {
                $employee = Employee::find($n->user_register);
            }

            return [
                'id' => $n->id,
                'number_note' => $n->number_note,
                'concept' => $n->concept,
                'request_date' => (string) $n->request_date,
                'delivery_date' => (string) $n->delivery_date,
                'approximate_cost' => $n->approximate_cost,
                'state' => $n->state,
                'employee' => "{$employee->first_name} {$employee->last_name} {$employee->mothers_last_name}",
                'username' => $positionName,
                'comment_recived' => $n->comment_recived,
                'type_cash_id' => $n->type_cash_id,
                'products' => $n->products->map(function ($p) {
                    return [
                        'product_id' => $p->id,
                        'description' => $p->description,
                        'costDetail' => optional($p->pivot)->costDetails,
                        'amount_request' => optional($p->pivot)->amount_request,
                        'quantity_delivered' => optional($p->pivot)->quantity_delivered,
                        'costDetailsFinal' => optional($p->pivot)->costDetailsFinal,
                        'invoice_number' => optional($p->pivot)->number_invoice,
                    ];
                })->values(),

                'tickets' => $n->tickets->map(function ($t) {
                    return [
                        'id_permission' => $t->id_permission,
                        'from' => $t->from,
                        'to' => $t->to,
                        'cost' => $t->cost,
                        'permission_day' => $t->permission_day
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'total' =>  $totalNotePetty,
            'page' => $page,
            'last_page' => ceil($totalNotePetty / $limit),
            'data' => $data
        ]);
    }

    public function deliveredGroupProduct(Request $request)
    {
        $items = $request->input('items', []);

        foreach ($items as $item) {
            Product::whereKey($item['productId'])->update(['group_id' => $item['groupId']]);
        }

        $note = PettyCash::with('products')->findOrFail($request->notePettyCashId);
        $note->comment_recived = '';
        $note->state = 'Finalizado';
        $note->save();
        $total = ($note->approximate_cost - $note->replacement_cost);

        return response()->json([
            'note' => $note,
            'status' => true,
            'message' => "Se recibio el monto de $total bs y se modificaron los grupos"
        ], 200);
    }

    public function titlePerson($idPersona)
    {

        $ldap = new Ldap();
        $user = $ldap->get_entry($idPersona, 'id');
        if ($user && isset($user['title'])) {
            return $user['title'];
        }
        return null;
    }

    public function postDeliveyOfResources(Request $request)
    {
        $note = PettyCash::whereId($request->id)->first();
        if ($note->type_cash_id == 3) {

            $note->state = 'Finalizado';
            $note->delivery_date = today()->toDateString();
        }

        $note->request_date = today()->toDateString();
        $note->save();

        $approximate = $note->approximate_cost;

        return response()->json([
            'note' => $note,
            'status' => true,
            'message' => "$approximate bs"
        ], 200);
    }

    public function request_cancellation(Request $request)
    {
        $note = PettyCash::whereId($request->id)->first();
        $typeCancellations = TypeCancellation::whereId($request->types_cancellations)->first();
        switch ($request->types_cancellations) {
            case 1:
                $note->state = "Anulado";
                $note->comment_recived = $typeCancellations->description;
                $note->reason_for_cancellation_id = $typeCancellations->id;
                break;
            case 2:
                $note->state = "Anulado";
                $note->comment_recived = $typeCancellations->description;
                $note->reason_for_cancellation_id = $typeCancellations->id;
                break;
            case 3:
                $note->state = "Anulado";
                $note->comment_recived = $typeCancellations->description;
                $note->reason_for_cancellation_id = $typeCancellations->id;
                break;
        }
        $note->save();
        $note->delete();
        return response()->json([
            'note' => $note,
            'status' => true,
            'message' => "Solicitud Anulada"
        ], 200);
    }

    public function personal_transport_tickets(Request $request)
    {
        $validated = $request->validate([
            'employeeId' => 'required|integer',
            'transfers' => 'required|array|min:1',
            'transfers.*.from' => 'required|string',
            'transfers.*.to' => 'required|string',
            'transfers.*.cost' => 'required|numeric|min:0|max:5',
            'transfers.*.permCode' => 'required|string',
        ]);

        $employeeId = (int) $validated['employeeId'];

        $resolvedDepartures = [];
        foreach ($validated['transfers'] as $idx => $t) {
            $permCode = $t['permCode'];

            $departure = Departure::where('code', $permCode)->first();
            $validPermissionIds = [6, 7, 8, 9];
            if (!$departure) {
                return response()->json([
                    "message" => "El permiso con el codigo $permCode, no existe"
                ], 201);
            }
            if ((int)$departure->employee_id !== $employeeId) {
                return response()->json([
                    "message" => "El permiso con el codigo $permCode, no te pertence"
                ], 201);
            }
            $alreadyLinked = Ticket::where('id_permission', $departure->id)->exists();
            if ($alreadyLinked) {
                return response()->json([
                    "message" => "El permiso con el codigo $permCode, ya fue utilizado para solicitar pasajes"
                ], 201);
            }

            if (!in_array($departure->departure_reason_id, $validPermissionIds)) {
                return response()->json([
                    "message" => "El permiso con el código $permCode no es de tipo comisión."
                ], 201);
            }



            $resolvedDepartures[$permCode] = $departure;
        }

        return DB::transaction(function () use ($validated, $resolvedDepartures) {

            $lastNoteNumber = PettyCash::max('number_note');
            $number_note = $lastNoteNumber ? $lastNoteNumber + 1 : 1;

            $period = Management::latest()->firstOrFail();
            $fund   = Fund::latest()->firstOrFail();

            $notePettyCash = PettyCash::create([
                'number_note' => $number_note,
                'concept' => '(TRANSPORTE) ' . 'TRANSPORTE PERSONAL',
                'approximate_cost' => 0,
                'replacement_cost' => 0,
                'state' => 'En Revision',
                'comment_recived' => 'Transporte Personal',
                'user_register' => $validated['employeeId'],
                'management_id' => $period->id,
                'fund_id' => $fund->id,
                'type_cash_id' => 3,
            ]);

            $tickets = [];
            foreach ($validated['transfers'] as $t) {
                $departure = $resolvedDepartures[$t['permCode']];

                $ticket = new Ticket();
                $ticket->from = $t['from'];
                $ticket->to = $t['to'];
                $ticket->cost = $t['cost'];
                $ticket->id_permission = $departure->id;
                $ticket->permission_day = $departure->departure;
                $ticket->ticket_invoice = $departure->code;
                $ticket->pettycash_id = $notePettyCash->id;

                $ticket->group_id = 41;
                $ticket->save();

                $tickets[] = $ticket;
            }

            $total = collect($tickets)->sum('cost');
            $notePettyCash->approximate_cost = $total;
            $notePettyCash->replacement_cost = $total;
            $notePettyCash->save();

            return response()->json([
                'ok' => true,
                'note' => $notePettyCash,
                'total' => $total,
                'tickets' => $tickets,
                'message' => 'Solicitud guardada correctamente'
            ], 200);
        });
    }

    public function getFunds()
    {
        $lastAssignment = Fund::where('type', 'ASIGNACIÓN DE FONDOS DE CAJA CHICA')
            ->orderBy('id', 'desc')
            ->first();


        if (!$lastAssignment) {
            return response()->json([
                'message' => 'No existe ninguna Asignacion de recursos registrada.'
            ], 404);
        }

        $listFunds = Fund::where('id', '>=', $lastAssignment->id)
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'data' => $listFunds
        ]);
    }

    public function EndManagement(Request $request)
    {
        $fundConfig = Fund::latest()->first();
        $fundConfig->discharge_date = today()->toDateString();
        $fundConfig->current_amount = $request->discharges;
        $fundConfig->save();

        $endFundManagement = Fund::create([
            'received_amount' => 0,
            'current_amount' => 0,
            'name_responsible' => 'WILLIAM ITURRALDE QUISBERT',
            'username_responsible' => 'witurralde',
            'type' => 'ASIGNACIÓN DE FONDOS DE CAJA CHICA',
        ]);

        return response()->json([
            'dataPettyCash' => $endFundManagement,
            'status' => true,
            'message' => "Llenar los datos cuando quiera iniciar la gestión"
        ], 200);
    }

    public function NewManagementPettyCash(Request $request)
    {
        $fundConfig = Fund::latest()->first();
        $fundConfig->reception_date = today()->toDateString();
        $fundConfig->received_amount = $request->assignmentAmount;
        $fundConfig->current_amount = $request->assignmentAmount;
        $fundConfig->name_responsible = $request->responsible;
        $fundConfig->save();

        return response()->json([
            'fund' => $fundConfig,
            'status' => true,
            'message' => "Inicio de Gesión Exitoso"
        ], 200);
    }
}
