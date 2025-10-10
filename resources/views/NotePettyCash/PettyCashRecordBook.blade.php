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
            font-size: 12px;
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

        thead th {
            font-size: 10px;
            line-height: 1.2;
        }

        .text-xxxs {
            font-size: 8px;
        }

        .text-white {
            color: white;
        }

        .border-left-white {
            border-left: 1px solid white;
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
    </div>

    <div class="block">
        <div class="leading-tight text-sm text-center m-b-10">{{ $title }}</div>
        <div class="leading-tight text-xxxl text-center m-b-10">(EXPRESADO EN BOLIVIANOS)</div>
        <table class="table-code w-100 m-b-10 uppercase text-xs">
            <tbody>
                <tr>
                    <td class="w-30 text-center bg-grey-darker text-white">FECHA Y LUGAR</td>
                    <td class="w-70 p-l-5"> LA PAZ, {{ $date }}</td>
                </tr>
                <tr>
                    <td class="w-30 text-center bg-grey-darker text-white">NOMBRE Y APELLIDO DEL CUSTODIO</td>
                    <td class="w-70 p-l-5"> {{ $name }}</td>
                </tr>
                <tr>
                    <td class="w-30 text-center bg-grey-darker text-white">ÁREA/UNIDAD</td>
                    <td class="w-70 p-l-5"> {{ $area }}</td>
                </tr>
            </tbody>
        </table>

        <table class="table-info w-100 m-b-10 uppercase text-xs">
            <thead>
                <tr>
                    <th class="text-center bg-grey-darker text-white">ITEM</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">FECHA</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">BENEFICIARIO</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">N° VALE</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">ENTREGADO</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">PROVEEDOR</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">N° FACTURA</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">CONCEPTO DE GASTO</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">PARTIDA</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">FACTURAR</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">INGRESOS</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-center">1</td>
                    <td class="text-left">{{ $dataPettyCash['date_recived'] }}</td>
                    <td class="text-left">{{ $dataPettyCash['name_responsibility'] }}</td>
                    <td class="text-center">---</td>
                    <td class="text-center">---</td>
                    <td class="text-center">---</td>
                    <td class="text-center">---</td>
                    <td class="text-left">{{ $dataPettyCash['concept'] }}</td>
                    <td class="text-center">---</td>
                    <td class="text-center">---</td>
                    <td class="text-center">{{ number_format($dataPettyCash['amount'], 2) }}</td>
                </tr>
                @foreach($book_diary as $entry)
                @php

                $ingresos = $entry['approximate_cost'] - $entry['replacement_cost'];
                @endphp
                <tr>
                    <td class="text-center">{{ $loop->iteration + 1 }}</td>
                    <td class="text-center">{{$entry['date_delivery']}}</td>
                    <td class="text-left">{{ $entry['user_register'] }}</td>
                    <td class="text-center">{{ $entry['number_note'] }}</td>
                    <td class="text-center">{{ $entry['approximate_cost'] }}</td>
                    <td class="text-left">{{ $entry['products'][0]['supplier'] ?? '' }}</td>
                    <td class="text-center">{{ $entry['products'][0]['invoce_number'] ?? '' }}</td>
                    <td class="text-center">{{ $entry['products'][0]['object_cost'] ?? '' }}</td>
                    <td class="text-center">{{ $entry['products'][0]['code'] ?? '' }}</td>
                    <td class="text-center">{{ $entry['products'][0]['costFinal'] ?? '' }}</td>
                    <td class="text-center">{{ number_format($ingresos, 2) }}</td>
                </tr>
                @foreach($entry['products'] as $index => $product)
                @if($index > 0)
                <tr>
                    <td class="text-center"></td>
                    <td class="text-center"></td>
                    <td class="text-left"></td>
                    <td class="text-center"></td>
                    <td class="text-center"></td>
                    <td class="text-left">{{ $product['supplier'] }}</td>
                    <td class="text-center">{{ $product['invoce_number'] }}</td>
                    <td class="text-center">{{ $product['object_cost'] }}</td>
                    <td class="text-center">{{ $product['code'] }}</td>
                    <td class="text-center">{{ $product['costFinal'] }}</td>
                    <td>---</td>
                </tr>
                @endif
                @endforeach
                @endforeach
            </tbody>
        </table>

        <table class="table-code w-100 m-b-10 uppercase text-xs">
            <tbody>
                <tr>
                    <td class="w-30 text-center bg-grey-darker text-white">Saldo Total Bs.</td>
                    <td class="w-70 p-l-5"> {{$dataPettyCash['balance']}}</td>
                </tr>
            </tbody>
        </table>

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