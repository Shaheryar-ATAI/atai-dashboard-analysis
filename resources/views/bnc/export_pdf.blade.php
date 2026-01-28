<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h2 { margin: 0 0 8px; }
        .meta { margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #777; padding: 5px; vertical-align: top; }
        th { background: #eee; }
        .section-title { margin-top: 14px; font-weight: bold; font-size: 12px; }
        .muted { color: #666; }
    </style>
</head>
<body>

<h2>BNC Projects Export</h2>
<div class="meta">
    Generated: <b>{{ $generatedAt }}</b> |
    Region: <b>{{ $filters['region'] }}</b> |
    Stage: <b>{{ $filters['stage'] }}</b> |
    Min Value (SAR): <b>{{ number_format($filters['min_value_sar'] ?? 0, 0) }}</b>
</div>

<div class="section-title">Quoted Projects</div>
<table>
    <thead>
    <tr>
        <th style="width:4%">#</th>
        <th style="width:16%">Project</th>
        <th style="width:8%">City</th>
        <th style="width:6%">Region</th>
        <th style="width:8%">Stage</th>
        <th style="width:9%">BNC Value (SAR)</th>
        <th style="width:9%">Quoted Value (SAR)</th>
        <th style="width:6%">Quotes</th>
        <th style="width:6%">Coverage</th>
        <th style="width:14%">Parties / Contacts</th>
        <th style="width:14%">Consultant / Contractors</th>
    </tr>
    </thead>
    <tbody>
    @foreach($quoted as $i => $r)
        @php
            $bncSar = ((float)($r->value_usd ?? 0)) * $USD_TO_SAR;
            $quotedSar = (float)($r->quoted_value_sar ?? 0);
            $cov = ($bncSar>0) ? round(($quotedSar/$bncSar)*100) : 0;
            $rp = is_array($r->raw_parties) ? $r->raw_parties : (json_decode($r->raw_parties ?? '[]', true) ?: []);
        @endphp
        <tr>
            <td>{{ $i+1 }}</td>
            <td><b>{{ $r->project_name }}</b><br><span class="muted">{{ $r->reference_no }}</span></td>
            <td>{{ $r->city }}</td>
            <td>{{ $r->region }}</td>
            <td>{{ $r->stage }}</td>
            <td style="text-align:right">{{ number_format($bncSar,0) }}</td>
            <td style="text-align:right">{{ number_format($quotedSar,0) }}</td>
            <td style="text-align:center">{{ (int)$r->quotes_count }}</td>
            <td style="text-align:center">{{ $cov }}%</td>
            <td>
                @if(!empty($rp))
                    {{-- You can customize which parties you want --}}
                    @if(!empty($rp['owners']['name'])) Owner: {{ $rp['owners']['name'] }}<br>@endif
                    @if(!empty($rp['lead_consultant']['name'])) Lead: {{ $rp['lead_consultant']['name'] }}<br>@endif
                    @if(!empty($rp['mep_contractor']['name'])) MEP: {{ $rp['mep_contractor']['name'] }}<br>@endif
                    @if(!empty($rp['mep_contractor']['phone'])) Tel: {{ $rp['mep_contractor']['phone'] }}<br>@endif
                    @if(!empty($rp['mep_contractor']['email'])) Email: {{ $rp['mep_contractor']['email'] }}@endif
                @else
                    <span class="muted">No contact details</span>
                @endif
            </td>
            <td>
                Consultant: {{ $r->consultant ?: '-' }}<br>
                Main: {{ $r->main_contractor ?: '-' }}<br>
                MEP: {{ $r->mep_contractor ?: '-' }}
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="section-title">Not Quoted (Action Required)</div>
<table>
    <thead>
    <tr>
        <th style="width:4%">#</th>
        <th style="width:18%">Project</th>
        <th style="width:10%">City</th>
        <th style="width:8%">Region</th>
        <th style="width:10%">Stage</th>
        <th style="width:12%">BNC Value (SAR)</th>
        <th style="width:18%">Parties / Contacts</th>
        <th style="width:20%">Consultant / Contractors</th>
    </tr>
    </thead>
    <tbody>
    @foreach($notQuoted as $i => $r)
        @php
            $bncSar = ((float)($r->value_usd ?? 0)) * $USD_TO_SAR;
            $rp = is_array($r->raw_parties) ? $r->raw_parties : (json_decode($r->raw_parties ?? '[]', true) ?: []);
        @endphp
        <tr>
            <td>{{ $i+1 }}</td>
            <td><b>{{ $r->project_name }}</b><br><span class="muted">{{ $r->reference_no }}</span></td>
            <td>{{ $r->city }}</td>
            <td>{{ $r->region }}</td>
            <td>{{ $r->stage }}</td>
            <td style="text-align:right">{{ number_format($bncSar,0) }}</td>
            <td>
                @if(!empty($rp))
                    @if(!empty($rp['owners']['name'])) Owner: {{ $rp['owners']['name'] }}<br>@endif
                    @if(!empty($rp['mep_contractor']['name'])) MEP: {{ $rp['mep_contractor']['name'] }}<br>@endif
                    @if(!empty($rp['mep_contractor']['phone'])) Tel: {{ $rp['mep_contractor']['phone'] }}<br>@endif
                    @if(!empty($rp['mep_contractor']['email'])) Email: {{ $rp['mep_contractor']['email'] }}@endif
                @else
                    <span class="muted">No contact details</span>
                @endif
            </td>
            <td>
                Consultant: {{ $r->consultant ?: '-' }}<br>
                Main: {{ $r->main_contractor ?: '-' }}<br>
                MEP: {{ $r->mep_contractor ?: '-' }}
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

</body>
</html>
