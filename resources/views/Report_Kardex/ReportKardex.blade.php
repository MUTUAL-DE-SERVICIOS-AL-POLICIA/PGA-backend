<?php

use \Milon\Barcode\DNS2D;

$date = '12/01/2024';
$dns = new DNS2D();
?>
<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
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
            <div class="leading-tight text-xxxl text-center m-b-10">(EXPRESADO EN BOLIVIANOS)</div>
            <table class="table-code w-100 m-b-10 uppercase text-xs">
                <tbody>
                    <tr>
                        <td class="w-10 text-center bg-grey-darker text-white">CODIGO</td>
                        <td class="w-90 p-l-5"> {{ $code_material }}</td>
                    </tr>
                    <tr>
                        <td class="w-10 text-center bg-grey-darker text-white">DESCRIPCION</td>
                        <td class="w-90 p-l-5"> {{ $description }}</td>
                    </tr>
                    <tr>
                        <td class="w-10 text-center bg-grey-darker text-white">UNIDAD</td>
                        <td class="w-90 p-l-5"> {{ $unit_material }}</td>
                    </tr>
                    <tr>
                        <td class="w-10 text-center bg-grey-darker text-white">GRUPO</td>
                        <td class="w-90 p-l-5"> {{ $group }}</td>
                    </tr>
                </tbody>
            </table>

            <table class="table-info w-100 m-b-10 uppercase text-xs">
                <thead>
                    <tr>
                        <th class="text-center bg-grey-darker text-white" rowspan="2">FECHA</th>
                        <th class="text-center bg-grey-darker text-white border-left-white" rowspan="2">DETALLE</th>
                        <th class="text-center bg-grey-darker text-white border-left-white" colspan="3">CANTIDAD</th>
                        <th class="text-center bg-grey-darker text-white border-left-white" rowspan="2">PRECIO UNITARIO</th>
                        <th class="text-center bg-grey-darker text-white border-left-white" colspan="3">IMPORTES</th>
                    </tr>
                    <tr>
                        <th class="text-center bg-grey-darker text-white border-left-white">ENTRADA</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">SALIDA</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">SALDO</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">ENTRADA</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">SALIDA</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">SALDO</th>
                    </tr>
                </thead>
                <tbody class="table-striped">
                    @foreach ($kardex_de_existencia as $index => $kardex)
                    <tr>
                        <td class="text-center">{{$kardex['date']}}</td>
                        <td class="text-left">{{$kardex['description']}}</td>
                        <td class="text-center">{{$kardex['entradas']}}</td>
                        <td class="text-center">{{$kardex['salidas']}}</td>
                        <td class="text-center">{{$kardex['stock_fisico']}}</td>
                        <td class="text-right">{{ number_format($kardex['cost_unit'], 2) }}</td>
                        <td class="text-right">
                            {{ $kardex['importe_entrada'] ? number_format($kardex['importe_entrada'], 2) : '---' }}
                        </td>
                        <td class="text-right">
                            {{ $kardex['importe_salida'] ? number_format($kardex['importe_salida'], 2) : '---' }}
                        </td>
                        <td class="text-right">
                            {{ $kardex['importe_saldo'] ? number_format($kardex['importe_saldo'], 2) : '---' }}
                        </td>

                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <table class="footer">
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