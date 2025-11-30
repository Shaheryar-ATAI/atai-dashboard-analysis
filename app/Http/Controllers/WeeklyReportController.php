<?php

namespace App\Http\Controllers;

use App\Models\WeeklyReport;
use App\Models\WeeklyReportItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WeeklyReportController extends Controller
{
    public function create()
    {
        return view('weekly.create');
    }

    public function save(Request $request)
    {
        // 1) Validate (incl. strict quotation format)
        $rules = [
            'engineer_name' => 'required|string|max:190',
            'week_date'     => 'required|date',
            'rows'          => 'array',

            'rows.*.customer'       => 'nullable|string|max:190',
            'rows.*.project'        => 'nullable|string|max:190',
            'rows.*.location'       => 'nullable|string|max:190',
            'rows.*.value'          => 'nullable|string|max:50',
            'rows.*.status'         => 'nullable|string|max:50',
            'rows.*.contact_name'   => 'nullable|string|max:190',
            'rows.*.contact_mobile' => 'nullable|string|max:32',
            'rows.*.visit_date'     => 'nullable|date',
            'rows.*.notes'          => 'nullable|string|max:500',

            // Quotation format: S.<num>.<num>.<num>.MH.R<num>  e.g. S.4135.1.2605.MH.R0
            'rows.*.quotation_no' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^S\.\d{4}\.\d+\.\d{4}\.[A-Z]{2}\.R\d+$/i',
            ],
        ];

        $messages = [
            'rows.*.quotation_no.regex' =>
                'Quotation # must be like S.0000.0.0000.XX.R0 (S.<num>.<num>.<num>.MH.R<num>).',
        ];

        $data = $request->validate($rules, $messages);

        // 2) Helpers
        $toE164 = function (?string $raw): ?string {
            if (!$raw) return null;
            $digits = preg_replace('/\D/', '', $raw);
            if (str_starts_with($digits, '966')) $digits = substr($digits, 3);
            if (str_starts_with($digits, '0'))   $digits = substr($digits, 1);
            $digits = substr($digits, 0, 9); // expect 5xxxxxxxx
            return $digits ? ('+966' . $digits) : null;
        };

        // Only these statuses are allowed (align with your ENUM)
        $ALLOWED = [
            'Inquiry','Quoted','Follow-up','Negotiation',
            'In-Hand','Lost','On Hold','Postponed','Closed'
        ];

        // 3) Normalize the rows BEFORE insert (uppercase & trim quotation, remove whitespace)
        $rows = $data['rows'] ?? [];
        foreach ($rows as $k => $r) {
            if (isset($r['quotation_no'])) {
                $q = strtoupper(trim($r['quotation_no']));
                // remove all spaces inside
                $q = preg_replace('/\s+/', '', $q);
                $rows[$k]['quotation_no'] = $q === '' ? null : $q;
            }
        }

        // 4) Save
        $reportId = DB::transaction(function () use ($data, $rows, $toE164, $ALLOWED) {
            $report = WeeklyReport::create([
                'user_id'       => auth()->id(),
                'engineer_name' => $data['engineer_name'],
                'week_start'    => $data['week_date'],
            ]);

            $i = 0;
            foreach ($rows as $r) {
                // skip fully empty rows
                $isEmpty = !array_filter([
                    $r['customer'] ?? null,
                    $r['project'] ?? null,
                    $r['location'] ?? null,
                    $r['value'] ?? null,
                    $r['status'] ?? null,
                    $r['contact_name'] ?? null,
                    $r['contact_mobile'] ?? null,
                    $r['visit_date'] ?? null,
                    $r['notes'] ?? null,
                    $r['quotation_no'] ?? null,
                ], fn($v) => ($v !== null && $v !== ''));

                if ($isEmpty) continue;

                // numeric value
                $val = 0;
                if (isset($r['value']) && trim($r['value']) !== '') {
                    $val = (float) preg_replace('/[^\d.\-]/', '', (string) $r['value']);
                }

                // whitelist status
                $statusRaw = trim((string)($r['status'] ?? ''));
                $status = $statusRaw === '' ? null : $statusRaw;
                if ($status !== null && !in_array($status, $ALLOWED, true)) {
                    $status = null;
                }

                WeeklyReportItem::create([
                    'weekly_report_id'    => $report->id,
                    'row_no'              => ++$i,
                    'customer_name'       => $r['customer'] ?? null,
                    'project_name'        => $r['project'] ?? null,
                    'quotation_no'        => $r['quotation_no'] ?? null,
                    'project_location'    => $r['location'] ?? null,
                    'value_sar'           => $val,
                    'project_status'      => $status,
                    'contact_name'        => $r['contact_name'] ?? null,
                    'contact_mobile_e164' => $toE164($r['contact_mobile'] ?? null),
                    'visit_date'          => $r['visit_date'] ?? null,
                    'notes'               => $r['notes'] ?? null,
                ]);
            }

            return $report->id;
        });

        // 5) Redirect back with success + id (to enable PDF button)
        return redirect()
            ->route('weekly.create')
            ->with('ok', true)
            ->with('report_id', $reportId);
    }


    public function pdf(\App\Models\WeeklyReport $report)
    {
        $report->load(['items' => fn($q) => $q->orderBy('row_no')]);
        $user = auth()->user();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('weekly.pdf', [
            'report' => $report,
            'items'  => $report->items,
            'user'   => $user,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('Weekly_Report.pdf');
    }
}
