@php
    use Carbon\Carbon;
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Pending Quotations Report</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #111;">

<p>Hello,</p>

<p>
    Below is the list of <strong>{{ $projects->count() }}</strong> pending quotations
    (bidding projects with no status update for more than 3 months).
</p>

<p style="margin-top: 6px; font-size: 12px; color: #555;">
    Requested by: {{ $requestedBy }}
</p>

<table width="100%" cellpadding="6" cellspacing="0" border="0"
       style="border-collapse: collapse; margin-top: 10px; font-size: 13px;">
    <thead>
    <tr style="background-color: #f3f4f6;">
        <th align="left" style="border: 1px solid #d1d5db;">Quotation No</th>
        <th align="left" style="border: 1px solid #d1d5db;">Rev</th>
        <th align="left" style="border: 1px solid #d1d5db;">Client</th>
        <th align="left" style="border: 1px solid #d1d5db;">Project</th>
        <th align="left" style="border: 1px solid #d1d5db;">Location</th>
        <th align="left" style="border: 1px solid #d1d5db;">Project Type</th>
        <th align="left" style="border: 1px solid #d1d5db;">Area</th>
        <th align="left" style="border: 1px solid #d1d5db;">Quotation Date</th>
        <th align="right" style="border: 1px solid #d1d5db;">Value (SAR)</th>
        <th align="right" style="border: 1px solid #d1d5db;">Pending</th>
        <th align="left" style="border: 1px solid #d1d5db;">Salesman</th>
        <th align="left" style="border: 1px solid #d1d5db;">Last Comment</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($projects as $p)
        @php
            $qDate = $p->quotation_date
              ? Carbon::parse($p->quotation_date)
              : null;
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
            <td style="border: 1px solid #e5e7eb;">{{ $p->quotation_no }}</td>
            <td style="border: 1px solid #e5e7eb;">{{ $rev ?? '-' }}</td>
            <td style="border: 1px solid #e5e7eb;">{{ $p->client_name }}</td>
            <td style="border: 1px solid #e5e7eb;">{{ $p->project_name }}</td>
            <td style="border: 1px solid #e5e7eb;">{{ $p->project_location }}</td>
            <td style="border: 1px solid #e5e7eb;">{{ $p->project_type }}</td>
            <td style="border: 1px solid #e5e7eb;">{{ $p->area }}</td>
            <td style="border: 1px solid #e5e7eb;">
                {{ $qDate ? $qDate->format('d-M-Y') : '-' }}
            </td>
            <td align="right" style="border: 1px solid #e5e7eb;">
                {{ number_format((float)($p->quotation_value ?? 0), 0) }}
            </td>
            <td align="right" style="border: 1px solid #e5e7eb;">
                {{ $daysPending }}
            </td>
            <td style="border: 1px solid #e5e7eb;">{{ $p->salesperson ?? $p->salesman }}</td>
            <td style="border: 1px solid #e5e7eb;">{{ $p->last_comment ?? '-' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<p style="margin-top: 16px;">
    You can update the status of these quotations in
    <strong>ATAI Dashboard → Quotation Log</strong>.
</p>

<p>Regards,<br>
ATAI Dashboard</p>

</body>
</html>
