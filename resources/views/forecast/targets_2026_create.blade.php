@extends('layouts.app')
<style>
    /* ======= TARGET 2026 FORM – LIGHT MODE OVERRIDE ======= */

    .container-fluid {
        background: rgba(20, 26, 42, 0.96) !important;
        color: #000000 !important;
        padding: 20px;
    }

    /* All tables brighter */
    table, td, th {
        background: #ffffff !important;
        color: #000000 !important;
        border-color: #9f9e9e !important;
    }

    /* Section headers (normal table header cells) */
    th {
        background: #989898 !important;
        color: #000;
        font-weight: 600;
    }

    h6 {
        color: #76fad4 !important;
        font-weight: 700;
    }

    /* Inputs + selects – force light mode */
    input,
    select,
    textarea,
    .form-control,
    .form-select {
        background-color: #ffffff !important;
        color: #000000 !important;
        border: 1px solid #76fad4 !important;
        box-shadow: none !important;
    }

    input:focus,
    select:focus,
    .form-control:focus,
    .form-select:focus {
        background-color: #ffffff !important;
        border-color: #4a90e2 !important;
        box-shadow: none !important;
        color: #000000 !important;
    }

    /* Selects specifically inside table cells (fix black bars) */
    .table td select,
    .table td .form-control {
        background-color: #ffffff !important;
        color: #000000 !important;
    }

    /* Options inside dropdown list */
    .table td select option {
        background-color: #ffffff;
        color: #000000;
    }

    /* Remove dark overlay from app layout */
    body {
        background: #fcfcfc !important;
        color: #000000 !important;
    }

    /* Remove dark theme inherited from atai-theme.css */
    .card,
    .table {
        background: #ffffff !important;
        color: #000000 !important;
    }

    /* Buttons */
    .btn-primary {
        background: #2c7be5 !important;
        border-color: #2c7be5 !important;
    }

    .btn-primary:hover {
        background: #1a68d1 !important;
    }

    /* ===== Special band above the table (Sales Data / Target Data) ===== */
    .target-section-header th {
        background: #dbe8f6 !important;  /* light blue/grey band */
        color: #000000 !important;
        font-weight: 700;
    }
</style>


