<?php
// app/Http/Middleware/PowerBIApiAuth.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PowerBIApiAuth
{
    public function handle(Request $request, Closure $next)
    {
        $tokenFromHeader = $request->header('POWERBI_API_TOKEN'); // must match header name

        if (!$tokenFromHeader || $tokenFromHeader !== config('services.powerbi.token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
