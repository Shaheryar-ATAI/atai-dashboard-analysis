@extends('layouts.app')

@section('title', 'ATAI - Performance Overview')

@push('head')
<style>
    .perf-landing .perf-kpi-card {
        min-height: 118px;
    }

    .perf-landing .perf-kpi-label {
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #9db0d8;
    }

    .perf-landing .perf-kpi-value {
        font-size: 1.55rem;
        font-weight: 800;
    }

    .perf-landing .perf-subtle {
        color: #9fb1d5;
    }

    .perf-chart-box {
        min-height: 360px;
    }
</style>
@endpush

@section('content')
<div class="container-fluid perf-landing">
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap gap-3 align-items-end justify-content-between">
            <div>
                <h4 class="mb-1">Performance Overview</h4>
                <div class="perf-subtle">Quotations vs POs for {{ $year }}</div>
            </div>

            <form method="GET" action="{{ route('performance.index') }}" class="d-flex align-items-end gap-2">
                <div>
                    <label class="form-label small text-uppercase mb-1">Year</label>
                    <select name="year" class="form-select form-select-sm">
                        @for($y = now()->year; $y >= now()->year - 5; $y--)
                            <option value="{{ $y }}" @selected((int)request('year', $year) === $y)>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Update</button>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="card perf-kpi-card">
                <div class="card-body">
                    <div class="perf-kpi-label">Quotation Total</div>
                    <div class="perf-kpi-value" id="kpiQuotation"></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card perf-kpi-card">
                <div class="card-body">
                    <div class="perf-kpi-label">PO Total</div>
                    <div class="perf-kpi-value" id="kpiPo"></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card perf-kpi-card">
                <div class="card-body">
                    <div class="perf-kpi-label">Gap</div>
                    <div class="perf-kpi-value" id="kpiGap"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold">Total Comparison</div>
        <div class="card-body">
            <div id="poVsQuote" class="perf-chart-box"></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-semibold">Area Comparison</div>
        <div class="card-body">
            <div id="poVsQuoteArea" class="perf-chart-box"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const totals = {
            quotations: Number(@json($quotationTotal ?? 0)),
            pos: Number(@json($poTotal ?? 0)),
            year: Number(@json($year ?? now()->year)),
        };

        const byArea = @json($byArea ?? []);

        function fmtSar(value) {
            return new Intl.NumberFormat('en-SA', {
                style: 'currency',
                currency: 'SAR',
                maximumFractionDigits: 0,
            }).format(Number(value || 0));
        }

        const gap = Math.abs(totals.quotations - totals.pos);

        const qNode = document.getElementById('kpiQuotation');
        const poNode = document.getElementById('kpiPo');
        const gapNode = document.getElementById('kpiGap');
        if (qNode) qNode.textContent = fmtSar(totals.quotations);
        if (poNode) poNode.textContent = fmtSar(totals.pos);
        if (gapNode) gapNode.textContent = fmtSar(gap);

        if (window.Highcharts && document.getElementById('poVsQuote')) {
            Highcharts.chart('poVsQuote', {
                chart: { type: 'column', backgroundColor: 'transparent' },
                title: { text: 'Quotations vs POs (Total)' },
                xAxis: { categories: [`Year ${totals.year}`], crosshair: true },
                yAxis: {
                    min: 0,
                    title: { text: 'Value (SAR)' },
                    labels: { formatter: function () { return fmtSar(this.value).replace('SAR', ''); } }
                },
                tooltip: {
                    shared: true,
                    useHTML: true,
                    formatter: function () {
                        return this.points.map(function (p) {
                            return `<span style="color:${p.color}">&#9679;</span> ${p.series.name}: <b>${fmtSar(p.y)}</b>`;
                        }).join('<br>');
                    }
                },
                plotOptions: {
                    column: {
                        pointPadding: 0.2,
                        borderWidth: 0,
                        borderRadius: 4,
                        dataLabels: {
                            enabled: true,
                            formatter: function () { return fmtSar(this.y); }
                        }
                    }
                },
                series: [
                    { name: 'Quotations', data: [totals.quotations], color: '#4c63ff' },
                    { name: 'POs Received', data: [totals.pos], color: '#36d399' },
                ],
                credits: { enabled: false }
            });
        }

        if (window.Highcharts && document.getElementById('poVsQuoteArea')) {
            if (!Array.isArray(byArea) || byArea.length === 0) {
                document.getElementById('poVsQuoteArea').innerHTML = '<div class="perf-subtle">No area-level data available for this year.</div>';
                return;
            }

            const categories = byArea.map(function (row) { return row.area || 'Not Mentioned'; });
            const quotations = byArea.map(function (row) { return Number(row.quotations || 0); });
            const pos = byArea.map(function (row) { return Number(row.pos || 0); });

            Highcharts.chart('poVsQuoteArea', {
                chart: { type: 'column', backgroundColor: 'transparent' },
                title: { text: 'Quotations vs POs by Area' },
                xAxis: { categories: categories, crosshair: true },
                yAxis: {
                    min: 0,
                    title: { text: 'Value (SAR)' },
                    labels: { formatter: function () { return fmtSar(this.value).replace('SAR', ''); } }
                },
                tooltip: {
                    shared: true,
                    useHTML: true,
                    formatter: function () {
                        return `<b>${this.x}</b><br>` + this.points.map(function (p) {
                            return `<span style="color:${p.color}">&#9679;</span> ${p.series.name}: <b>${fmtSar(p.y)}</b>`;
                        }).join('<br>');
                    }
                },
                plotOptions: {
                    column: {
                        pointPadding: 0.2,
                        borderWidth: 0,
                        borderRadius: 4,
                        dataLabels: {
                            enabled: true,
                            formatter: function () { return fmtSar(this.y); }
                        }
                    }
                },
                series: [
                    { name: 'Quotations', data: quotations, color: '#60a5fa' },
                    { name: 'POs Received', data: pos, color: '#34d399' },
                ],
                credits: { enabled: false }
            });
        }
    })();
</script>
@endpush
