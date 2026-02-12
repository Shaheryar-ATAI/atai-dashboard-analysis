@extends('layouts.app')

@section('title', 'ATAI Dashboard')

@push('head')
<style>
    .welcome-shell {
        min-height: calc(100vh - var(--atai-navbar-offset, 80px) - 4rem);
        display: grid;
        place-items: center;
    }

    .welcome-card {
        max-width: 920px;
        width: 100%;
        border-radius: 18px;
        padding: 2.25rem;
        background:
            radial-gradient(780px 280px at 8% 0%, rgba(149, 197, 61, .15), transparent 55%),
            radial-gradient(760px 300px at 92% 0%, rgba(76, 99, 255, .22), transparent 58%),
            linear-gradient(160deg, rgba(14, 20, 36, .95) 0%, rgba(11, 16, 30, .95) 100%);
        border: 1px solid rgba(255, 255, 255, .12);
        box-shadow: 0 28px 60px rgba(0, 0, 0, .38);
    }

    .welcome-kicker {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        padding: .35rem .8rem;
        border-radius: 999px;
        font-size: .76rem;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #b8d7ff;
        border: 1px solid rgba(184, 215, 255, .26);
        background: rgba(20, 28, 47, .62);
    }

    .welcome-card p {
        color: #b5c4e4;
        max-width: 62ch;
    }
</style>
@endpush

@section('content')
<div class="container-fluid welcome-shell">
    <div class="welcome-card">
        <div class="welcome-kicker mb-3">
            <i class="bi bi-grid"></i>
            ATAI Intelligence Portal
        </div>

        <h1 class="mb-2">One Platform for Quotations, Sales Orders, Forecast, and KPI Tracking</h1>
        <p class="mb-4">
            Use the navigation to access operational dashboards, logs, and exports in a single consistent workspace.
        </p>

        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('projects.index') }}" class="btn btn-primary">Open Dashboard</a>
            <a href="{{ route('projects.inquiries_log') }}" class="btn btn-outline-light">Open Quotation Log</a>
        </div>
    </div>
</div>
@endsection
