<?php

use \Milon\Barcode\DNS2D;

$max_requests = 10;

$dns = new DNS2D();
$hasCajaChica = collect($materials)->contains(function ($material) {
    return str_contains(strtoupper($material['description']), 'CAJA CHICA');
});
?>

<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>PLATAFORMA VIRTUAL ADMINISTRATIVA - MUSERPOL </title>
    <link rel="stylesheet" href="{{ public_path("/css/material-request.min.css") }}" media="all" />
    <style>
        @page {
            size: letter;
            margin: 1.5cm;
        }

        body {
            font-size: 12px;
            /* Cambia según sea necesario */
        }

        .scissors-rule {
            display: block;
            text-align: center;
            overflow: hidden;
            white-space: nowrap;
            margin-top: 6px;
            margin-bottom: 17px;
        }

        .scissors-rule>span {
            position: relative;
            display: inline-block;
        }

        .scissors-rule>span:before,
        .scissors-rule>span:after {
            content: "";
            position: absolute;
            top: 50%;
            width: 9999px;
            height: 1px;
            background: white;
            border-top: 1px dashed black;
        }

        .scissors-rule>span:before {
            right: 100%;
            margin-right: 5px;
        }

        .scissors-rule>span:after {
            left: 100%;
            margin-left: 5px;
        }

        .border-left-white {
            border-left: 1px solid white;
        }

        .p-l-5 {
            padding-left: 5px;
        }

        .text-xs {
            font-size: 9px;
        }

        .text-xxxs {
            font-size: 7px;
        }
    </style>
</head>

<body style="border: 0; border-radius: 0;">
    @for($it = 0; $it<2; $it++)
        <table class="w-100 uppercase" >
        <tr>
            <th class="w-25 text-left no-paddings no-margins align-middle">
                <div class="text-left">
                    <img src="{{ public_path("/img/logo.png") }}" class="w-40">
                </div>
            </th>
            <th class="w-50 align-top">
                <div class="font-hairline leading-tight text-xs">
                    <div>MUTUAL DE SERVICIOS AL POLICÍA "MUSERPOL"</div>
                    <div>DIRECCIÓN DE ASUNTOS ADMINISTRATIVOS</div>
                    <div>UNIDAD ADMINISTRATIVA</div>
                </div>
            </th>
            <th class="w-25 no-padding no-margins align-top">
                <table class="table-code no-padding no-margins text-xxxs uppercase">
                    <tbody>
                        <tr>
                            <td class="text-center bg-grey-darker text-white">Nº </td>
                            <td class="text-center text-xxs"> {{ $number_note }} </td>
                        </tr>
                        <tr>
                            <td class="text-center bg-grey-darker text-white">Fecha</td>
                            <td class="text-center text-xxs"> {{ $date }} </td>
                        </tr>
                    </tbody>
                </table>
            </th>
        </tr>
        </table>
        <hr class="m-b-10" style="margin-top: 0; padding-top: 0;">
        <div class="block">
            <div class="leading-tight text-center m-b-10">{{ $title }}</div>

            <table class="table-code w-100 m-b-10 uppercase text-xs">
                <tbody>
                    <tr>
                        <td class="w-10 text-center bg-grey-darker text-white">Nombre</td>
                        <td class="w-90 p-l-5">{{ $employee }}</td>
                    </tr>
                    <tr>
                        <td class="w-10 text-center bg-grey-darker text-white">Cargo</td>
                        <td class="w-90 p-l-5">{{ $position }}</td>
                    </tr>
                </tbody>
            </table>

            <table class="table-info w-100 m-b-10 uppercase text-xs">
                <thead>
                    <tr>
                        <th class="text-center bg-grey-darker text-white">ITEM</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">DESCRIPCIÓN</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">UNIDAD</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">SOLICITADO</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">ENTREGADO</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">COSTO UNI</th>
                        <th class="text-center bg-grey-darker text-white border-left-white">COSTO TOTAL</th>
                    </tr>
                </thead>
                <tbody class="table-striped">
                    @foreach ($materials as $i => $material)
                    <tr>
                        <td class="text-center">{{++$i}}</td>
                        <td class="text-center">{{$material['description']}}</td>
                        <td class="text-center">{{$material['unit_material']}}</td>
                        <td class="text-center">{{$material['amount_request']}}</td>
                        <td class="text-center">{{$material['delivered_quantity']}}</td>
                        <td class="text-center">{{$material['cost_unit']}}</td>
                        <td class="text-center">{{number_format($material['cost_unit'] * $material['delivered_quantity'], 2)}}</td>
                    </tr>
                    @endforeach
                    @for($i = sizeof($materials) + 1; $i <= $max_requests; $i++)
                        <tr>
                        <td class="text-center" colspan="7">&nbsp;</td>
                        </tr>
                        @endfor
                        <tr>
                            <td class="text-center" colspan="6"><strong>TOTAL</strong></td>
                            <td class="text-center"><strong>{{ number_format($materials->sum(function($material) {return $material['cost_unit'] * $material['delivered_quantity'];}), 2) }}</strong></td>
                        </tr>
                </tbody>
            </table>
            <table class="w-100" style="margin-top: 50px;">
                <tbody>
                    <tr class="align-bottom text-center text-xxxs" style="height: 120px; vertical-align: bottom;">
                        @if($hasCajaChica)
                        <td class="rounded w-100">&nbsp;Entregado por:</td>
                        @else
                        <td class="rounded w-50">&nbsp;Recibi conforme</td>
                        <td class="rounded w-50">&nbsp;Entregado por:</td>
                        @endif
                    </tr>
                </tbody>
            </table>
            <table class="table-info w-100 m-b-10 uppercase text-xs" style="margin-top: 10px;">
                <thead>
                    <tr>
                        <th class="text-center bg-grey-darker text-white border-left-white">Comentario Encargado de Almacen</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="rounded w-50">{{ $comment }}</td>
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
        @if($it == 0)
        <div class="scissors-rule">
            <span>------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------</span>
        </div>
        @endif
        @endfor
</body>

</html>