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

        .col-detalle {
            width: 250px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .col-unit {
            width: 80px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
                        <!-- Espacio para el código de barras -->
                    </table>
                </th>
            </tr>
        </table>
        <hr class="m-b-10" style="margin-top: 0; padding-top: 0;">
        <div class="block">
            <div class="leading-tight text-sm text-center m-b-10">{{ $title }}</div>
            <div class="leading-tight text-xxxl text-center m-b-10">LA PAZ, DEL {{ strtoupper($date_note) }} AL {{ $fecha_actual }}</div>
            @foreach ($results as $index => $result)
            <div class="leading-tight text-xxl text-left m-b-10">{{ $result['codigo_grupo'] }}, GRUPO: {{ strtoupper($result['grupo']) }}</div>
            <table class="table-info w-100 m-b-10 uppercase text-xs">
                <thead>
                    <tr>
                        <th class="text-center bg-grey-darker text-white" rowspan="2">CÓDIGO</th>
                        <th class="text-center bg-grey-darker text-white border-left-white col-detalle" rowspan="2">DETALLE</th>
                        <th class="text-center bg-grey-darker text-white border-left-white col-unit" rowspan="2">UNIDAD</th>
                        <th class="text-center bg-grey-darker text-white border-left-white" colspan="3">ENTRADAS</th>
                        <th class="text-center bg-grey-darker text-white border-left-white" colspan="3">CANTIDADES</th>
                        <th class="text-center bg-grey-darker text-white border-left-white" colspan="3">SALDOS</th>
                    </tr>
                    <tr>
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
                    @foreach ($result['materiales'] as $material)
                    @php
                        $totalEntradas = 0;
                        $totalEntradasCosto = 0;
                        $totalCantidades = 0;
                        $totalCantidadesCosto = 0;
                        $totalSaldos = 0;
                        $totalSaldosCosto = 0;
                    @endphp
                    @foreach ($material['lotes'] as $lote)
                    @php
                        $cantidadEntradas = $lote['cantidad_inicial'];
                        $cantidadRestante = $lote['cantidad_restante'];
                        $precioUnitario = $lote['precio_unitario'];

                        $totalEntradas += $cantidadEntradas;
                        $totalEntradasCosto += $cantidadEntradas * $precioUnitario;

                        $totalCantidades += ($cantidadEntradas - $cantidadRestante);
                        $totalCantidadesCosto += ($cantidadEntradas - $cantidadRestante) * $precioUnitario;

                        $totalSaldos += $cantidadRestante;
                        $totalSaldosCosto += $cantidadRestante * $precioUnitario;
                    @endphp
                    <tr>
                        @if($loop->first)
                        <td class="text-left" rowspan="{{ count($material['lotes']) }}">{{ $material['codigo_material'] }}</td>
                        <td class="text-left" rowspan="{{ count($material['lotes']) }}">{{ $material['nombre_material'] }}</td>
                        <td class="text-left" rowspan="{{ count($material['lotes']) }}">{{ $material['unidad_material'] }}</td>
                        @endif
                        <td class="text-center">{{ $cantidadEntradas }}</td>
                        <td class="text-right">{{ number_format($precioUnitario, 2) }}</td>
                        <td class="text-right">{{ number_format($cantidadEntradas * $precioUnitario, 2) }}</td>
                        <td class="text-center">{{ $cantidadEntradas - $cantidadRestante }}</td>
                        <td class="text-right">{{ number_format($precioUnitario, 2) }}</td>
                        <td class="text-right">{{ number_format(($cantidadEntradas - $cantidadRestante) * $precioUnitario, 2) }}</td>
                        <td class="text-center">{{ $cantidadRestante }}</td>
                        <td class="text-right">{{ number_format($precioUnitario, 2) }}</td>
                        <td class="text-right">{{ number_format($cantidadRestante * $precioUnitario, 2) }}</td>
                    </tr>
                    @endforeach
                    <!-- <tr>
                        <td colspan="3" class="text-right font-bold">SUB-TOTAL</td>
                        @php
                        $averageEntradas = $totalEntradas > 0 ? $totalEntradasCosto / $totalEntradas : 0;
                        $averageCantidades = $totalCantidades > 0 ? $totalCantidadesCosto / $totalCantidades : 0;
                        $averageSaldos = $totalSaldos > 0 ? $totalSaldosCosto / $totalSaldos : 0;
                        @endphp
                        <td class="text-center">{{ $totalEntradas }}</td>
                        <td class="text-right">{{ number_format($averageEntradas, 2) }}</td>
                        <td class="text-right">{{ number_format($totalEntradasCosto, 2) }}</td>
                        <td class="text-center">{{ $totalCantidades }}</td>
                        <td class="text-right">{{ number_format($averageCantidades, 2) }}</td>
                        <td class="text-right">{{ number_format($totalCantidadesCosto, 2) }}</td>
                        <td class="text-center">{{ $totalSaldos }}</td>
                        <td class="text-right">{{ number_format($averageSaldos, 2) }}</td>
                        <td class="text-right">{{ number_format($totalSaldosCosto, 2) }}</td>
                    </tr> -->
                    @endforeach
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