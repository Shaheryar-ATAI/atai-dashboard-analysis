<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Error' }} - ATAI</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          onerror="this.onerror=null;this.href='{{ asset('vendor/bootstrap/5.3.3/bootstrap.min.css') }}';">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @php
        $themeVersion = @filemtime(public_path('css/atai-theme-20260210.css')) ?: time();
    @endphp
    <link rel="stylesheet" href="{{ asset('css/atai-theme-20260210.css') }}?v={{ $themeVersion }}">

    <style>
        .error-wrap {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1.5rem;
        }

        .error-card {
            max-width: 760px;
            width: 100%;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,.14);
            background: linear-gradient(180deg, rgba(20,28,45,.92), rgba(10,16,30,.94));
            box-shadow: 0 22px 48px rgba(0,0,0,.45);
        }

        .error-list li {
            color: #f8d7da;
            margin-bottom: .45rem;
        }
    </style>
</head>
<body class="atai-app atai-future">
<div class="error-wrap">
    <div class="card error-card">
        <div class="card-body p-4 p-md-5">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                <h1 class="h4 mb-0">{{ $title ?? 'Error' }}</h1>
            </div>

            @if(!empty($issues))
                <p class="text-light-emphasis mb-3">Please review the issues below:</p>
                <ul class="error-list ps-3 mb-0">
                    @foreach($issues as $issue)
                        @foreach($issue['messages'] ?? [] as $msg)
                            <li>{{ $msg }}</li>
                        @endforeach
                    @endforeach
                </ul>
            @else
                <p class="text-light-emphasis mb-0">Something went wrong. Please try again.</p>
            @endif
        </div>
    </div>
</div>
</body>
</html>