@section('content')
    <div class="container-fluid" style="font-size:12px;">
        <form method="POST" action="{{ route('forecast.targets2026.downloadFromForm') }}">
            @csrf

            {{-- Top title row --}}
            <table class="table table-sm" style="width:100%; border:none;">
                <tr>
                    <td style="border:none;">
                        <strong>Annual Sales Target – {{ $year }}</strong><br>
                        <small>ATAI Group</small>
                    </td>
                    <td style="border:none; text-align:center;">
                        <strong>{{ $year }}</strong>
                    </td>
                    <td style="border:none; text-align:right;">
                        Submission Date
                    </td>
                    <td style="border:none; width:150px;">
                        <input type="date" name="submissionDate"
                               value="{{ now()->format('Y-m-d') }}"
                               class="form-control form-control-sm" readonly>
                    </td>
                </tr>
            </table>

            {{-- Sales + Target Data --}}
            <table class="table table-bordered table-sm" style="width:100%;">
                <tr class="target-section-header">
                    <th style="width:45%;">Sales Data</th>
                    <th style="width:55%;">Target Data</th>
                </tr>
                <tr>
                    {{-- Sales Data (left) --}}
                    <td>
                        <div class="mb-1 row">
                            <label class="col-3 col-form-label col-form-label-sm">Region</label>
                            <div class="col-9">
                                <select name="region" class="form-control form-control-sm">
                                    <option value="All Regions"  {{ $region=='All Regions'?'selected':'' }}>All Regions</option>
                                    <option value="Eastern"      {{ $region=='Eastern'?'selected':'' }}>Eastern</option>
                                    <option value="Central"      {{ $region=='Central'?'selected':'' }}>Central</option>
                                    <option value="Western"      {{ $region=='Western'?'selected':'' }}>Western</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-1 row">
                            <label class="col-3 col-form-label col-form-label-sm">Year</label>
                            <div class="col-9">
                                <input type="number" name="year"
                                       class="form-control form-control-sm"
                                       value="{{ $year }}">
                            </div>
                        </div>

                        <div class="mb-1 row">
                            <label class="col-3 col-form-label col-form-label-sm">Issued By</label>
                            <div class="col-9">
                                <input type="text" name="issuedBy"
                                       class="form-control form-control-sm"
                                       value="{{ $issuedBy }}">
                            </div>
                        </div>

                        <div class="mb-1 row">
                            <label class="col-3 col-form-label col-form-label-sm">Issued Date</label>
                            <div class="col-9">
                                <input type="date" name="issuedDate"
                                       class="form-control form-control-sm"
                                       value="{{ $issuedDate }}">
                            </div>
                        </div>
                    </td>

                    {{-- Target Data (right) --}}
                    <td>
                        <div class="mb-1 row">
                            <label class="col-4 col-form-label col-form-label-sm">
                                Annual Target (SAR)
                            </label>
                            <div class="col-8">
                                <input type="number" name="annual_target"
                                       class="form-control form-control-sm"
                                       value="50000000" id="annual_target">
                            </div>
                        </div>

                        <div class="mb-1 row">
                            <label class="col-4 col-form-label col-form-label-sm">
                                Monthly Avg Target
                            </label>
                            <div class="col-8">
                                <input type="text" id="monthly_avg"
                                       class="form-control form-control-sm" readonly>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>

            {{-- A) New Orders Expected This Year --}}
            <h6 class="mt-3">A) New Orders Expected This Year -</h6>

            <table class="table table-bordered table-sm">
                <thead style="background:#f5f9f2;">
                <tr>
                    <th style="width:40px;">Serial</th>
                    <th>Customer Name</th>
                    <th>Products</th>
                    <th>Project Name</th>
                    <th>Quotation No.</th>
                    <th style="width:120px;">Value</th>
                    <th>Status</th>
                    <th>Forecast Criteria</th>
                    <th>Remarks</th>
                </tr>
                </thead>
                <tbody>
                @for($i = 0; $i < 25; $i++)
                    <tr>
                        <td>{{ $i+1 }}</td>

                        <td><input name="orders[{{ $i }}][customer]"  class="form-control form-control-sm"></td>

                        <td><input name="orders[{{ $i }}][product]"   class="form-control form-control-sm"></td>

                        <td><input name="orders[{{ $i }}][project]"   class="form-control form-control-sm"></td>

                        <td><input name="orders[{{ $i }}][quotation]" class="form-control form-control-sm"></td>

                        <td><input name="orders[{{ $i }}][value]"     class="form-control form-control-sm"
                                   type="number" step="0.01"></td>

                        {{-- STATUS DROPDOWN --}}
                        <td>
                            <select name="orders[{{ $i }}][status]" class="form-control form-control-sm">
                                <option value="">--</option>
                                <option value="In-hand">In-hand</option>
                                <option value="Bidding">Bidding</option>
                            </select>
                        </td>

                        {{-- FORECAST CRITERIA DROPDOWN --}}
                        <td>
                            <select name="orders[{{ $i }}][forecast_criteria]" class="form-control form-control-sm">
                                <option value="">--</option>
                                <option value="A">A — Commercial matters agreed & MS approved</option>
                                <option value="B">B — Commercial matters agreed OR MS approved</option>
                                <option value="C">C — Neither commercial matters nor MS achieved</option>
                                <option value="D">D — Project is in bidding stage</option>
                            </select>
                        </td>

                        <td><input name="orders[{{ $i }}][remarks]" class="form-control form-control-sm"></td>
                    </tr>
                @endfor
                </tbody>
            </table>

            <div class="text-end mt-3">
                <button type="submit" class="btn btn-primary btn-sm">
                    Download PDF
                </button>
            </div>
        </form>
    </div>

    {{-- simple JS to auto-calc Monthly Avg --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const annual  = document.getElementById('annual_target');
            const monthly = document.getElementById('monthly_avg');

            // Passed from Laravel (best practice)
            const userRegion = "{{ auth()->user()->region ?? '' }}".trim().toUpperCase();

            function applyRegionRule() {
                if (userRegion === "WESTERN") {
                    annual.value = 36000000;
                }
                // Add future rules here:
                else if (userRegion === "EASTERN") { annual.value = 50000000; }
                else if (userRegion === "CENTRAL") { annual.value = 50000000; }
            }

            function recalc() {
                const v = parseFloat(annual.value || '0');
                monthly.value = v > 0 ? Math.round(v / 12).toLocaleString() : '';
            }

            // Run once on page load
            applyRegionRule();
            recalc();

            // Recalculate whenever user edits target
            annual.addEventListener('input', recalc);
        });
    </script>
@endsection
