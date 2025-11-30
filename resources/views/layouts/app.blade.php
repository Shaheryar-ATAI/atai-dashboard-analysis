<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ATAI')</title>

    {{-- Base styles (global) --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/atai-theme.css') }}?v={{ filemtime(public_path('css/atai-theme.css')) }}"/>
    <link href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.3.2/dist/select2-bootstrap4.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    {{-- Page-level styles (DataTables CSS, etc.) --}}
    @stack('head')
</head>
<body class="bg-light">

{{-- Global navbar --}}
@include('partials.navbar')

{{-- Main content --}}
<main class="container-fluid py-4">
    @yield('content')
</main>

{{-- Core JS (global) --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/highcharts-more.js"></script>
<script src="https://code.highcharts.com/modules/solid-gauge.js"></script>
<script src="https://code.highcharts.com/modules/no-data-to-display.js"></script>
<script src="https://code.highcharts.com/modules/funnel.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
{{-- DataTables after jQuery (jQuery should come from the layout) --}}
<script src="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.js"></script>
{{-- Page-level scripts (DataTables JS, page logic, etc.) --}}


<script src="https://code.highcharts.com/modules/solid-gauge.js"></script>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.full.min.js"></script>





@stack('scripts')
</body>
</html>
