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

            <table class="table-info w-100 m-b-10 uppercase text-xs">
                <thead>
                    <tr>
                        <th class="text-center bg-grey-darker text-white" rowspan="2">CÓDIGO</th>
                        <th class="text-center bg-grey-darker text-white border-left-white" rowspan="2">DETALLE</th>
                        <th class="text-center bg-grey-darker text-white border-left-white" colspan="2">SAL. GES. ANT.</th>
                        <th class="text-center bg-grey-darker text-white border-left-white" colspan="2">COM. GES. ACT.</th>
                        <th class="text-center bg-grey-darker text-white border-left-white" colspan="2">SAL. GES. ACT.</th>
                    </tr>
                    <tr>
                        <th class="text-center bg-grey-darker text-white border-left-white">FISICO</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">VALOR BS.</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">FISICO</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">VALOR BS.</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">FISICO</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">VALOR BS.</th>
                    </tr>
                </thead>
                <tbody class="table-striped">
                    @foreach ($results as $group)
                    <tr>
                        <td class="text-left">{{ $group['codigo_grupo'] }}</td>
                        <td class="text-left">{{ $group['grupo'] }}</td>
                        <td class="text-left">{{ $group['total_cantidad_anterior'] }}</td>
                        <td class="text-left">{{ $group['total_presupuesto_anterior'] }}</td>
                        <td class="text-left">{{ $group['total_cantidad'] }}</td>
                        <td class="text-left">{{ $group['total_presupuesto'] }}</td>
                        <td class="text-left">{{ $group['cantidad_entregada'] }}</td>
                        <td class="text-left">{{ $group['suma_cost_detail'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
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