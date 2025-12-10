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
                    <div>FORMULARIO N° 5</div>
                    <div>{{$title}}</div>
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

        <div class="leading-tight text-sm text-left m-b-10">
            <strong>POR CONCEPTO:</strong> {{$concept}}
        </div>
        <table class="table-info w-100 m-b-10 uppercase text-xs">
            <thead>
                <tr>
                    <th class="text-center bg-grey-darker text-white">ITEM</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">NOMBRE DE BENEFICIARIO</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">FECHA</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">OBJETO DE GASTO</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">CANTIDAD</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">PRECIO UNIT</th>
                    <th class="text-center bg-grey-darker text-white border-left-white">TOTAL (BS)</th>
                </tr>
            </thead>
            <tbody class="table-striped">

                <tr>
                    <td class="text-center">1</td>
                    <td class="text-center">{{ $employee }}</td>
                    <td class="text-center">{{ $request_date }}</td>
                    <td class="text-center">TRANSPORTE PERSONAL</td>
                    <td class="text-center">1</td>
                    <td class="text-center">{{ $total_petty_cash }}</td>
                    <td class="text-center">{{ $total_petty_cash }}</td>
                </tr>

                @for($i = 2; $i <= $max_requests; $i++)
                    <tr>
                    <td class="text-center" colspan="7">&nbsp;</td>
                    </tr>
                    @endfor

                    <tr>
                        <td class="text-left" colspan="6"><strong>VALOR TOTAL DE COMPRA DEL BIEN O SERVICIO EN BS.</strong></td>
                        <td class="text-right"><strong>{{ $total_petty_cash }}</strong></td>
                    </tr>
                    <tr>
                        <td class="text-center" colspan="7">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="text-left" colspan="6"><strong>SALDO A DEVOLVER EN BS.</strong></td>
                        <td class="text-right">
                            <strong>
                                0
                            </strong>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-center" colspan="7">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="text-left" colspan="6"><strong>VALOR ENTREGADO EN BS.</strong></td>
                        <td class="text-right"><strong>{{ $total_petty_cash }}</strong></td>
                    </tr>
            </tbody>
        </table>
        <br />
        <div class="leading-tight text-sm text-left m-b-10">
            <strong>Lugar y Fecha:</strong> ________________________________________
        </div>
        <br />

        <table class="w-100 text-sm uppercase" style="width: 100%; margin-top: 10px;">
            <tr>
                <td class="text-center" style="width: 50%; vertical-align: top;">
                    <br /><br />
                    ____________________________
                    <br />
                    <strong>Entregué Conforme: </strong>
                    <br /><br />

                </td>
                <td class="text-center" style="width: 50%; vertical-align: top;">
                    <br /><br />
                    ____________________________
                    <br />
                    <strong>Recibí Conforme:</strong>
                    <br /><br />

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