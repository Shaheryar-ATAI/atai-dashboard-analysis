<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function kpis(Request $req)
    {
        $user = $req->user();
        $q = Project::query();

        if (!$user->hasRole(['gm','manager'])) {
            $q->where('area', $user->region);
        }

        $all = $q->get();

        $countsByArea = $all->groupBy('area')->map->count();
        $valueByStatus = $all->groupBy('status')->map(fn($g) => (float) $g->sum('price'));

        return [
            'countsByArea' => $countsByArea,
            'valueByStatus' => $valueByStatus,
        ];
    }
}
