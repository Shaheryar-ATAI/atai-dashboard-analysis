<?php

namespace App\Http\Controllers;

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
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['auth' => 'Incorrect email or password.']);
        }

        $user = Auth::user();

        // Works with Spatie OR the lightweight fallback helpers we added
        $canViewAll      = method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['gm','admin']);
        $hasRegionalRole = method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['sales_eastern','sales_central','sales_western']);

        if (! $canViewAll && ! $hasRegionalRole) {
            Auth::logout();
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['auth' => 'You do not have permission to access this system.']);
        }

        if ($hasRegionalRole && empty($user->region)) {
            Auth::logout();
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['auth' => 'Your region is not set. Please contact admin.']);
        }

        // remember in session for /me
        session(['atai.canViewAll' => $canViewAll]);

        $request->session()->regenerate();

        return redirect()->intended(route('projects.index'));
    }

    // POST /logout
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been logged out.');
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
}
