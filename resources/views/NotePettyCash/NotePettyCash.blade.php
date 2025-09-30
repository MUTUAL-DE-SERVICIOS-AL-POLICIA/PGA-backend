<?php

use \Milon\Barcode\DNS2D;

$max_requests = 7;

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
    <div class="block">
        <div class="leading-tight text-sm text-center m-b-10">FORMULARIO N° 1</div>
        <div class="leading-tight text-sm text-center m-b-10">{{ $title }}{{ $subtitle }}</div>
    </div>
    <div class="leading-tight text-sm text-left m-b-10">
        <strong>DESEMBOLSO:</strong>
    </div>
    <div class="leading-tight text-sm text-left m-b-10">
        He recibido del Responsable de Caja Chica con cargo a rendición de cuenta documentada, la suma de:
    </div>
    <table class="table-code w-100 m-b-10 uppercase text-xs">
        <tbody>
            <tr>
                <td class="w-75 p-l-5 table-large-font">{{ number_format($total, 2) }} Bs.</td>
            </tr>
            <tr>
                <td class="w-75 p-l-5 table-large-font">{{ $total_lit }}</td>
            </tr>
        </tbody>
    </table>
    <div class="leading-tight text-sm text-left m-b-10">
        <strong>POR CONCEPTO: {{$concept}}</strong>
    </div>
    <div class="leading-tight text-sm text-left m-b-10">
        <strong>SOLICITADO:</strong>
    </div>
    <table class="table-info w-100 m-b-10 uppercase text-xs">
        <thead>
            <tr>
                <th class="text-center bg-grey-darker text-white">ITEM</th>
                <th class="text-center bg-grey-darker text-white border-left-white">DESCRIPCIÓN</th>
                <th class="text-center bg-grey-darker text-white border-left-white">CANTIDAD</th>
                <th class="text-center bg-grey-darker text-white border-left-white">PRECIO UNITARIO</th>
                <th class="text-center bg-grey-darker text-white border-left-white">PRECIO TOTAL</th>
            </tr>
        </thead>
        <tbody class="table-striped">
            @foreach ($products as $i => $product)
            <tr>
                <td class="text-center">{{ ++$i }}</td>
                <td class="text-center">{{ $product['description'] }}</td>
                <td class="text-center">{{ $product['quantity'] }}</td>
                <td class="text-right">{{ number_format($product['price'],2) }}</td>
                <td class="text-right">{{ number_format(($product['quantity'] * $product['price']),2) }}</td>
            </tr>
            @endforeach
            @for($i = sizeof($products) + 1; $i <= $max_requests; $i++)
                <tr>
                <td class="text-center" colspan="7">&nbsp;</td>
                </tr>
                @endfor
                <tr>
                    <td class="text-center" colspan="4"><strong>TOTAL</strong></td>
                    <td class="text-right"><strong>{{ number_format($products->sum(function($product) {return $product['price'] * $product['quantity'];}), 2) }}</strong></td>
                </tr>
        </tbody>
    </table>
    <div class="leading-tight text-sm text-left m-b-10">
        El descargo será previa presentación de la documentación de sustento del gasto.
    </div>
    <div class="leading-tight text-sm text-left m-b-10">
        <strong>COMPROMISO:</strong>
    </div>
    <div class="leading-tight text-sm text-left m-b-10" style="text-align: justify;">
        En sujeción al inciso c) del artículo 27 de la Ley 1178 del 20 de julio de 1990 de Administración y Control Gubernamentales
        (SAFCO), me comprometo a rendir cuentas, presentando la documentación sustentatoria original, auténtica y fidedigna, <strong><u>en
                el plazo máximo de 48 horas hábiles siguientes de recibido el efectivo.</u></strong>
    </div>
    <div class="leading-tight text-sm text-left m-b-10" style="text-align: justify;">
        Conozco que en el caso contrario, son aplicables en mi contra las normas del Decreto Supremo No. 23318-A del 3 de noviembre de 1992 que aprueba el Reglamento de la Responsabilidad por la Función Pública y el Reglamento de Caja Chica vigente de la MUSERPOL. Asimismo acepto que la no devolución o descargo de los recursos dentro del plazo estipulado en el presente reglamento acepto que el importe sea descontado de mis haberes.
    </div>
    <div class="leading-tight text-sm text-left m-b-10">
        <strong>Lugar y Fecha:</strong> ________________________________________
    </div>
    <br />
    <div class="leading-tight text-sm text-left m-b-10">
        <table class="w-100 text-sm uppercase" style="width: 100%; margin-top: 20px;">
            <tr>
                <td class="text-center" style="width: 50%; vertical-align: top;">
                    <br /><br />
                    ____________________________
                    <br />
                    <strong>Solicitado por: {{$employee}}</strong>
                    <br /><br />
                    <br /><br />
                    <br /><br />
                    ____________________________
                    <br />
                    <strong>Visto Bueno:</strong>
                </td>
                <td class="text-center" style="width: 50%; vertical-align: top;">
                    <br /><br />
                    ____________________________
                    <br />
                    <strong>Autorizado por:</strong>
                    <br /><br />
                    <br /><br />
                    <br /><br />
                    ____________________________
                    <br />
                    <strong>Entregue conforme:</strong>
                </td>
            </tr>
        </table>
    </div>

</body>

</html>