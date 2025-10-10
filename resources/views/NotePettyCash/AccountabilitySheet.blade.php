<?php

use \Milon\Barcode\DNS2D;

$max_requests = 30;

$dns = new DNS2D();



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
            font-size: 10px;
        }

        .text-xxxs {
            font-size: 8px;
        }

        .table-large-font {
            font-size: 20px;
            font-weight: bold;
            border: 2px;
        }
    </style>
</head>

<body style="border: 0; border-radius: 0;">
    <table class="w-100 uppercase" style="margin-top: 50px;">
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
                            <td class="text-center text-xxs"> CCH / </td>
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
        <div class="leading-tight text-sm text-center m-b-10">DIRECCIÓN DE ASUNTOS ADMINISTRATIVA</div>
        <div class="leading-tight text-sm text-center m-b-10">{{ $title }}</div>
    </div>
    <table class="table-code w-100 m-b-10 uppercase text-xs">
        <tbody>
            <tr>
                <td class="w-40 text-left bg-grey-darker text-white">LUGAR Y FECHA</td>
                <td td class="w-60 p-l-5">{{$date_first}}</td>
            </tr>
            <tr>
                <td class="w-40 text-left bg-grey-darker text-white">ÁREA / UNIDAD</td>
                <td td class="w-60 p-l-5">{{$area}}</td>
            </tr>
            <tr>
                <td class="w-40 text-left bg-grey-darker text-white">FECHA DE RECEPCIÓN DE FONDOS</td>
                <td td class="w-60 p-l-5">{{$date_of_receipt_of_funds}}</td>
            </tr>
            <tr>
                <td class="w-40 text-left bg-grey-darker text-white">FECHA DE PRESENTACIÓN DE DESCARGOS</td>
                <td td class="w-60 p-l-5">{{$date_first}}</td>
            </tr>
        </tbody>
    </table>
    <div class="leading-tight text-sm text-left m-b-10">
        <strong>PLANILLA:</strong>
    </div>
    <table class="table-info w-100 m-b-10 uppercase text-xs">
        <thead>
            <tr>
                <th class="text-center bg-grey-darker text-white">FECHA</th>
                <th class="text-center bg-grey-darker text-white border-left-white">Nro. FACTURA</th>
                <th class="text-center bg-grey-darker text-white border-left-white">PARTIDA</th>
                <th class="text-center bg-grey-darker text-white border-left-white">DESCRIPCION DEL GASTO</th>
                <th class="text-center bg-grey-darker text-white border-left-white">IMPORTE EN Bs.</th>
            </tr>
        </thead>
        <tbody class="table-striped">
            @foreach ($products as $product)
            <tr>
                <td class="text-center">{{$product['delivery_date']}}</td>
                <td class="text-center border-left-white">{{ $product['number_invoice'] }}</td>
                <td class="text-center border-left-white">{{ $product['code'] }}</td>
                <td class="text-center border-left-white">{{ $product['description'] }}</td>
                <td class="text-center border-left-white">{{ number_format($product['amount'], 2) }}</td>
            </tr>
            @endforeach
            <tr>
                <td class="text-left" colspan="4"><strong>VALOR TOTAL BS.</strong></td>
                <td class="text-center"><strong>{{ number_format($products->sum(function($product) {return $product['amount'];}), 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <div class="leading-tight text-sm text-left m-b-10">
        <strong>DATOS:</strong>
    </div>
    <table class="table-info w-100 m-b-10 uppercase text-xs">
        <thead>
            <tr>
                <th class="text-center bg-grey-darker text-white" colspan="2">DATOS</th>
                <th class="text-center bg-grey-darker text-white border-left-white">DESCARGADO POR </th>
                <th class="text-center bg-grey-darker text-white border-left-white">APROBADO POR </th>
            </tr>
        </thead>
        <tbody class="table-striped">
            <tr>
                <td class="text-left border-left-white">Importe Total Entregado</td>
                <td class="text-center border-left-white">{{$fund}}</td>
                <td class="text-center" rowspan="3"></td>
                <td class="text-center" rowspan="3"></td>
            </tr>
            <tr>
                <td class="text-left border-left-white">Importe Total Descargado</td>
                <td class="text-center border-left-white">{{number_format($products->sum(function($product) {return $product['amount'];}), 2)}}</td>
            </tr>
            <tr>
                <td class="text-left border-left-white">Saldos en efectivo </td>
                <td class="text-center border-left-white">{{number_format($fund -$products->sum(function($product) {return $product['amount'];}),2)}}</td>
            </tr>
        </tbody>
    </table>
    <div class="leading-tight text-sm text-left m-b-10">
        <strong>OBSERVACIONES Y/O ACLARACIONES:</strong> _____________________________________________________
    </div>
    <br />
    <div class="leading-tight text-sm text-left m-b-10">
        <strong>GRUPOS PRESUPUESTARIOS:</strong>
    </div>

    <table class="table-info w-100 m-b-10 uppercase text-xs">
        <thead>
            <tr>
                <th class="text-center bg-grey-darker text-white">PARTIDAS</th>
                <th class="text-center bg-grey-darker text-white border-left-white">PRESUPUESTO</th>
                <th class="text-center bg-grey-darker text-white border-left-white">IMPORTE EN Bs.</th>
            </tr>
        </thead>
        <tbody class="table-striped">
            @foreach ($groups_summary as $group)
            <tr>
                <td class="text-left">{{$group['code']}}</td>
                <td class="text-left border-left-white">{{ $group['name_group'] }}</td>
                <td class="text-center border-left-white">{{ number_format($group['total_amount'], 2) }}</td>
            </tr>
            @endforeach
            <tr>
                <td class="text-left" colspan="2"><strong>VALOR TOTAL BS.</strong></td>
                <td class="text-center"><strong>{{ number_format($groups_summary->sum(function($group) {return $group['total_amount'];}), 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

</body>

</html>