<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\FromArray;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ValuedPhysicalExport implements FromArray, WithStyles
{
    protected $data;
    protected $sheetData = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->data as $group) {

            $rows[] = ['Grupo:', $group['grupo']];
            $rows[] = ['Código:', $group['codigo_grupo']];
            $rows[] = []; 

            $rows[] = [
                'CÓDIGO', 'DESCRIPCIÓN', 'UNIDAD',
                'SALDO INICIAL CANT', 'SALDO INICIAL PRECIO', 'SALDO INICIAL TOTAL',
                'ENTRADA CANT', 'ENTRADA PRECIO', 'ENTRADA TOTAL',
                'SALIDA CANT', 'SALIDA PRECIO', 'SALIDA TOTAL',
                'SALDO FINAL CANT', 'SALDO FINAL PRECIO', 'SALDO FINAL TOTAL'
            ];

            foreach ($group['materiales'] as $material) {
                $saldoAnteriores = $material['saldo_anterior'] ?? [];
                $lotes = $material['lotes'] ?? [];
                $maxRows = max(count($saldoAnteriores), count($lotes));

                for ($i = 0; $i < $maxRows; $i++) {
                    $saldo = $saldoAnteriores[$i] ?? ['cantidad_restante' => '', 'precio_unitario' => '', 'valor_restante' => ''];
                    $lote = $lotes[$i] ?? [
                        'cantidad_inicial' => '', 'precio_unitario' => '', 'cantidad_1' => '',
                        'cantidad_restante' => '', 'cantidad_2' => '', 'cantidad_3' => ''
                    ];

                    $row = [];

                    if ($i == 0) {
                        $row[] = $material['codigo_material'];
                        $row[] = $material['nombre_material'];
                        $row[] = $material['unidad_material'];
                    } else {
                        $row[] = '';
                        $row[] = '';
                        $row[] = '';
                    }

                    $row[] = $saldo['cantidad_restante'];
                    $row[] = number_format((float)($saldo['precio_unitario'] ?? 0), 2);
                    $row[] = number_format((float)($saldo['valor_restante'] ?? 0), 2);

                    $row[] = $lote['cantidad_inicial'];
                    $row[] = number_format((float)$lote['precio_unitario'], 2);
                    $row[] = number_format((float)$lote['cantidad_1'], 2);

                    $row[] = $lote['cantidad_inicial'] - $lote['cantidad_restante'];
                    $row[] = number_format((float)$lote['precio_unitario'], 2);
                    $row[] = number_format((float)$lote['cantidad_2'], 2);

                    $row[] = $lote['cantidad_restante'];
                    $row[] = number_format((float)$lote['precio_unitario'], 2);
                    $row[] = number_format((float)$lote['cantidad_3'], 2);

                    $rows[] = $row;
                }
            }

            $resumen = $group['resumen'];
            $rows[] = [
                'TOTAL BS', '', '',
                $resumen['saldo_anterior_cantidad'], '', number_format($resumen['saldo_anterior_total'], 2),
                $resumen['entradas_cantidad'], '', number_format($resumen['entradas_total'] - $resumen['saldo_anterior_total'], 2),
                $resumen['salidas_cantidad'], '', number_format($resumen['salidas_total'], 2),
                $resumen['saldo_final_cantidad'], '', number_format($resumen['saldo_final_total'], 2)
            ];

            $rows[] = []; 
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:O1000')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        $sheet->getStyle('A')->getFont()->setBold(true);
        $sheet->getStyle('D4:P4')->getFont()->setBold(true); 
        $sheet->getStyle('A')->getAlignment()->setWrapText(true);

        return [];
    }
}
