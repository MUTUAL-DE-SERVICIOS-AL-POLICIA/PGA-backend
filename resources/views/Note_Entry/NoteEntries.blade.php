<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>{{$file_title}}</title>
    <link rel="stylesheet" href="{{ public_path("/css/report-print.min.css") }}" media="all" />
    <style>
        @page {
            size: letter;
            margin: 1.5cm;
        }

        /* Nueva clase para centrar contenido en celdas td */
        .td-center {
            text-align: center !important;
        }
    </style>
</head>

<body>
    @include('partials.header_pga', $header)
    <div class="block">
        <div class="leading-tight text-center m-b-10 text-xs">{{ $title }}</div>

        <table class="table-code w-100 m-b-10 uppercase text-xs" style="table-layout: fixed;">
            <colgroup>
                <col style="width: 25%;">
                <col style="width: 25%;">
                <col style="width: 25%;">
                <col style="width: 25%;">
            </colgroup>
            <tbody>
                @foreach ($suppliers as $index => $supplier)
                <tr>
                    <td class="text-center bg-grey-darker text-white">DATOS DE PROVEEDOR</td>
                    <td>{{ $supplier['supplier_name'] }}</td>
                    <td class="text-center bg-grey-darker text-white">N° DE FACTURA</td>
                    <td>{{ $supplier['invoice_number'] }}</td>
                </tr>
                @endforeach
                <tr>
                    <td class="text-center bg-grey-darker text-white">FECHA</td>
                    <td colspan="3">{{ $delivery_date }}</td>
                </tr>
            </tbody>
        </table>

        </table>
        <div class="table-container uppercase">
            <table class="table-info">
                <thead>
                    <tr>
                        <th class="bg-grey-darker" rowspan="2">Nro.</th>
                        <th rowspan="2">Código</th>
                        <th rowspan="2">Unidad</th>
                        <th rowspan="2">Detalle</th>
                        <th rowspan="2">Cantidad</th>
                        <th colspan="2">Importe</th>
                    </tr>
                    <tr>
                        <th>Unitario</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($materials as $index => $material)
                    <tr>
                        <td class="td-center">{{ $index + 1 }}</td>
                        <td>{{ $material['code_material'] }}</td>
                        <td>{{ $material['unit_material'] }}</td>
                        <td>{{ $material['description'] }}</td>
                        <td class="td-center">{{ $material['amount_entries'] }}</td>
                        <td class="td-center">{{ $material['cost_unit'] }}</td>
                        <td class="td-center">{{ $material['cost_total'] }}</td>
                    </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="5">Total</td>
                        <td></td>
                        <td>{{ $total_cost }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="signature-container">
        <div class="signature-line"></div>
        <p><strong>FIRMA PROFESIONAL EN ACTIVOS FIJOS Y ALMACENES</strong></p>
    </div>

</html>