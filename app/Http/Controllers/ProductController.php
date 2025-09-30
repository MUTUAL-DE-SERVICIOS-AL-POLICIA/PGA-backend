<?php

namespace App\Http\Controllers;

use App\Helpers\Ldap;
use App\Models\Employee;
use App\Models\Fund;
use App\Models\Group;
use App\Models\Management;
use App\Models\Material;
use App\Models\PettyCash;
use App\Models\PettyCash_Product;
use App\Models\Product;
use App\Models\TypePetty;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use NumberFormatter;

class ProductController extends Controller
{

    public function list_petty_cash_user($userId)
    {
        $notes = PettyCash::where('user_register', $userId)
            ->with(['products' => function ($q) {
                $q->select('products.id', 'description');
            }])
            ->orderByDesc('id')
            ->get(['id', 'number_note', 'concept', 'request_date', 'delivery_date', 'approximate_cost', 'state', 'comment_recived']);

        $data = $notes->map(function ($n) {
            return [
                'id' => $n->id,
                'number_note' => $n->number_note,
                'concept' => $n->concept,
                'request_date' => (string) $n->request_date,
                'delivery_date' => (string) $n->delivery_date,
                'approximate_cost' => $n->approximate_cost,
                'state' => $n->state,
                'comment_recived' => $n->comment_recived,
                'products' => $n->products->map(function ($p) {
                    return [
                        'description' => $p->description,
                        'costDetail' => optional($p->pivot)->costDetails,
                        'amount_request' => optional($p->pivot)->amount_request,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json($data);
    }


    public function list_petty_cash()
    {
        $query = Product::where('description', '!=', 'PASAJES')->get();
        return $query;
    }

    public function list_total_petty_cash()
    {
        $fund = Fund::latest()->first();
        $query = PettyCash::where('fund_id', $fund->id)->get();
        return response()->json([
            'data' => $query
        ]);
    }

    public function create_product(Request $request)
    {
        try {
            $validate = $request->validate([
                'description' => 'required|string|max:255',
            ]);
            $validate['description'] = strtoupper($validate['description']);
            $product = Product::create([
                'description' => $validate['description'],
                'cost_object' => 'CAJA CHICA',
            ]);
            return response()->json([
                'message' => 'Producto creado correctamente',
                'material' => $product,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Ocurrió un error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function create_note(Request $request)
    {
        $lastNoteNumber = PettyCash::max('number_note');
        $number_note = $lastNoteNumber ? $lastNoteNumber + 1 : 1;
        $period = Management::latest()->first();
        $fund = Fund::latest()->first();

        $approximate_cost = 0;

        foreach ($request['product'] as $productData) {
            $approximate_cost += $productData['quantity'] * $productData['price'];
        }

        $notePettyCash = PettyCash::create([
            'number_note' => $number_note,
            'concept' => $request['concept'],
            'request_date' => today()->toDateString(),
            'approximate_cost' => $approximate_cost,
            'state' => 'En Revision',
            'user_register' => $request['id'],
            'management_id' => $period->id,
            'fund_id' => $fund->id,
            'type_cash_id' => $request->type

        ]);

        foreach ($request['product'] as $productData) {
            $notePettyCash->products()->attach($productData['id'], [
                'amount_request' => $productData['quantity'],
                'name_product' => $productData['description'],
                'costDetails' => $productData['price']
            ]);
        }


        return response()->json($notePettyCash->load('products'), 201);
    }


    public function create_note_tickets(Request $request)
    {
        $request_tickest = DB::select(
            "SELECT d.created_at, d.code, e.id, CONCAT(e.first_name, ' ', e.last_name, ' ', e.mothers_last_name) AS full_name
             FROM public.departures d, public.employees e
             WHERE d.id = :requestId AND d.employee_id = e.id",
            ['requestId' => $request->requestId]
        );

        if ($request_tickest) {

            $formattedDate = isset($request_tickest[0]->created_at)
                ? Carbon::parse($request_tickest[0]->created_at)->format('Y-m-d')
                : null;
            $lastNoteNumber = PettyCash::max('number_note');
            $number_note = $lastNoteNumber ? $lastNoteNumber + 1 : 1;
            $period = Management::latest()->first();
            $fund = Fund::latest()->first();
            $totalCost = 0;
            if (isset($request->transfers) && is_array($request->transfers)) {
                $totalCost = array_sum(array_column($request->transfers, 'cost'));
            }

            $notePettyCash = PettyCash::create([
                'number_note' => $number_note,
                'concept' => 'TRANSPORTE PERSONAL',
                'request_date' => $formattedDate,
                'delivery_date' => $formattedDate,
                'approximate_cost' => $totalCost,
                'replacement_cost' => $totalCost,
                'state' => 'Finalizado',
                'user_register' => $request_tickest[0]->id,
                'management_id' => $period->id,
                'fund_id' => $fund->id,
            ]);
            $product = Product::where('description', 'PASAJES')->first();
            if ($product) {
                $notePettyCash->products()->attach($product->id, [
                    'amount_request' => 1,
                    'number_invoice' => $request_tickest[0]->code,
                    'name_product' => $product->cost_object,
                    'supplier' => null,
                    'costDetails' => $totalCost,
                    'costFinal' => $totalCost,
                ]);
                $product->update([
                    'group_id' => 42,
                ]);
            }

            return response()->json(['message' => 'Petty cash updated successfully.'], 200);
        } else {
            return false;
        }
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

    public function print_Petty_Cash(PettyCash $notepettyCash)
    {
        $positionName = $this->titlePerson($notepettyCash->user_register);
        $user = User::where('employee_id', $notepettyCash->user_register)->first();

        $type = TypePetty::whereId($notepettyCash->type_cash_id)->first();

        if ($user) {
            $employee = Employee::find($notepettyCash->user_register);
            $products = $notepettyCash->products()->get()->map(function ($product) {
                return [
                    'description' => $product->description,
                    'quantity' => $product->pivot->amount_request,
                    'price' => $product->pivot->costDetails,
                ];
            });
            $totalDesembolso = $products->reduce(function ($carry, $product) {
                return $carry + ($product['quantity'] * $product['price']);
            }, 0);

            $data = [
                'title' => 'VALE DE ',
                'subtitle' => $type->description,
                'code' => $type->code,
                'number_note' => $notepettyCash->number_note,
                'date' => Carbon::now()->format('Y'),
                'employee' => $employee
                    ? "{$employee->first_name} {$employee->last_name} {$employee->mothers_last_name}"
                    : null,
                'position' => $positionName,
                'products' => $products,
                'concept' => $notepettyCash->concept,
                'total' => $totalDesembolso,
                'total_lit' => $this->numero_a_letras($totalDesembolso),
            ];

            $pdf = Pdf::loadView('NotePettyCash.NotePettyCash', $data);
            return $pdf->download('Vale_caja_chica.pdf');
        } else {
            $employee = Employee::where('id', $notepettyCash->user_register)->first();
            if ($employee) {
                $employee = Employee::find($notepettyCash->user_register);
                $products = $notepettyCash->products()->get()->map(function ($product) {
                    return [
                        'description' => $product->description,
                        'quantity' => $product->pivot->amount_request,
                        'price' => $product->pivot->costDetails,
                    ];
                });
                $totalDesembolso = $products->reduce(function ($carry, $product) {
                    return $carry + ($product['quantity'] * $product['price']);
                }, 0);

                $data = [
                    'title' => 'VALE DE ',
                    'subtitle' => $type->description,
                    'code' => $type->code,
                    'number_note' => $notepettyCash->number_note,
                    'date' => Carbon::now()->format('Y'),
                    'employee' => $employee
                        ? "{$employee->first_name} {$employee->last_name} {$employee->mothers_last_name}"
                        : null,
                    'position' => $positionName,
                    'products' => $products,
                    'concept' => $notepettyCash->concept,
                    'total' => $totalDesembolso,
                    'total_lit' => $this->numero_a_letras($totalDesembolso),
                ];

                $pdf = Pdf::loadView('NotePettyCash.NotePettyCash', $data);
                return $pdf->download('Vale_caja_chica.pdf');
            }
        }
    }

    public function verify(Request $request)
    {
        $materials = Material::where('stock', '>', 0)
            ->where('state', 'Habilitado')
            ->where('description', 'not like', '%CAJA CHICA%')
            ->get();
        $products = $request->input('product');
        $similarProducts = [];

        foreach ($products as $product) {
            $productDescription = $product['description'];
            foreach ($materials as $material) {
                $productDescLower = strtolower($productDescription);
                $materialDescLower = strtolower($material->description);


                if (str_contains($materialDescLower, $productDescLower)) {
                    $similarProducts[] = [
                        'product_description' => $productDescription,
                        'material_description' => $material->description,
                        'similarity' => 100,
                    ];
                    continue;
                }
                $levenshteinDistance = levenshtein($productDescLower, $materialDescLower);
                $maxLength = max(strlen($productDescLower), strlen($materialDescLower));
                $similarity = 1 - ($levenshteinDistance / $maxLength);
                if ($similarity >= 0.7) {
                    $similarProducts[] = [
                        'product_description' => $productDescription,
                        'material_description' => $material->description,
                        'similarity' => round($similarity * 100, 2),
                    ];
                }
            }
        }

        return response()->json(['similar_products' => $similarProducts]);
    }

    public function list_group()
    {
        $groups = Group::all()->map(function ($group) {
            return [
                'id' => $group->id,
                'details' => "{$group->code} - {$group->name_group}"
            ];
        });

        return $groups;
    }

    public function save_petty_cash(Request $request)
    {
        try {
            $fund = Fund::latest()->first();
            $pettyCash = PettyCash::find($request['requestId']);

            if (!$pettyCash) {
                return response()->json(['error' => 'PettyCash not found.'], 404);
            }

            if ($pettyCash->fund_id != $fund->id) {
                $pettyCash->fund_id = $fund->id;
            }

            $sum_product = 0;

            foreach ($request['products'] as $productData) {
                $product = Product::where('description', $productData['description'])->first();

                if (!$product) {
                    return response()->json(['error' => 'Product not found.'], 404);
                }
                $sum_product += $productData['total'];

                $pettyCash->products()->syncWithoutDetaching([
                    $product->id => [
                        'supplier' => $productData['supplier'],
                        'number_invoice' => $productData['numer_invoice'],
                        'quantity_delivered' => $productData['amount'],
                        'costDetailsFinal' => $productData['costUnit'],
                        'costTotal' => $productData['total'],
                    ],
                ]);
            }

            $pettyCash->replacement_cost = $sum_product;
            $pettyCash->delivery_date = today()->toDateString();
            $pettyCash->comment_recived = 'Aun no puede imprimir';
            $pettyCash->state = 'Aceptado';
            $pettyCash->save();

            return response()->json(['message' => 'Petty cash updated successfully.'], 200);
        } catch (\Exception $e) {
            logger($e);
            return response()->json([
                'error' => 'An error occurred while saving petty cash.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function change_petty_cash_to_replenishment_of_funds(Request $request)
    {
        return DB::transaction(function () use ($request) {

            $from = PettyCash::with('products')->findOrFail($request['requestId']);

            $to = PettyCash::create([
                'number_note' => $from->number_note,
                'concept' => $from->concept,
                'request_date' => $from->request_date,
                'delivery_date' => today()->toDateString(),
                'comment_recived' => 'Aun no puede imprimir',
                'approximate_cost' => $from->approximate_cost,
                'replacement_cost' => $from->replacement_cost,
                'state' => 'Aceptado',
                'user_register' => $from->user_register,
                'management_id' => $from->management_id,
                'fund_id' => $from->fund_id,
                'type_cash_id' => 2,
            ]);

            $pivotData = $from->products->mapWithKeys(function ($product) {
                return [
                    $product->id => [
                        'supplier' => $product->pivot->supplier,
                        'number_invoice'  => $product->pivot->number_invoice,
                        'quantity_delivered' => $product->pivot->quantity_delivered,
                        'costDetailsFinal' => $product->pivot->costDetailsFinal,
                        'costTotal' => $product->pivot->costTotal,
                        'amount_request' => $product->pivot->amount_request,
                        'name_product' => $product->pivot->name_product,
                        'costDetails' => $product->pivot->costDetails,
                        'costFinal' => $product->pivot->costFinal,
                    ]
                ];
            })->toArray();

            $from->delete();

            $to->products()->sync($pivotData);


            $sum_product = 0;

            foreach ($request['products'] as $productData) {
                $product = Product::where('description', $productData['description'])->first();

                if (!$product) {
                    return response()->json(['error' => 'Product not found.'], 404);
                }
                $sum_product += $productData['total'];

                $to->products()->syncWithoutDetaching([
                    $product->id => [
                        'supplier' => $productData['supplier'],
                        'number_invoice' => $productData['numer_invoice'],
                        'amount_request' => $productData['amount'],
                        'quantity_delivered' => $productData['amount'],
                        'costDetailsFinal' => $productData['costUnit'],
                        'costDetails' => $productData['costUnit'],
                        'costTotal' => $productData['total'],
                    ],
                ]);
            }

            $to->replacement_cost = $sum_product;
            $to->approximate_cost = $sum_product;

            $to->save();

            return response()->json([
                'from_note_id' => $from->id,
                'to_note_id' => $to->id,
                'products_copied' => count($pivotData),
            ], 201);
        });
    }

    public function print_Petty_Cash_discharge(PettyCash $notepettyCash)
    {
        $requests_date = $notepettyCash->request_date;
        $type = TypePetty::whereId($notepettyCash->type_cash_id)->first();

        $products = $notepettyCash->products()->get()->map(function ($product) {
            $group = Group::where('id', $product->group_id)->first();
            $codeGroup = $group ? $group->code : null;
            return [
                'supplier' => $product->pivot->supplier,
                'number_invoice' => $product->pivot->number_invoice,
                'description' => $product->description,
                'quantity' => $product->pivot->quantity_delivered,
                'code_group' => $codeGroup,
                'price' => $product->pivot->costDetailsFinal,
                'total' =>  $product->pivot->costTotal
            ];
        });

        $data = [
            'title' => 'DESCARGO DE ',
            'subtitle' => $type->description,
            'code' => $type->code,
            'number_note' => $notepettyCash->number_note,
            'date' => Carbon::now()->format('Y'),
            'request_date' => $requests_date,
            'concept' => $notepettyCash->concept,
            'products' => $products,
            'total_petty_cash' => $notepettyCash->approximate_cost
        ];
        $pdf = Pdf::loadView('NotePettyCash.NotePettyCashForm', $data);
        return $pdf->download('Vale_caja_chica_form_2.pdf');
    }

    private function numero_a_letras($numero)
    {
        $formatter = new NumberFormatter("es", NumberFormatter::SPELLOUT);

        $partes = explode('.', number_format($numero, 2, '.', ''));

        $entero = intval($partes[0]);
        $decimal = intval($partes[1]);

        $literal = ucfirst($formatter->format($entero));

        $literal .= " con " . str_pad($decimal, 2, "0", STR_PAD_RIGHT) . "/100";

        return $literal;
    }
}
