<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ScopeRegion
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();

        if ($u && $u->hasAnyRole(['sales_eastern','sales_central','sales_western'])) {
            // Sales users: force their own region
            $request->merge(['__effective_region' => $u->region]);
        } else {
            // GM/Admin: null means "no filter" (see all)
            $request->merge(['__effective_region' => null]);
        }

        return $next($request);
    }
}
