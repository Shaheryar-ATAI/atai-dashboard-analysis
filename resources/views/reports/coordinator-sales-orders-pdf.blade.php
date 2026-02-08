<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sales Order Log PDF</title>
    <style>
        @page { size: A4 landscape; margin: 6mm; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8px;
            color: #000;
        }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .logo { height: 40px; }
        .title { font-size: 14px; font-weight: 700; margin-bottom: 2px; }
        .sub { font-size: 9px; color: #333; }
        .summary {
            border-collapse: collapse;
            font-size: 9px;
            width: 100%;
        }
        .summary th,
        .summary td {
            border: 1px solid #000;
            padding: 3px 5px;
        }
        .summary th {
            background: #ffff00;
            text-align: center;
            font-weight: 700;
        }
        .summary .total {
            color: #c00;
            font-weight: 700;
        }
        .main-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            table-layout: fixed;
        }
        .main-table th,
        .main-table td {
            border: 1px solid #000;
            padding: 2px 3px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
        }
        .main-table th {
            background: #e5e5e5;
            font-size: 7px;
            text-transform: uppercase;
            text-align: center;
            font-weight: 700;
        }
        .main-table td { font-size: 7px; }
        .main-table tbody tr:nth-child(odd) { background: #f7f7f7; }
        .main-table tbody tr.rejected { background: #fce8e8; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .nowrap { white-space: nowrap; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 140px;">
                @if(!empty($logoPath))
                    <img src="{{ $logoPath }}" class="logo" alt="ATAI">
                @endif
            </td>
            <td>
                <div class="title">Sales Order Log</div>
                <div class="sub">Region: {{ $regionLabel }}</div>
                <div class="sub">Period: {{ $periodLabel }}</div>
                <div class="sub">Generated: {{ $generatedAt }}</div>
            </td>
            <td style="width: 420px;">
                <table class="summary">
                    <thead>
                        <tr>
                            <th>Regional Concertration</th>
                            <th>Total Orders Regional Concertration(Accepted &amp; Pre Acceptance)</th>
                            <th>Rejected Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($summaryRows as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td class="text-right">{{ number_format($row['total'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['rejected'], 2) }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td class="total">Total Orders Received</td>
                            <td class="text-right total">{{ number_format($totalOrders, 2) }}</td>
                            <td class="text-right total">{{ number_format($totalRejected, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <table class="main-table">
        <thead>
            <tr>
                <th>Client Name</th>
                <th>Count/Region</th>
                <th>Location</th>
                <th>Date Rec</th>
                <th>PO No.</th>
                <th>Products</th>
                <th>Quote No.</th>
              
                <th>Cur</th>
                <th>PO Value</th>
                <th>Value with VAT</th>
                <th>Payment Terms</th>
                <th>Project Name</th>
                <th>Project Location</th>
                <th>Status (OAA)</th>
                <th>Sales OAA</th>
                <th>Job No</th>
                <th>Sales Source</th>
          
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr class="{{ $row['is_rejected'] ? 'rejected' : '' }}">
                    <td>{{ $row['client'] }}</td>
                    <td class="text-center">{{ $row['area'] }}</td>
                    <td>{{ $row['location'] }}</td>
                    <td class="nowrap">{{ $row['date_rec'] }}</td>
                    <td class="nowrap">{{ $row['po_no'] }}</td>
                    <td>{{ $row['atai_products'] }}</td>
                    <td>{{ $row['quotation_no'] }}</td>
                   
                    <td class="text-center">{{ $row['cur'] }}</td>
                    <td class="text-right">{{ number_format($row['po_value'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['value_with_vat'], 2) }}</td>
                    <td>{{ $row['payment_terms'] }}</td>
                    <td>{{ $row['project'] }}</td>
                    <td>{{ $row['project_location'] }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td>{{ $row['oaa'] }}</td>
                    <td>{{ $row['job_no'] }}</td>
                    <td>{{ $row['salesman'] }}</td>
            
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
