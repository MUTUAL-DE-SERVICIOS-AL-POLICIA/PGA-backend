<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Illuminate\Http\Request;
use App\Http\Requests\MaterialRequest;
use App\Models\Entrie_Material;
use App\Models\Group;
use App\Models\Note_Entrie;

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

            // Determinamos un nuevo code_material único
            $newCorrelativo = 1;
            do {
                $newCodeMaterial = $group->code . $newCorrelativo;
                $exists = Material::where('code_material', $newCodeMaterial)->exists();
                $newCorrelativo++;
            } while ($exists);

            // Formateamos la descripción en mayúsculas
            $data['description'] = strtoupper($data['description']);

            // Creamos el nuevo material
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

        $material->code_material = $request['code_material'];
        $material->description = $request['description'];
        $material->unit_material = $request['unit_material'];

        $material->save();
        return response()->json(['status' => true, 'data' => $material], 200);
    }

    public function fixDuplicatedCodes()
    {
        try {

            $duplicatedCodes = Material::select('code_material')
                ->groupBy('code_material')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('code_material');

            if ($duplicatedCodes->isEmpty()) {
                return response()->json(['message' => 'No hay códigos duplicados'], 200);
            }

            $fixed = [];

            foreach ($duplicatedCodes as $code) {

                $materials = Material::where('code_material', $code)->orderBy('id')->get();

                foreach ($materials as $material) {
                    $group = $material->group;

                    if (!$group) continue; 

                    $newCorrelativo = 1;
                    do {
                        $newCodeMaterial = $group->code . $newCorrelativo;
                        $exists = Material::where('code_material', $newCodeMaterial)->exists();
                        $newCorrelativo++;
                    } while ($exists);

                    $material->code_material = $newCodeMaterial;
                    $material->save();

                    $fixed[] = [
                        'material_id' => $material->id,
                        'old_code' => $code,
                        'new_code' => $newCodeMaterial
                    ];
                }
            }

            return response()->json([
                'message' => 'Códigos duplicados corregidos',
                'fixed' => $fixed
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error al corregir duplicados: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function NameMaterialCorrect(){        
        $notes = Entrie_Material::where('material_id', 49)->get();
        
        foreach ($notes as $note) {
            if($note->name_material === '34600 - MINERALES (CAJA CHICA)'){
                $note->name_material = '34700 - MINERALES (CAJA CHICA)';
                $note->save();
            }
        }
        return $notes;
    }   
}
