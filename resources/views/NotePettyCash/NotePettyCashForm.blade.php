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
        <div class="leading-tight text-sm text-center m-b-10">FORMULARIO N° 2</div>
        <div class="leading-tight text-sm text-center m-b-10">{{ $title }}{{ $subtitle }}</div>
    </div>
    <div class="leading-tight text-sm text-left m-b-10">
        <strong>POR CONCEPTO:</strong>
    </div>
    <div class="leading-tight text-sm text-left m-b-10">
        {{$concept}}
    </div>
    <div class="leading-tight text-sm text-left m-b-10">

    </div>
    <div class="leading-tight text-sm text-left m-b-10">
        <strong>SOLICITADO:</strong>
    </div>
    <table class="table-info w-100 m-b-10 uppercase text-xs">
        <thead>
            <tr>
                <th class="text-center bg-grey-darker text-white">ITEM</th>
                <th class="text-center bg-grey-darker text-white border-left-white">PROVEEDOR</th>
                <th class="text-center bg-grey-darker text-white border-left-white">FECHA</th>
                <th class="text-center bg-grey-darker text-white border-left-white">N° DE FACTURA</th>
                <th class="text-center bg-grey-darker text-white border-left-white">PARTIDA PRESUPUESTARIA</th>
                <th class="text-center bg-grey-darker text-white border-left-white">CANTIDAD</th>
                <th class="text-center bg-grey-darker text-white border-left-white">PRECIO UNIT</th>
                <th class="text-center bg-grey-darker text-white border-left-white">TOTAL (BS)</th>
            </tr>
        </thead>
        <tbody class="table-striped">
            @foreach ($products as $i => $product)
            <tr>
                <td class="text-center">{{ ++$i }}</td>
                <td class="text-center">{{ $product['supplier'] }}</td>
                <td class="text-center">{{ $request_date }}</td>
                <td class="text-center">{{ $product['number_invoice'] }}</td>
                <td class="text-center">{{ $product['code_group'] }}</td>
                <td class="text-center">{{ $product['quantity'] }}</td>
                <td class="text-center">{{ $product['price'] }}</td>
                <td class="text-right">{{ number_format($product['total'], 2)}}</td>
            </tr>
            @endforeach
            @for($i = sizeof($products) + 1; $i <= $max_requests; $i++)
                <tr>
                <td class="text-center" colspan="8">&nbsp;</td>
                </tr>
                @endfor

                <tr>
                    <td class="text-left" colspan="7"><strong>VALOR TOTAL DE COMPRA DEL BIEN O SERVICIO EN BS.</strong></td>
                    <td class="text-right"><strong>{{ number_format($products->sum(function($product) {return $product['total'];}), 2) }}</strong></td>
                </tr>
                <tr>
                    <td class="text-center" colspan="8">&nbsp;</td>
                </tr>
                <tr>
                    <td class="text-left" colspan="7"><strong>SALDO A DEVOLVER EN BS.</strong></td>
                    <td class="text-right">
                        <strong>
                            {{
                number_format(
                    $total_petty_cash - $products->sum(function($product) { return $product['total']; }),
                    2
                )
            }}
                        </strong>
                    </td>
                </tr>
                <tr>
                    <td class="text-center" colspan="8">&nbsp;</td>
                </tr>
                <tr>
                    <td class="text-left" colspan="7"><strong>VALOR ENTREGADO EN BS.</strong></td>
                    <td class="text-right"><strong>{{ $total_petty_cash }}</strong></td>
                </tr>
        </tbody>
    </table>
    <br />
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
                    <strong>Entregué Conforme: </strong>
                    <br /><br />
                    <br /><br />
                    <br /><br />
                </td>
                <td class="text-center" style="width: 50%; vertical-align: top;">
                    <br /><br />
                    ____________________________
                    <br />
                    <strong>Recibí Conforme:</strong>
                    <br /><br />
                    <br /><br />
                    <br /><br />
                </td>
            </tr>
        </table>
    </div>

</body>

</html>