<?php

namespace App\Console\Commands;

use App\Models\Entrie_Material;
use App\Models\Request_Material;
use Illuminate\Console\Command;

class MaterialModificated extends Command
{
    protected $signature = 'app:material-modificated';
    protected $description = 'Ajusta materiales mal cargados a caja chica';

    public function handle()
    {
        Entrie_Material::whereIn('id', [454, 445, 448, 463, 460, 469, 494, 504, 510, 520])
            ->update([
                'material_id'   => 273,
                'name_material' => '34600 - PRODUCTOS METALICOS (CAJA CHICA)',
            ]);

        Request_Material::whereIn('id', [400, 397, 638, 688, 720, 803, 1018, 1034, 1048, 1075])
            ->update([
                'material_id'   => 273,
                'name_material' => '34600 - PRODUCTOS METALICOS (CAJA CHICA)',
            ]);

        Entrie_Material::where('id', 857)->update([
            'material_id'   => 56,
            'name_material' => '39500 - ÚTILES DE ESCRITORIO Y OFICINA (CAJA CHICA)',
        ]);

        Request_Material::where('id', 2535)->update([
            'material_id'   => 56,
            'name_material' => '39500 - ÚTILES DE ESCRITORIO Y OFICINA (CAJA CHICA)',
        ]);

        $this->info('Materiales actualizados correctamente.');
        return Command::SUCCESS;
    }
}
