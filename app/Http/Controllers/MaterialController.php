<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Illuminate\Http\Request;
use App\Http\Requests\MaterialRequest;
use App\Models\Group;

class MaterialController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $materials = Material::all();
        return response()->json([
            'status' => true,
            'total' => $materials->count(),
            'materials' => $materials
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'group_id' => 'required|exists:groups,id',
                'description' => 'required|string',
                'unit_material' => 'required|string',
                'barcode' => 'nullable|string',
                'stock' => 'required|integer',
                'state' => 'required|string',
                'min' => 'required|integer',
                'type' => 'required|string',
            ]);

            // Buscamos el grupo especificado
            $group = Group::find($data['group_id']);

            if (!$group) {
                return response()->json(['error' => 'Group not found'], 404);
            }

            // Obtenemos el último material que no sea de tipo "Caja Chica" y pertenece al grupo
            $lastMaterial = Material::where('group_id', $data['group_id'])
                ->where('type', '!=', '%Caja Chica%')
                ->orderBy('id', 'desc')
                ->first();

            // Determinamos el nuevo correlativo
            if ($lastMaterial) {
                // Extraemos el número del código de material anterior
                $lastCorrelativo = (int) str_replace($group->code, '', $lastMaterial->code_material);

                $newCorrelativo = $lastCorrelativo + 1;
            } else {
                // Si no hay materiales previos, comenzamos desde 1
                $newCorrelativo = 1;
            }

            // Generamos el nuevo código de material
            $newCodeMaterial = $group->code . $newCorrelativo;

            $data['description'] = strtoupper($data['description']);

            $material = Material::create([
                'group_id' => $data['group_id'],
                'code_material' => $newCodeMaterial,
                'description' => $data['description'],
                'unit_material' => $data['unit_material'],
                'barcode' => $data['barcode'] ?? '0',
                'stock' => $data['stock'],
                'state' => $data['state'],
                'min' => $data['min'],
                'type' => $data['type'],
            ]);

            return response()->json([
                'material' => $material,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Ocurrió un error: ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {

        $material = Material::findOrFail($id);


        $entries = $material->noteEntries()->withPivot('amount_entries', 'request', 'cost_unit', 'cost_total')->get();


        $response = $entries->map(function ($entry) {
            return [
                'note_id' => $entry->id,
                'note_number' => $entry->number_note,
                'date' => $entry->delivery_date,
                'amount_entries' => $entry->pivot->amount_entries,
                'request' => $entry->pivot->request,
                'cost_unit' => $entry->pivot->cost_unit,
                'cost_total' => $entry->pivot->cost_total,
            ];
        });


        return response()->json([
            'material_id' => $material->id,
            'material_description' => $material->description,
            'entries' => $response,
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id) {}

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $material = Material::find($id);
        if ($request['state'] == "Habilitado") {
            $upState = "Inhabilitado";
        } else {
            if ($material->stock > 0) {
                $upState = "Habilitado";
            } else {
                return response()->json([
                    'status' => false,
                    'message' => "Debe existir Stock para poder Habilitar el material"
                ], 400);
            }
        }
        $material->state = $upState;
        $material->save();
        return response()->json(['status' => true, 'data' => $material], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Material $material)
    {
        $material->delete();
        return response()->json(['message' => 'Eliminado'], 200);
    }

    public function materialslist(Request $request)
    {
        $page = max(0, (int) $request->get('page', 0));
        $limit = max(1, (int) $request->get('limit', Material::count()));
        $start = $page * $limit;

        $search = $request->input('search', '');
        $stateFilter = $request->input('state', ''); 

        $query = Material::query()->orderBy('state')->orderBy('stock');
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', '%' . $search . '%')
                    ->orWhere('code_material', 'like', '%' . $search . '%');
            });
        }

        if (!empty($stateFilter) && in_array($stateFilter, ['Habilitado', 'Inhabilitado'])) {
            $query->where('state', $stateFilter);
        }

        $totalMaterials = $query->count();

        $materials = $query->skip($start)->take($limit)->get();

        foreach ($materials as $material) {
            if ($material->stock <= 0 && $material->state === 'Habilitado') {
                $material->state = 'Inhabilitado';
                $material->save();
            }
        }

        return response()->json([
            'status' => 'success',
            'total' => $totalMaterials,
            'page' => $page,
            'last_page' => ceil($totalMaterials / $limit),
            'materials' => $materials,
        ]);
    }


    public function list_materials_pva()
    {
        $query = Material::where('state', 'Habilitado')->where('description', 'not like', '%CAJA CHICA%')->get();
        return $query;
    }

    public function updateName(Request $request, string $id)
    {
        $material = Material::find($id);


        $material->description = $request['description'];
        $material->unit_material = $request['unit_material'];

        $material->save();
        return response()->json(['status' => true, 'data' => $material], 200);
    }
}
