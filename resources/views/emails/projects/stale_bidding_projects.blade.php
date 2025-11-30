@php
    use Carbon\Carbon;
    use Carbon\CarbonInterval;
@endphp

    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Stale Bidding Projects Reminder</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #111;">

<p>Dear {{ $regionName }},</p>

<p>
    This is a gentle reminder that the following
    <strong>{{ $projects->count() }}</strong> bidding project(s)
    have had <strong>no status update for more than 3 months</strong>.
    Kindly review and update their status in the ATAI Dashboard.
</p>

<table width="100%" cellpadding="6" cellspacing="0" border="0"
       style="border-collapse: collapse; margin-top: 10px; font-size: 13px;">
    <thead>
    <tr style="background-color: #f3f4f6;">
        <th align="left" style="border: 1px solid #d1d5db;">Quotation No</th>
        <th align="left" style="border: 1px solid #d1d5db;">Client</th>
        <th align="left" style="border: 1px solid #d1d5db;">Project</th>
        <th align="left" style="border: 1px solid #d1d5db;">Project Location</th>
        <th align="left" style="border: 1px solid #d1d5db;">Project Type</th>
        <th align="left" style="border: 1px solid #d1d5db;">Area</th>
        <th align="left" style="border: 1px solid #d1d5db;">Quotation Date</th>
        <th align="right" style="border: 1px solid #d1d5db;">Value (SAR)</th>
        <th align="right" style="border: 1px solid #d1d5db;">Quotation Duration</th>
        <th align="right" style="border: 1px solid #d1d5db;">Sales Man</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($projects as $p)
        @php
            $qDate = $p->quotation_date
              ? Carbon::parse($p->quotation_date)
              : null;

          if ($qDate) {
              $daysDiff = $qDate->diffInDays(today());   // total whole days

              if ($daysDiff === 0) {
                  $daysPending = 'Today';
              } elseif ($daysDiff < 30) {
                  // Only days
                  $daysPending = $daysDiff . ' day' . ($daysDiff > 1 ? 's' : '');
              } elseif ($daysDiff < 365) {
                  // Months + days (approx, 30 days = 1 month)
                  $months = intdiv($daysDiff, 30);
                  $days   = $daysDiff % 30;

                  if ($days > 0) {
                      $daysPending =
                          $months . ' month' . ($months > 1 ? 's' : '') . ' ' .
                          $days   . ' day'   . ($days > 1 ? 's' : '');
                  } else {
                      $daysPending =
                          $months . ' month' . ($months > 1 ? 's' : '');
                  }
              } else {
                  // Years + months + days (same 30-day month approximation)
                  $years   = intdiv($daysDiff, 365);
                  $remain  = $daysDiff % 365;
                  $months  = intdiv($remain, 30);
                  $days    = $remain % 30;

                  $parts = [];
                  if ($years)  $parts[] = $years  . ' year'  . ($years  > 1 ? 's' : '');
                  if ($months) $parts[] = $months . ' month' . ($months > 1 ? 's' : '');
                  if ($days)   $parts[] = $days   . ' day'   . ($days   > 1 ? 's' : '');

                  $daysPending = implode(' ', $parts);
              }
          } else {
              $daysPending = '-';
          }
        @endphp
        <tr>
            <td style="border: 1px solid #e5e7eb;">{{ $p->quotation_no }}</td>
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
                {{ $daysPending !== null ? $daysPending : '-' }}
            </td>

            <td style="border: 1px solid #e5e7eb;">{{ $p->salesperson }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<p style="margin-top: 16px;">
    You can update the status of these projects directly in the
    <strong>ATAI Dashboard → Quotation Log</strong>.
</p>

<p>Best regards,<br>
    ATAI Dashboard – Automated Reminder</p>

</body>
</html>
