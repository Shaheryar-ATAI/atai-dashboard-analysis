<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function kpis(Request $req)
    {
        $user = $req->user();
        $base = Project::query();

        if (!$user->hasRole(['gm', 'manager'])) {
            $base->where('area', $user->region);
        }

        $countsByArea = (clone $base)
            ->select('area', DB::raw('COUNT(*) as total'))
            ->groupBy('area')
            ->pluck('total', 'area')
            ->map(fn($v) => (int)$v);

        $valueByStatus = (clone $base)
            ->select('status', DB::raw('SUM(COALESCE(price, quotation_value, value_with_vat, 0)) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn($v) => (float)$v);

        return [
            'countsByArea' => $countsByArea,
            'valueByStatus' => $valueByStatus,
        ];
    }
}
