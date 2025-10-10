<?php

use \Milon\Barcode\DNS2D;

if (!extension_loaded('intl')) {
    die('La extensión Intl não está habilitada.');
}
$formatter = new IntlDateFormatter(
    'es_ES',
    IntlDateFormatter::LONG,
    IntlDateFormatter::NONE,
    null,
    null,
    'd \'DE\' MMMM \'DE\' y'
);
$fecha_actual = $formatter->format(new DateTime());

$fecha_actual = strtoupper($fecha_actual);

$date = '12/01/2024';
$dns = new DNS2D();
?>
<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PLATAFORMA VIRTUAL ADMINISTRATIVA - MUSERPOL </title>
    <link rel="stylesheet" href="{{ public_path('/css/material-request.min.css') }}" media="all" />

    <style>
        @page {
            size: letter landscape;
            margin: 1.5cm;
        }

        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            font-size: 9px;
        }

        table,
        th,
        td {
            font-size: 9px;
        }

        .content {
            flex: 1;
        }

        .footer {
            text-align: left;
            margin-top: auto;
            width: 100%;
        }

        .footer td {
            padding: 5px;
        }

        .detalle-columna {
            width: 250px;
            word-wrap: break-word;
        }

        .subtotal-row {
            background-color: #f2f2f2;
            font-weight: bold;
            border-top: 2px solid #000;
        }

        .text-xs {
            font-size: 8px !important;
        }

        .text-sm {
            font-size: 9px !important;
        }

        .text-md {
            font-size: 10px !important;
        }

        .text-lg {
            font-size: 11px !important;
        }

        .text-xl {
            font-size: 12px !important;
        }
    </style>
</head>

