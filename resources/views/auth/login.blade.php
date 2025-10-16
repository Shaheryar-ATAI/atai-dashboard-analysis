{{-- resources/views/auth/login.blade.php --}}
    <!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in — ATAI</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">


    <style>
        html, body {
            height: 100%;
            margin: 0;
            background: #0a0f1d;              /* fallback in case hero doesn't fill */
        }
        :root{
            --atai-green: #95c53d;
            --atai-deep:  #2e5a2e;
            --ring: 0 0 0 .25rem rgba(149,197,61,.25);

            /* hero theme */
            --cta-orange: #ff6a2a;
            --cta-orange-700: #e45717;
            --hero-ink: #eef3ff;
        }

        /* ===========================
           HERO LOGIN (split image + form)
           =========================== */
        body{ min-height:100vh; }

        .login-hero{
            min-height: 100vh;                 /* fill full height */
            display: block;
                       position: relative; isolation:isolate;
            color: var(--hero-ink);
            background:
                radial-gradient(900px 420px at 65% 15%, rgba(255,255,255,.06), transparent 40%),
                linear-gradient(160deg, #12192b 0%, #0b1120 60%, #0a0f1d 100%);
            overflow:hidden;
        }
        .login-hero::before{
            content:""; position:absolute; inset:0; z-index:-2;
            background: url('{{ asset('images/login-bg.jpg') }}') center/cover no-repeat;
            opacity:.22; filter: blur(1px);
        }
        .login-hero .container{
            min-height: 100vh;                 /* was 92vh */
            align-items: center;

            display: grid;
            grid-template-columns: 1.05fr 0.95fr; gap: 2rem;
            padding-top: 2rem; padding-bottom: 2rem;
        }

        /* Left side: big ATAI logo + small kicker/headline (optional) */
        .hero-left{
            display:flex; align-items:center; justify-content:center; position:relative;
        }
        .hero-left .logo-wrap{
            text-align:center;
        }
        .hero-left .kicker{
            font-size:.8rem; letter-spacing:.18em; text-transform:uppercase;
            color: rgba(149,197,61,.85);
            margin-bottom:.35rem;
        }
        .hero-left h1{
            color:#fff; font-weight:800; line-height:1.1; margin-bottom:1rem;
        }
        .hero-logo{
            width: 340px; max-width: 80%; height:auto;
            filter: drop-shadow(0 22px 40px rgba(0,0,0,.55));
            margin: .5rem auto 0;
        }

        /* Right side: the card */
        .auth-card{
            border: 1px solid rgba(255,255,255,.14);
            background: rgba(255,255,255,.07);
            border-radius: 18px;
            box-shadow: 0 18px 50px rgba(0,0,0,.45), inset 0 1px 0 rgba(255,255,255,.08);
            color: #fff;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .auth-card .card-body{ padding: 1.75rem; }

        .logo-avatar{ display:none; } /* not needed now */

        .text-atai{ color:#cfe8a1 !important; }

        .form-label{ color:#dbe7ff; }
        .form-control, .form-select{
            background: rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18); color:#fff;
        }
        .form-control::placeholder{ color: rgba(255,255,255,.65); }
        .form-control:focus{ box-shadow: var(--ring); border-color: var(--atai-green); }

        .btn-atai{
            --bs-btn-bg: var(--cta-orange);
            --bs-btn-border-color: var(--cta-orange-700);
            --bs-btn-hover-bg: #ff6a2a;
            --bs-btn-hover-border-color:#e45717;
            --bs-btn-focus-shadow-rgb:255,106,42;
            --bs-btn-active-bg:#e45717;
            --bs-btn-active-border-color:#d64f14;
            color:#fff; font-weight:800; letter-spacing:.02em;
            box-shadow: 0 10px 22px rgba(255,106,42,.25);
        }
        .muted-link{ color:#c3cee6; }
        .muted-link a{ color:#ffffff; text-decoration: underline; text-decoration-color: rgba(255,255,255,.45); }

        .brandbar{ display:none; } /* hide old top strip on this page */

        /* Responsive */
        @media (max-width: 991.98px){
            .login-hero .container{ grid-template-columns: 1fr; gap: 1.5rem; min-height:auto; }
            .hero-left{ order:2; }
            .hero-logo{ width: 260px; }
        }
        @media (max-width: 575.98px){
            .auth-card{ border-radius: 14px; }
            .auth-card .card-body{ padding: 1.25rem; }
        }
    </style>
</head>

<body>
<section class="login-hero">
    <div class="container">
        {{-- LEFT: ATAI logo and headline --}}
        <div class="hero-left">
            <div class="logo-wrap">
                <div class="kicker">Your Access</div>
                <h1 class="h2 mb-3">Sign in to ATAI</h1>
                <img class="hero-logo" src="{{ asset('images/atai-logo.png') }}" alt="ATAI">
            </div>
        </div>

        {{-- RIGHT: Login form card (keeps your existing validation & routes) --}}
        <div>
            <div class="card auth-card">
                <div class="card-body">

                    {{-- status (e.g., "You have been logged out.") --}}
                    @if (session('status'))
                        <div class="alert alert-info py-2 mb-3">{{ session('status') }}</div>
                    @endif

                    {{-- generic auth errors --}}
                    @if ($errors->has('auth'))
                        <div class="alert alert-danger py-2 mb-3">{{ $errors->first('auth') }}</div>
                    @endif

                    {{-- validation errors --}}
                    @if ($errors->any() && ! $errors->has('auth'))
                        <div class="alert alert-danger py-2 mb-3">
                            <ul class="mb-0 small">
                                @foreach ($errors->all() as $e)
                                    <li>{{ $e }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <h5 class="mb-1 fw-semibold text-atai">Welcome back</h5>
                    <div class="text-secondary small mb-3" style="color:#cbd5e1 !important;">Use your credentials to continue</div>

                    <form method="POST" action="{{ route('login.post') }}" novalidate autocomplete="on">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email"
                                   name="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   placeholder="you@company.com"
                                   value="{{ old('email') }}"
                                   required autofocus>
                            @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label d-flex justify-content-between">
                                <span>Password</span>
                            </label>
                            <div class="input-group">
                                <input type="password"
                                       name="password"
                                       id="password"
                                       class="form-control @error('password') is-invalid @enderror"
                                       placeholder="••••••••"
                                       required>
                                <button class="btn btn-outline-light" type="button" id="togglePwd" aria-label="Show password"
                                        style="border-color: rgba(255,255,255,.25);">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8"/>
                                        <path d="M8 5.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5"/>
                                    </svg>
                                </button>
                                @error('password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember"
                                    {{ old('remember') ? 'checked' : '' }}>
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>
                        </div>

                        <button class="btn btn-atai w-100" type="submit">Sign in</button>

                        <p class="text-center mt-3 mb-0 muted-link small">
                            Need access? Contact your administrator.
                        </p>
                    </form>

                </div>
            </div>

            <div class="text-center small mt-3" style="color:#a8b2c7;">
                © {{ date('Y') }} ATAI. All rights reserved.
            </div>
        </div>
    </div>
</section>

<script>
    // Show / hide password
    (function(){
        const btn = document.getElementById('togglePwd');
        const pwd = document.getElementById('password');
        if(btn && pwd){
            btn.addEventListener('click', function(){
                const show = pwd.getAttribute('type') === 'password';
                pwd.setAttribute('type', show ? 'text' : 'password');
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            });
        }
    })();
</script>
</body>
</html>
