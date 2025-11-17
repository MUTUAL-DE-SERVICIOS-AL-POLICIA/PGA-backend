<?php

use \Milon\Barcode\DNS2D;

if (!extension_loaded('intl')) {
    die('La extensión Intl não está habilitada.');
}

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

        .table-info-sm td,
        .table-info-sm th {
            font-size: 8px;
            padding: 3px;
        }
    </style>
</head>

<body>
    <table class="w-100 uppercase">
        <tr>
            <th>
                <table class="table-code w-100 m-b-10 uppercase">
                    <tbody>
                        <tr>
                            <th class="bg-grey-darker text-white text-center" colspan="2">
                                LIBRO DE REGISTROS COMPLETADOS
                            </th>
                        </tr>
                        <tr>
                            <td class="w-50 bg-grey-darker text-white">FECHA Y LUGAR</td>
                            <td class="w-70 p-l-5"> LA PAZ, {{ $date }}</td>
                        </tr>
                        <tr>
                            <td class="w-50 bg-grey-darker text-white">NOMBRE Y APELLIDO DEL CUSTODIO</td>
                            <td class="w-70 p-l-5"> {{ $name }}</td>
                        </tr>
                        <tr>
                            <td class="w-50 bg-grey-darker text-white">ÁREA/UNIDAD</td>
                            <td class="w-70 p-l-5"> {{ $area }}</td>
                        </tr>
                    </tbody>
                </table>
            </th>
        </tr>
    </table>
    <hr class="m-b-10" style="margin-top: 0; padding-top: 0;">

    <div class="block">

        <table class="table-info table-info-sm w-100 m-b-10 uppercase text-xs">
            <thead>
                <tr>
                    <th class="text-center bg-grey-darker text-white">ITEM</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">FECHA</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">BENEFICIARIO</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">N° VALE</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">IMPORTE ENTREGADO</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">IMPORTE DEVUELTO</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">PROVEEDOR</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">N° FACTURA</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">PRODUCTO</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">PARTIDA</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">INGRESOS</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">GASTO</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">SALDO</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($book_diary as $entry)
                @php
                $products = $entry['products'] ?? [];
                @endphp

                @if (empty($products) || count($products) === 0)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td class="text-center">{{ $entry['delivery_date'] }}</td>
                    <td class="text-left">{{ $entry['user_register'] }}</td>
                    <td class="text-center">{{ $entry['number_note'] }}</td>
                    <td class="text-center">{{ $entry['importe_entregado'] }}</td>
                    <td class="text-center">{{ $entry['importe_devuelto'] }}</td>
                    <td class="text-left">-</td>
                    <td class="text-center">-</td>
                    <td class="text-left">-</td>
                    <td class="text-center">-</td>
                    <td class="text-right">-</td>
                    <td class="text-right">-</td>
                    <td class="text-right">{{ $entry['saldo_current'] }}</td>
                </tr>
                @else
                @foreach ($products as $idx => $product)
                <tr>
                    @if ($idx === 0)
                    <td class="text-center">{{ $loop->parent->iteration }}</td>
                    <td class="text-center">{{ $entry['delivery_date'] }}</td>
                    <td class="text-left">{{ $entry['user_register'] }}</td>
                    <td class="text-center">{{ $entry['number_note'] }}</td>
                    <td class="text-center">{{ $entry['importe_entregado'] }}</td>
                    <td class="text-center">{{ $entry['importe_devuelto'] }}</td>
                    @else
                    <td class="text-center" colspan="6"></td>
                    @endif

                    <td class="text-left">{{ $product['supplier'] ?? '-' }}</td>
                    <td class="text-center">
                        {{ $product['invoice_number'] ?? ($product['invoce_number'] ?? '-') }}
                    </td>
                    <td class="text-left">{{ $product['description'] ?? '-' }}</td>
                    <td class="text-center">{{ $product['code'] ?? '-' }}</td>

                    <td class="text-right">{{ $product['ingreso'] }}</td>
                    <td class="text-right">{{ $product['gasto'] }}</td>
                    <td class="text-right">{{ $product['saldo'] }}</td>
                </tr>
                @endforeach
                @endif
                @endforeach
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