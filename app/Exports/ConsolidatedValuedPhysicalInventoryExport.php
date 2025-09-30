<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ConsolidatedValuedPhysicalInventoryExport implements FromCollection, WithHeadings, WithColumnFormatting
{
    protected $results;

    public function __construct($results)
    {
        $this->results = $results;
    }

    public function collection()
    {
        $data = [];

        foreach ($this->results as $group) {
            $data[] = [
                'grupo' => $group['grupo'],
                'codigo_grupo' => $group['codigo_grupo'],
                'saldo_anterior_cantidad' => $group['resumen']['saldo_anterior_cantidad'],
                'saldo_anterior_total' => $group['resumen']['saldo_anterior_total'],
                'entradas_cantidad' => ($group['resumen']['entradas_cantidad'] - $group['resumen']['saldo_anterior_cantidad']),
                'entradas_total' => number_format(($group['resumen']['entradas_total'] - $group['resumen']['saldo_anterior_total']), 2),
                'salidas_cantidad' => $group['resumen']['salidas_cantidad'],
                'salidas_total' => $group['resumen']['salidas_total'],
                'saldo_final_cantidad' => $group['resumen']['saldo_final_cantidad'],
                'saldo_final_total' => $group['resumen']['saldo_final_total'],
            ];
        }

        return collect($data);
    }

    public function headings(): array
    {
        return [
            'GRUPO',
            'CÓDIGO GRUPO',
            'SALDO INICIAL FÍSICO',
            'SALDO INICIAL VALOR BS.',
            'ENTRADAS FÍSICO',
            'ENTRADAS VALOR BS.',
            'SALIDAS FÍSICO',
            'SALIDAS VALOR BS.',
            'SALDOS FÍSICO',
            'SALDOS VALOR BS.',
        ];
    }

    public function columnFormats(): array
    {
        return [
            
            'D' => NumberFormat::FORMAT_NUMBER_00,  
            
            'F' => NumberFormat::FORMAT_NUMBER_00,  
            
            'H' => NumberFormat::FORMAT_NUMBER_00, 
            
        ];
    }
}
