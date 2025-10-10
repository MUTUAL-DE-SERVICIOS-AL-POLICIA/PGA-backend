<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class KardexExport implements FromArray, WithHeadings, WithTitle
{
    protected $kardex;
    protected $totales;

    public function __construct($kardex, $totales)
    {
        $this->kardex = $kardex;
        $this->totales = $totales;
    }

    /**
     * Return the data for the Excel file
     *
     * @return array
     */
    public function array(): array
    {
        $kardexData = [];
        foreach ($this->kardex as $movement) {
            $kardexData[] = [
                'Date' => $movement['date'],
                'Description' => $movement['description'],
                'Entradas' => $movement['entradas'],
                'Salidas' => $movement['salidas'],
                'Stock Fisico' => $movement['stock_fisico'],
                'Cost Unit' => $movement['cost_unit'],
                'Importe Entrada' => $movement['importe_entrada'],
                'Importe Salida' => $movement['importe_salida'],
                'Importe Saldo' => $movement['importe_saldo'],
            ];
        }

        $kardexData[] = [
            'Date' => 'Totales',
            'Description' => '',
            'Entradas' => $this->totales['entradas'],
            'Salidas' => $this->totales['salidas'],
            'Stock Fisico' => $this->totales['stock_fisico'],
            'Cost Unit' => '',
            'Importe Entrada' => $this->totales['importe_entrada'],
            'Importe Salida' => $this->totales['importe_salida'],
            'Importe Saldo' => $this->totales['importe_saldo'],
        ];

        return $kardexData;
    }

    /**
     * Return the headings for the Excel file
     *
     * @return array
     */
    public function headings(): array
    {
        return [
            'Fechas',
            'Detalles',
            'Entradas',
            'Salidas',
            'Stock Fisico',
            'Costo Unitario',
            'Importe Entrada',
            'Importe Salida',
            'Importe Saldo'
        ];
    }

    /**
     * Set the title for the Excel sheet
     *
     * @return string
     */
    public function title(): string
    {
        return 'Kardex de Existencias';
    }
}
