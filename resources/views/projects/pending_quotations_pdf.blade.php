@php
    use Carbon\Carbon;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Pending Quotations Report</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 6px 0; }
        .meta { margin-bottom: 10px; color: #444; }
        .meta span { display: inline-block; margin-right: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 4px 6px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; }
        .num { text-align: right; }
        .muted { color: #666; }
    </style>
</head>
<body>
<h1>Pending Quotations Report</h1>
<div class="meta">
    <span>Generated: {{ $generatedAt?->format('d-M-Y H:i') }}</span>
    <span>Total: {{ $projects->count() }}</span>
    @if(!empty($filters))
        <span class="muted">
            Filters:
            @foreach($filters as $k => $v)
                @if(!empty($v))
                    {{ strtoupper($k) }}={{ $v }}
                @endif
            @endforeach
        </span>
    @endif
</div>

<table>
    <thead>
    <tr>
        <th>Quotation No</th>
        <th>Rev</th>
        <th>Client</th>
        <th>Project</th>
        <th>Location</th>
        <th>Project Type</th>
        <th>Area</th>
        <th>Quotation Date</th>
        <th class="num">Value (SAR)</th>
        <th class="num">Pending</th>
        <th>Salesman</th>
        <th>Last Comment</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($projects as $p)
        @php
            $qDate = $p->quotation_date ? Carbon::parse($p->quotation_date) : null;
            $rev = $p->revision_no ?? null;
            if (empty($rev) && !empty($p->quotation_no)) {
                if (preg_match('/(?:^|[\\.\\-])([Rr]\\d+)$/', (string)$p->quotation_no, $m)) {
                    $rev = strtoupper($m[1]);
                }
            }
            $daysPending = '-';
            if ($qDate) {
                $daysDiff = $qDate->diffInDays(today());
                if ($daysDiff === 0) {
                    $daysPending = 'Today';
                } elseif ($daysDiff < 30) {
                    $daysPending = $daysDiff . ' day' . ($daysDiff > 1 ? 's' : '');
                } elseif ($daysDiff < 365) {
                    $months = intdiv($daysDiff, 30);
                    $days = $daysDiff % 30;
                    $daysPending = $months . ' month' . ($months > 1 ? 's' : '');
                    if ($days > 0) {
                        $daysPending .= ' ' . $days . ' day' . ($days > 1 ? 's' : '');
                    }
                } else {
                    $years = intdiv($daysDiff, 365);
                    $remain = $daysDiff % 365;
                    $months = intdiv($remain, 30);
                    $days = $remain % 30;
                    $parts = [];
                    if ($years)  $parts[] = $years  . ' year'  . ($years  > 1 ? 's' : '');
                    if ($months) $parts[] = $months . ' month' . ($months > 1 ? 's' : '');
                    if ($days)   $parts[] = $days   . ' day'   . ($days   > 1 ? 's' : '');
                    $daysPending = implode(' ', $parts);
                }
            }
        @endphp
        <tr>
            <td>{{ $p->quotation_no }}</td>
            <td>{{ $rev ?? '-' }}</td>
            <td>{{ $p->client_name }}</td>
            <td>{{ $p->project_name }}</td>
            <td>{{ $p->project_location }}</td>
            <td>{{ $p->project_type }}</td>
            <td>{{ $p->area }}</td>
            <td>{{ $qDate ? $qDate->format('d-M-Y') : '-' }}</td>
            <td class="num">{{ number_format((float)($p->quotation_value ?? 0), 0) }}</td>
            <td class="num">{{ $daysPending }}</td>
            <td>{{ $p->salesperson ?? $p->salesman }}</td>
            <td>{{ $p->last_comment ?? '-' }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="12" class="muted">No pending quotations found.</td>
        </tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
