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
                    <div>ESTADO PLURINACIONAL DE BOLIVIA</div>
                    <hr class="m-b-10" style="margin-top: 0; padding-top: 0;">
                    <div>MUTUAL DE SERVICIOS AL POLICIA</div>
                </div>
            </th>
            <th class="w-25 text-left no-paddings no-margins align-middle">
                <div class="text-left">
                    <img src="{{ public_path("/img/logo.png") }}" class="w-40">
                </div>
            </th>
        </tr>
    </table>
    <hr class="m-b-10" style="margin-top: 0; padding-top: 0;">
    <div class="block">
        <div class="leading-tight text-sm text-center m-b-10"><strong>{{ $title }}</strong></div>
        <div class="leading-tight text-sm text-center m-b-10">{{$routeSheet}}</div>
    </div>

    <div class="leading-tight text-sm text-right m-b-10">La Paz, {{$date_send}}</div>

    <div class="leading-tight text-sm text-left m-b-10" style="text-align: justify;">
        En cumplimiento a los Artículos 1 y 14 de la Ley 1178, en esta Unidad se realizó la revisión del presente tramite, adjuntando documentación suficiente, pertinente y competente como respaldo, por lo que solicitamos proceder al pago a través de la Unidad Financiera dependiente de la Dirección de Asuntos Administrativos.
    </div>

    <table class="table-info w-100 m-b-10 uppercase text-xs">
        <thead>
            <tr>
                <th class="text-center bg-grey-darker text-white">N°</th>
                <th class="text-center bg-grey-darker text-white border-left-white">NOMBRE(S) BENEFICIARIO(S)</th>
                <th class="text-center bg-grey-darker text-white border-left-white">IMPORTE (BS)</th>
            </tr>
        </thead>
        <tbody class="table-striped">
            <tr>
                <td class="text-center">{{1}}</td>  
                <td class="text-center">{{$responsible}}</td>
                <td class="text-center">{{$amount}}</td>
            </tr>
            <tr>
                <td class="text-center">Total: </td>
                <td class="text-center" colspan="2"><strong>{{$amount_literal}}</strong></td>
            </tr>
        </tbody>
    </table>
    <div class="leading-tight text-sm text-left m-b-10">
        <strong>CONCEPTO:</strong>
    </div>

    <div class="leading-tight text-sm text-left m-b-10" style="text-align: justify;">
        Descargo de gastos realizados con fondos de Caja Chica de la MUSERPOL, previa revisión será reembolsado conforme al Reglamento Interno para el efecto.
    </div>

    <div class="leading-tight text-sm text-left m-b-10" style="text-align: justify;">
        Correspondiente: Colocar fechas de intervalo 
    </div>


    <div class="leading-tight text-sm text-left m-b-10">
        <strong>COMO RESPALDO AL PRESENTE PAGO, SE ANEXA LA SIGUIENTE DOCUMENTACIÓN:</strong>
    </div>

    <div class="leading-tight text-sm text-left m-b-10" style="text-align: justify;">
        <ol>
            <li>Descargo Documentado de Gastos</li>
            <li>Planilla de Rendición de Cuentas</li>
            <li>Vales de Caja Chica originales y firmados</li>
            <li>Facturas originales</li>
            <li>Formularios de Transporte de personal originales</li>
            <li>Actas de Conformidad, adjuntado a los vales de Caja Chica</li>
        </ol>
    </div>

    <div class="leading-tight text-sm text-left m-b-10">
        <strong>UNIDAD EJECUTORIA RESPONSABLE:</strong>
    </div>
    <div class="leading-tight text-sm text-left m-b-10" style="text-align: justify;">
        Garantizamos haber revisado los documentos de la presente Orden de Pago y certificamos que el proceso es correcto, asumiendo plena responsabilidad de acuerdo a D.S. 23318-A de la Responsabilidad por la Función Pública.
    </div>

    <table class="table-info w-100 m-b-10 uppercase text-xs">
        <thead>
            <tr>
                <th class="text-center bg-grey-darker text-white" style="width: 33.33%;">ELABORADO POR:</th>
                <th class="text-center bg-grey-darker text-white border-left-white" style="width: 33.33%;">REVISADO POR:</th>
                <th class="text-center bg-grey-darker text-white border-left-white" style="width: 33.33%;">AUTORIZADO POR:</th>
            </tr>
        </thead>
        <tbody class="table-striped">
            <tr>
                <td rowspan="3" style="height: 80px;"></td>
                <td rowspan="3" style="height: 80px;"></td>
                <td rowspan="3" style="height: 80px;" doce></td>
            </tr>
        </tbody>
    </table>


</body>

</html>