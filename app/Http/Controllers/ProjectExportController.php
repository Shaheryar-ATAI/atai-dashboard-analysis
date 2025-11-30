<?php

namespace App\Http\Controllers;

use App\Exports\ProjectsMonthlyExport;
use App\Exports\ProjectsWeeklyExport;
use App\Exports\ProjectsYearlyExport;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ProjectExportController extends Controller
{

    protected function baseQuery(Request $request): Builder
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();   // or Auth::user()

        $q = Project::query();

        // 🔐 1) Region restriction (you probably already have something like this)
//        if ($user && ! $user->can('view_all_regions')) {
//            if ($user->region) {
//                $q->where('area', $user->region);  // Eastern / Central / Western
//            }
//        }

        // 🔐 2) Salesman restriction – THIS is what you asked for
        if ($user && ! $user->can('view_all_projects')) {

            // Example: map aliases for that salesman
            // Sohaib -> ['SOHAIB','SOAHIB']
            // Tareq -> ['TARIQ','TAREQ'] etc.
            $aliases = match (strtoupper($user->name)) {
                'SOHAIB' => ['SOHAIB', 'SOAHIB'],
                'TAREQ', 'TARIQ' => ['TAREQ', 'TARIQ'],
                'ABDO', 'ABDU' => ['ABDO', 'ABDU'],
                'Ahmed'=>['Ahmed','Ahmed'],
                default => [strtoupper($user->name)],
            };

            $upperAliases = array_map('strtoupper', $aliases);

            $q->where(function ($qq) use ($upperAliases) {
                $qq->whereIn(DB::raw('UPPER(TRIM(salesman))'), $upperAliases)
                    ->orWhereIn(DB::raw('UPPER(TRIM(salesperson))'), $upperAliases);
            });
        }

        // 3) Existing screen filters (year/month/date_from/date_to/family/area
        //    coming from the request) – keep whatever you already had here.
        //    Example:
        if ($family = $request->input('family')) {
            if ($family !== 'all') {
                $q->where('atai_products', 'LIKE', "%{$family}%");
            }
        }

        // other filters: area, date range, status, etc…

        return $q;
    }
    /**
     * Weekly export: multi-sheet, each sheet is a KSA work week (Sun–Thu).
     */
    public function weekly(Request $request)
    {
        $rows = $this->baseQuery($request)->get();

        $groups = [];
        foreach ($rows as $p) {
            /** @var \App\Models\Project $p */
            $date = $p->quotation_date ?? $p->date_rec;
            if (!$date) {
                $key = 'No Date';
            } else {
                $c     = \Carbon\Carbon::parse($date);
                $start = $c->copy()->startOfWeek(\Carbon\Carbon::SUNDAY);
                $end   = $start->copy()->addDays(4); // Thursday
                $key   = $start->format('d-m-Y').' to '.$end->format('d-m-Y');
            }
            $groups[$key][] = $p;
        }

        ksort($groups);

        $fileName       = 'ATAI-Projects-Weekly-' . now()->format('Ymd_His') . '.xlsx';
        $estimatorName  = optional(\Illuminate\Support\Facades\Auth::user())->name;

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ProjectsWeeklyExport($groups, $estimatorName),
            $fileName
        );
    }


    /**
     * Monthly export: single sheet for selected month.
     * Uses same filters + requires year & month.
     */
    public function monthly(Request $request)
    {
        $year  = (int) $request->input('year');
        $month = (int) $request->input('month');

        if (! $year || ! $month) {
            return back()->with('error', 'Please select year and month for monthly export.');
        }

        // Same date expression as on the log/KPI (quotation_date OR date_rec)
        $dateExpr = DB::raw("COALESCE(DATE(quotation_date), DATE(date_rec))");

        // 🔐 Start from the SAME baseQuery used for the Quotation Log.
        // baseQuery already applies:
        //   - region restriction
        //   - salesman visibility (Sohaib only sees Sohaib, etc.)
        //   - family / area filters from the screen
        $q = $this->baseQuery($request)
            ->whereYear($dateExpr, $year)
            ->whereMonth($dateExpr, $month);

        // ❌ DO NOT re-filter salesman or area from raw request here.
        //     That opens the door for Sohaib seeing Tareq, etc.
        //     baseQuery already knows what the logged-in user is allowed to see.

        $rows = $q
            ->orderBy('quotation_date')
            ->orderBy('date_rec')
            ->get();

        $monthName     = Carbon::createFromDate($year, $month, 1)->format('F Y');
        $fileName      = 'ATAI-Projects-Monthly-' . $monthName . '.xlsx';
        $estimatorName = optional(Auth::user())->name;

        return Excel::download(
            new ProjectsMonthlyExport($rows, $monthName, $estimatorName),
            $fileName
        );
    }
    public function yearly(Request $request)
    {
        $year = (int) $request->input('year');

        if (! $year) {
            return back()->with('error', 'Please select year for yearly export.');
        }

        // Same date expression as on the log/KPI (quotation_date OR date_rec)
        $dateExpr = DB::raw("COALESCE(DATE(quotation_date), DATE(date_rec))");

        // 🔐 Start from the SAME baseQuery used for the Quotation Log.
        // baseQuery already applies:
        //   - region restriction
        //   - salesman visibility (Sohaib only sees Sohaib, etc.)
        //   - family / area filters from the screen
        $q = $this->baseQuery($request)
            ->whereYear($dateExpr, $year);

        // ❌ DO NOT re-filter salesman or area from raw request here.
        //     baseQuery already knows what the logged-in user is allowed to see.

        $rows = $q
            ->orderBy('quotation_date')
            ->orderBy('date_rec')
            ->get();

        $label         = (string) $year;
        $fileName      = 'ATAI-Projects-Yearly-' . $label . '.xlsx';
        $estimatorName = optional(Auth::user())->name;

        return Excel::download(
            new ProjectsYearlyExport($rows, $label, $estimatorName),
            $fileName
        );
    }
}
