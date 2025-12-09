<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PageController extends Controller
{
    // GET /login
    public function login()
    {
        if (Auth::check()) {
            return redirect()->route('projects.index');
        }
        return view('auth.login'); // your login blade
    }


    // POST /login
    public function loginPost(Request $request)
    {
        // accept email (or username if you later alias it to email)
        $request->merge(['email' => trim($request->input('email') ?? '')]);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['auth' => 'Incorrect email or password.']);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // ---- Role groups ----
        $salesRoles = ['sales_eastern', 'sales_central', 'sales_western'];
        $coordinatorRoles = ['project_coordinator_eastern', 'project_coordinator_western'];
        $estimatorRoles = ['estimator'];

        $canViewAll = method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(['gm', 'admin']);

        $hasSalesRole = method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole($salesRoles);

        $hasCoordinatorRole = method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole($coordinatorRoles);

        $hasEstimatorRole = method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole($estimatorRoles);

        // Any regional role (sales or coordinator) – estimators are NOT forced to have region
        $hasRegionalRole = $hasSalesRole || $hasCoordinatorRole;

        // If user is not GM/Admin, not regional (sales/coordinator), and not estimator → block
        if (!$canViewAll && !$hasRegionalRole && !$hasEstimatorRole) {
            Auth::logout();
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['auth' => 'You do not have permission to access this system.']);
        }

        // Region must be set for regional users (sales + coordinators), but NOT for estimators
        if ($hasRegionalRole && empty($user->region)) {
            Auth::logout();
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['auth' => 'Your region is not set. Please contact admin.']);
        }

        // remember in session for /me
        session(['atai.canViewAll' => $canViewAll]);

        $request->session()->regenerate();

        // ---- Decide landing page ----
        // Coordinators → Coordinator dashboard
        // Estimators  → Estimation / Reports page
        // Others (Sales / GM / Admin) → Projects dashboard
        if ($hasCoordinatorRole) {
            $redirectUrl = route('coordinator.index');
        } elseif ($hasEstimatorRole) {
            // send estimators to /estimation/reports
            $redirectUrl = url('/estimation/reports');
            // if you have a named route instead, use:
            // $redirectUrl = route('estimation.reports.index');
        } else {
            $redirectUrl = route('projects.index');
        }

        return redirect()->intended($redirectUrl);
    }


    // POST /logout
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'You have been logged out.');
    }

    // GET /projects   <-- the missing action
    public function projects()
    {
        // Pass the signed-in name to the navbar button (your blade expects $user)
        $user = Auth::user()?->name ?? 'User';

        return view('projects.index', [
            'user' => $user,
        ]);
    }

    // Inquiries Log page (DataTable only)
    public function inquiriesLog(Request $r)
    {
        $user = Auth::user()?->name ?? 'User';

        $salesmen = User::whereNotNull('region')
            ->orderBy('name')
            ->get(['name']);

        return view('projects.inquiries_log', [
            'user' => $user,
            'salesmen' => $salesmen,
        ]);
    }


}