<body>
    <div class="content">
        <table class="w-100 uppercase">
            <tr>
                <th class="w-25 text-left no-paddings no-margins align-middle">
                    <div class="text-left">
                        <img src="{{ public_path('/img/logo.png') }}" class="w-40">
                    </div>
                </th>
                <th class="w-50 align-top">
                    <div class="leading-tight text-xs">
                        <div>MUTUAL DE SERVICIOS AL POLICÍA "MUSERPOL"</div>
                        <div>DIRECCIÓN DE ASUNTOS ADMINISTRATIVOS</div>
                        <div>UNIDAD ADMINISTRATIVA</div>
                    </div>
                </th>
                <th class="w-25 no-padding no-margins align-top">
                    <table class="table-code no-padding no-margins text-xxxs uppercase">
                    </table>
                </th>
            </tr>
        </table>
        <hr class="m-b-10" style="margin-top: 0; padding-top: 0;">
        <div class="block">
            <div class="leading-tight text-sm text-center m-b-10">{{ $title }}</div>
            <div class="leading-tight text-xxxl text-center m-b-10">LA PAZ, DEL {{ strtoupper($date_note) }} AL {{ $fecha_actual }}</div>
            @foreach ($result as $index => $result)
            <div class="leading-tight text-xxl text-left m-b-10">{{ $result['codigo_grupo'] }}, GRUPO: {{ strtoupper($result['grupo']) }}</div>
            <table class="table-info w-100 m-b-10 uppercase text-xs">
                <thead>
                    <tr>
                        <th class="text-center bg-grey-darker text-white" rowspan="2">CÓDIGO</th>
                        <th class="text-center bg-grey-darker text-white border-left-white detalle-columna" rowspan="2" style="width: 250px;">DETALLE</th>
                        <th class="text-center bg-grey-darker text-white border-left-white" rowspan="2">UNIDAD</th>
                        <th class="text-center bg-grey-darker text-white border-left-white" colspan="3">SAL. ANT</th>
                        <th class="text-center bg-grey-darker text-white border-left-white" colspan="3">ENTRADAS</th>
                        <th class="text-center bg-grey-darker text-white border-left-white" colspan="3">CANTIDADES</th>
                        <th class="text-center bg-grey-darker text-white border-left-white" colspan="3">SALDOS</th>
                    </tr>
                    <tr>
                        <th class="text-center bg-grey-darker text-white border-left-white">EXIS. ALM.</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">COST. UNI.</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">COST. TOTAL</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">EXIS. ALM.</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">COST. UNI.</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">COST. TOTAL</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">SAL. EXIS. ALM</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">COST. UNI.</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">COST. TOTAL</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">SAL. EXIS. ALM</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">COST. UNI.</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">SALDO</th>
                    </tr>
                </thead>

                <tbody class="table-striped">
                    @php
                    $groupTotalSaldo = 0;
                    @endphp

                    @foreach ($result['materiales'] as $material)
                    @php
                    
                    $m = [
                        'sa_cant' => 0, 'sa_total' => 0,           
                        'ent_cant' => 0, 'ent_total' => 0,         
                        'sal_cant' => 0, 'sal_total' => 0,         
                        'saldo_cant' => 0, 'saldo_total' => 0,    
                    ];
                    

                    $saldos = $material['saldo_anterior'] ?? [];
                    $lotes = $material['lotes'] ?? [];
                    $maxFilas = max(count($saldos), count($lotes));

                    $totalEntradas = 0;
                    $totalEntradasCosto = 0;
                    $totalCantidades = 0;
                    $totalCantidadesCosto = 0;
                    $totalSaldos = 0;
                    $totalSaldosCosto = 0;
                    @endphp

                    @for ($i = 0; $i < $maxFilas; $i++)
                        @php
                        $sa=$saldos[$i] ?? ['cantidad_restante'=> '', 'precio_unitario' => '', 'valor_restante' => ''];
                        $lt = $lotes[$i] ?? ['cantidad_inicial' => '', 'cantidad_restante' => '', 'precio_unitario' => '', 'cantidad_1' => '', 'cantidad_2' => '', 'cantidad_3' => ''];

                        $cantidadEntradas = $lt['cantidad_inicial'] ?? '';
                        $cantidadRestante = $lt['cantidad_restante'] ?? '';
                        $precioUnitario = $lt['precio_unitario'] ?? '';
                        $cantidad_1 = $lt['cantidad_1'] ?? '';
                        $cantidad_2 = $lt['cantidad_2'] ?? '';
                        $cantidad_3 = $lt['cantidad_3'] ?? '';

                        if (is_numeric($cantidadEntradas)) $totalEntradas += $cantidadEntradas;
                        if (is_numeric($cantidad_1)) $totalEntradasCosto += $cantidad_1;

                        if (is_numeric($cantidadEntradas) && is_numeric($cantidadRestante)) {
                            $salidas = $cantidadEntradas - $cantidadRestante;
                            $totalCantidades += $salidas;
                            if (is_numeric($cantidad_2)) $totalCantidadesCosto += $cantidad_2;
                        } else {
                            $salidas = '';
                        }

                        if (is_numeric($cantidadRestante)) $totalSaldos += $cantidadRestante;
                        if (is_numeric($cantidad_3)) $totalSaldosCosto += $cantidad_3;

                        
                        if (is_numeric($sa['cantidad_restante'])) $m['sa_cant']   += $sa['cantidad_restante'];
                        if (is_numeric($sa['valor_restante']))   $m['sa_total']  += $sa['valor_restante'];

                        if (is_numeric($cantidadEntradas))       $m['ent_cant']  += $cantidadEntradas;
                        if (is_numeric($cantidad_1))             $m['ent_total'] += $cantidad_1;

                        if ($salidas !== '' && is_numeric($salidas)) $m['sal_cant'] += $salidas;
                        if (is_numeric($cantidad_2))                 $m['sal_total'] += $cantidad_2;

                        if (is_numeric($cantidadRestante))       $m['saldo_cant']  += $cantidadRestante;
                        if (is_numeric($cantidad_3))             $m['saldo_total'] += $cantidad_3;
                        
                        @endphp

                        <tr>
                            @if ($i === 0)
                            <td class="text-left">{{ $material['codigo_material'] }}</td>
                            <td class="text-left">{{ $material['nombre_material'] }}</td>
                            <td class="text-left">{{ $material['unidad_material'] }}</td>
                            @else
                            <td class="text-left" style="border-bottom: none;"></td>
                            <td class="text-left" style="border-bottom: none;"></td>
                            <td class="text-left" style="border-bottom: none;"></td>
                            @endif

                            <td class="text-center">{{ $sa['cantidad_restante'] !== '' ? $sa['cantidad_restante'] : '' }}</td>
                            <td class="text-right">{{ $sa['precio_unitario'] !== '' ? number_format($sa['precio_unitario'], 2) : '' }}</td>
                            <td class="text-right">{{ $sa['valor_restante'] !== '' ? number_format($sa['valor_restante'], 2) : '' }}</td>

                            <td class="text-center">{{ $cantidadEntradas }}</td>
                            <td class="text-right">{{ $precioUnitario !== '' ? number_format($precioUnitario, 2) : '' }}</td>
                            <td class="text-right">{{ $cantidad_1 !== '' ? number_format($cantidad_1, 2) : '' }}</td>

                            <td class="text-center">{{ is_numeric($cantidadEntradas) && is_numeric($cantidadRestante) ? $cantidadEntradas - $cantidadRestante : '' }}</td>
                            <td class="text-right">{{ $precioUnitario !== '' ? number_format($precioUnitario, 2) : '' }}</td>
                            <td class="text-right">{{ $cantidad_2 !== '' ? number_format($cantidad_2, 2) : '' }}</td>

                            <td class="text-center">{{ $cantidadRestante }}</td>
                            <td class="text-right">{{ $precioUnitario !== '' ? number_format($precioUnitario, 2) : '' }}</td>
                            <td class="text-right">{{ $cantidad_3 !== '' ? number_format($cantidad_3, 2) : '' }}</td>
                        </tr>
                        @endfor

                        <tr>
                            <td colspan="15" style="border-top: 1px solid black;"></td>
                        </tr>
                        <tr class="subtotal-row">
                            <td colspan="3" class="text-left font-bold">SUB-TOTAL</td>

                            {{-- Saldo Anterior --}}
                            <td class="text-center">{{ $m['sa_cant'] }}</td>
                            <td class="text-right"></td>
                            <td class="text-right">{{ number_format($m['sa_total'], 2) }}</td>

                            {{-- Entradas --}}
                            <td class="text-center">{{ $m['ent_cant'] }}</td>
                            <td class="text-right"></td>
                            <td class="text-right">{{ number_format($m['ent_total'], 2) }}</td>

                            {{-- Salidas --}}
                            <td class="text-center">{{ $m['sal_cant'] }}</td>
                            <td class="text-right"></td>
                            <td class="text-right">{{ number_format($m['sal_total'], 2) }}</td>

                            {{-- Saldos Finales --}}
                            <td class="text-center">{{ $m['saldo_cant'] }}</td>
                            <td class="text-right"></td>
                            <td class="text-right">{{ number_format($m['saldo_total'], 2) }}</td>
                        </tr>
                        @endforeach

                        @php
                        $totalSaldoAnteriorCantidad = 0;
                        $totalSaldoAnteriorTotal = 0;

                        foreach ($result['materiales'] as $material) {
                            foreach ($material['saldo_anterior'] ?? [] as $sa) {
                                $totalSaldoAnteriorCantidad += $sa['cantidad_restante'] ?? 0;
                                $totalSaldoAnteriorTotal += $sa['valor_restante'] ?? 0;
                            }
                        }
                        @endphp

                        <tr class="subtotal-row">
                            <td colspan="3" class="text-right font-bold">TOTAL EN BS :</td>

                            {{-- Saldo Anterior --}}
                            <td class="text-center">{{ $result['resumen']['saldo_anterior_cantidad'] ?? '' }}</td>
                            <td class="text-right"></td>
                            <td class="text-right">{{ isset($result['resumen']['saldo_anterior_total']) ? number_format($result['resumen']['saldo_anterior_total'], 2) : '' }}</td>

                            {{-- Entradas --}}
                            <td class="text-center">{{ $result['resumen']['entradas_cantidad'] ?? '' }}</td>
                            <td class="text-right"></td>
                            <td class="text-right">{{ isset($result['resumen']['entradas_total']) ? number_format($result['resumen']['entradas_total'] - $result['resumen']['saldo_anterior_total'], 2) : '' }}</td>

                            {{-- Salidas --}}
                            <td class="text-center">{{ $result['resumen']['salidas_cantidad'] ?? '' }}</td>
                            <td class="text-right"></td>
                            <td class="text-right">{{ isset($result['resumen']['salidas_total']) ? number_format($result['resumen']['salidas_total'], 2) : '' }}</td>

                            {{-- Saldos Finales --}}
                            <td class="text-center">{{ $result['resumen']['saldo_final_cantidad'] ?? '' }}</td>
                            <td class="text-right"></td>
                            <td class="text-right">{{ isset($result['resumen']['saldo_final_total']) ? number_format($result['resumen']['saldo_final_total'], 2) : '' }}</td>
                        </tr>

                </tbody>
            </table>
            <div style="margin-top: 20px;"></div>
            @endforeach
        </div>
    </div>
    <table>
        <tr>
            <td class="text-xxxs" align="left">
                @if (env("APP_ENV") == "production")
                PLATAFORMA VIRTUAL ADMINISTRATIVA
                @else
                VERSIÓN DE PRUEBAS
                @endif
            </td>
            <td class="child" align="right">
                <img src="data:image/png;base64, {{ $dns->getBarcodePNG(bcrypt($date . ' ' . gethostname() . ' ' . env('APP_URL')), 'PDF417') }}" alt="BARCODE!!!" style="height: 22px; width: 125px;" />
            </td>
        </tr>
    </table>

</body>

</html>
