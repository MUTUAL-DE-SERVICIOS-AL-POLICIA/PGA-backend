<?php

use \Milon\Barcode\DNS2D;

$max_requests = 10;

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
            font-size: 10px;
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
            padding-left: 3px;
        }

        .text-xs {
            font-size: 8px;
        }

        .text-xxxs {
            font-size: 6px;
        }

        .table-large-font {
            font-size: 20px;
            font-weight: bold;
            border: 2px;
        }
    </style>
</head>

<body style="border: 0; border-radius: 0;">
    @for($it = 0; $it<2; $it++)
        <table class="w-100 uppercase" style="margin-top: 20px;">
        <tr>
            <th class="w-25 text-left no-paddings no-margins align-middle">
                <div class="text-left">
                    <img src="{{ public_path("/img/logo.png") }}" class="w-40">
                </div>
            </th>
            <th class="w-50 align-top">
                <div class="font-hairline leading-tight text-xs">
                    <div>FORMULARIO N° 1</div>
                    <div>VALE DE CAJA CHICA</div>
                    <div>{{$subtitle}}</div>
                </div>
            </th>
            <th class="w-25 no-padding no-margins align-top">
                <table class="table-code no-padding no-margins text-xxxs uppercase">
                    <tbody>
                        <tr>
                            <td class="text-center bg-grey-darker text-white">Nº </td>
                            <td class="text-center text-xxs"> {{$code}} / {{ $number_note }} </td>
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
        <div class="leading-tight text-left">
            <strong>DESEMBOLSO:</strong>
        </div>
        <div class="leading-tight text-xs text-left">
            He recibido del Responsable de Caja Chica con cargo a rendición de cuenta documentada, la suma de:
        </div>
        <table class="table-code w-100 m-b-10 uppercase ">
            <tbody>
                <tr>
                    <td class="w-75 p-l-5 table-large-font">{{ number_format($total, 2) }} Bs.</td>
                </tr>
                <tr>
                    <td class="w-75 p-l-5 table-large-font">{{ $total_lit }}</td>
                </tr>
            </tbody>
        </table>
        <div class="leading-tight  text-left ">
            <strong>POR CONCEPTO: {{$concept}}</strong>
        </div>
        <table class="table-info w-100 m-b-10 uppercase text-xs">
            <thead>
                <tr>
                    <th class="text-center bg-grey-darker text-white">ITEM</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">N° BOLETA</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">DESDE</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">HASTA</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">IMPORTE</th>
                </tr>
            </thead>
            <tbody class="table-striped">
                @foreach ($routes as $i => $route)
                <tr>
                    <td class="text-center">{{ ++$i }}</td>
                    <td class="text-center">{{ $route['ticket_invoice'] }}</td>
                    <td class="text-center">{{ $route['from'] }}</td>
                    <td class="text-center">{{ $route['to'] }}</td>
                    <td class="text-right">{{ $route['cost'] }}</td>
                </tr>
                @endforeach
                @for($i = sizeof($routes) + 1; $i <= $max_requests; $i++)
                    <tr>
                    <td class="text-center" colspan="7">&nbsp;</td>
                    </tr>
                    @endfor
                    <tr>
                        <td class="text-center" colspan="4"><strong>TOTAL</strong></td>
                        <td class="text-right"><strong>{{ number_format($total, 2) }}</strong></td>
                    </tr>
            </tbody>
        </table>
        <div class="leading-tight text-left m-b-10">
            El reembolso por gasto de transporte será previa presentación del formulario adjuntando la boleta de comisión en original.
        </div>
        <div class="leading-tight text-left m-b-10">
            <strong>COMPROMISO:</strong>
        </div>
        <div class="leading-tight text-left m-b-10" style="text-align: justify;">
            En sujeción al inciso c) del artículo 27 de la Ley 1178 del 20 de julio de 1990 de Administración y Control Gubernamentales
            (SAFCO), me comprometo a presentar la documentación sustentatoria original, auténtica y fidedigna <strong><u>en
                    el plazo máximo de 5 dias hábiles de la fecha de la boleta de comisión.</u></strong>
        </div>
        
        <div class="leading-tight text-left m-b-10">
            <strong>Lugar y Fecha:</strong> ________________________________________
        </div>
        <table class="w-100" style="margin-top: 50px;">
            <tbody>
                <tr class="align-bottom text-center text-xxxs" style="height: 120px; vertical-align: bottom;">
                    <td class="rounded w-33">&nbsp;Solicitado por:</td>
                    <td class="rounded w-33">&nbsp;Autorizado por:</td>
                    <td class="rounded w-33">&nbsp;Visto Bueno</td>
                    <td class="rounded w-33">&nbsp;Entregué Conforme</td>
                </tr>
            </tbody>
        </table>
        @if($it == 0)
        <div class="scissors-rule">
            <span>------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------</span>
        </div>
        @endif
        @endfor
</body>

</html>