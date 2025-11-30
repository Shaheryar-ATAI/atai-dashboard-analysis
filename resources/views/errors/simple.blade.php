<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Error' }}</title>
    <style>
        body { font-family: sans-serif; background:#f8f9fa; color:#333; padding:2rem; }
        h1 { color:#b02a37; }
        ul { margin-top:1rem; }
        li { margin-bottom:0.5rem; }
    </style>
</head>
<body>
<h1>{{ $title ?? 'Error' }}</h1>
@if(!empty($issues))
    <ul>
        @foreach($issues as $issue)
            @foreach($issue['messages'] ?? [] as $msg)
                <li>{{ $msg }}</li>
            @endforeach
        @endforeach
    </ul>
@else
    <p>Something went wrong. Please try again.</p>
@endif
</body>
</html>
