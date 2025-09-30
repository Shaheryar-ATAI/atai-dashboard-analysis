{{-- resources/views/auth/login.blade.php --}}
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in — ATAI</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

  <style>
    :root{
      --atai-green:#95c53d;     /* primary (from your swatch) */
      --atai-deep:#2e5a2e;      /* deep green for contrast */
      --atai-mid:#3f7a3f;       /* mid green used in accents */
      --atai-soft:#eff7e6;      /* soft background wash */
      --ring: 0 0 0 .25rem rgba(149,197,61,.25);
    }

    /* Page background with subtle radial wash */
    body {
      min-height: 100vh;
      background:
        radial-gradient(1200px 600px at 50% -150px, rgba(149,197,61,.20), transparent 60%),
        radial-gradient(1000px 400px at 50% 115%, rgba(149,197,61,.12), transparent 60%),
        linear-gradient(180deg, #ffffff 0%, var(--atai-soft) 100%);
    }

    /* Top brand strip */
    .brandbar {
      background: linear-gradient(180deg, rgba(149,197,61,.12), rgba(149,197,61,.05));
      border-bottom: 1px solid rgba(0,0,0,.06);
    }
    .navbar-brand img {
      height: 44px;              /* desktop default */
      width: auto;
    }
    .brand-tagline {
      letter-spacing:.02em;
      color: #5b6b61;
    }

    /* Auth card */
    .auth-card {
      border: 1px solid rgba(0,0,0,.06);
      box-shadow:
        0 20px 60px rgba(0,0,0,.08),
        0 2px 10px rgba(0,0,0,.05);
      border-radius: 18px;
    }
    .auth-card .card-body { padding: 2rem 2rem 1.75rem; }

    /* Logo avatar */
    .logo-avatar {
      width: 84px;
      height: 84px;
      border-radius: 50%;
      display: grid;
      place-items: center;
      margin: 0 auto 0.5rem;
      background: radial-gradient(circle at 30% 30%, #fff 0, #fff 60%, rgba(149,197,61,.15) 61%);
      border: 1px solid rgba(0,0,0,.06);
      box-shadow: 0 8px 24px rgba(149,197,61,.18);
    }
    .logo-avatar img { width: 64px; height: 64px; object-fit: contain; }

    .text-atai { color: var(--atai-deep) !important; }

    /* Inputs */
    .form-control:focus { box-shadow: var(--ring); border-color: var(--atai-green); }

    /* Primary button tuned to your green */
    .btn-atai {
      --bs-btn-bg: var(--atai-green);
      --bs-btn-border-color: var(--atai-green);
      --bs-btn-hover-bg: #86b533;
      --bs-btn-hover-border-color:#86b533;
      --bs-btn-focus-shadow-rgb:149,197,61;
      --bs-btn-active-bg:#7aaa2f;
      --bs-btn-active-border-color:#7aaa2f;
      color:#0b220b;
      font-weight: 600;
      letter-spacing:.02em;
      box-shadow: 0 6px 16px rgba(149,197,61,.35);
    }

    /* Tiny link below button */
    .muted-link { color:#6b786f; }
    .muted-link a { color: var(--atai-mid); text-decoration: none; }
    .muted-link a:hover { text-decoration: underline; }

    /* Show/Hide password button */
    .btn-eye {
      border-color: rgba(0,0,0,.1);
    }

    /* --------- Responsive tweaks --------- */
    @media (max-width: 992px){
      .navbar-brand img { height: 40px; }
    }
    @media (max-width: 768px){
      .navbar-brand img { height: 36px; }
      .brand-tagline { display:none; } /* keep header clean on small screens */
      .auth-card .card-body { padding: 1.5rem; }
    }
    @media (max-width: 576px){
      .navbar-brand img { height: 32px; }
      .auth-card { border-radius: 14px; }
    }
  </style>
</head>

<body>
  {{-- Brand bar --}}
  <nav class="navbar brandbar">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center gap-2" href="#">
        <img src="{{ asset('images/atai-logo.png') }}" alt="ATAI logo">
        <span class="fw-bold text-atai">ATAI</span>
      </a>
      <div class="brand-tagline small d-none d-md-block">
        HVAC • Engineering • Manufacturing
      </div>
    </div>
  </nav>

  <main class="container py-5 py-md-5">
    <div class="row justify-content-center">
      <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">

        <div class="card auth-card">
          <div class="card-body">

            <div class="text-center mb-3">
              <div class="logo-avatar">
                <img src="{{ asset('images/atai-logo.png') }}" alt="ATAI">
              </div>
              <h5 class="mb-0 fw-semibold text-atai">Welcome back</h5>
              <div class="text-secondary small">Sign in to continue</div>
            </div>

            {{-- status (e.g., "You have been logged out.") --}}
            @if (session('status'))
              <div class="alert alert-info py-2">{{ session('status') }}</div>
            @endif

            {{-- generic auth errors --}}
            @if ($errors->has('auth'))
              <div class="alert alert-danger py-2">{{ $errors->first('auth') }}</div>
            @endif

            {{-- validation errors (if any) --}}
            @if ($errors->any() && ! $errors->has('auth'))
              <div class="alert alert-danger py-2">
                <ul class="mb-0 small">
                  @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                  @endforeach
                </ul>
              </div>
            @endif

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
                  <button class="btn btn-outline-secondary btn-eye" type="button" id="togglePwd" aria-label="Show password">
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
                  <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
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

        <div class="text-center small text-secondary mt-3">
          © {{ date('Y') }} ATAI. All rights reserved.
        </div>

      </div>
    </div>
  </main>

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
