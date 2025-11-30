@extends('layouts.app')

@section('title', 'ATAI Sales Orders — Live')

@push('head')
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Product Summary — Performance</title>

  <style>
      .badge-total { font-weight: 600; }

      .table-sticky thead th {
          position: sticky;
          top: 0;
          z-index: 1;
          background: rgba(15, 23, 42, 0.96); /* dark navy to match dashboard */
          color: #e5e7eb;
          text-align: center;
          text-transform: uppercase;
          letter-spacing: .08em;
          font-size: .72rem;
          border-bottom: 1px solid rgba(148, 163, 184, .5);
      }

      .section-title {
          text-align: center;
          text-transform: uppercase;
          letter-spacing: .08em;
          font-weight: 600;
          font-size: .8rem;
          color: #f9fafb;
          margin-bottom: 1.25rem;
      }
  </style>
@endpush
@section('content')

@php $u = auth()->user(); @endphp
<main class="container-fluid py-4">


  <div class="card mb-3">
    <div class="card-body">
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <h6 class="mb-0">Product Summary</h6>
        <div class="ms-auto d-flex align-items-center gap-2">
          <div class="input-group input-group-sm" style="width: 120px;">
            <span class="input-group-text">Year</span>
            <select id="yearSelect" class="form-select">
              @for($y = now()->year; $y >= now()->year - 5; $y--)
                <option value="{{ $y }}" @selected($y==$year)>{{ $y }}</option>
              @endfor
            </select>
          </div>
          <span class="badge-total text-bg-info" id="badgeInq">Inquiries: SAR 0</span>
          <span class="badge-total text-bg-primary" id="badgePO">POs: SAR 0</span>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
        <h6 class="card-title mb-2 section-title">Products comparison (Inquiries vs POs)</h6>
      <div id="chartProducts" style="height: 360px;"></div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
        <h6 class="card-title section-title">Inquiries — by Product</h6>
      <div class="table-responsive">
        <table id="tblProdInquiries" class="table table-striped table-sticky w-100">
          <thead>
            <tr>
              <th>Product</th>
              <th>Jan</th><th>Feb</th><th>Mar</th><th>Apr</th><th>May</th><th>Jun</th>
              <th>Jul</th><th>Aug</th><th>Sep</th><th>Oct</th><th>Nov</th><th>Dec</th>
              <th>Total</th>
            </tr>
          </thead>
        </table>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
        <h6 class="card-title section-title">POs received — by Product</h6>
      <div class="table-responsive">
        <table id="tblProdPOs" class="table table-striped table-sticky w-100">
          <thead>
            <tr>
              <th>Product</th>
              <th>Jan</th><th>Feb</th><th>Mar</th><th>Apr</th><th>May</th><th>Jun</th>
              <th>Jul</th><th>Aug</th><th>Sep</th><th>Oct</th><th>Nov</th><th>Dec</th>
              <th>Total</th>
            </tr>
          </thead>
        </table>
      </div>
    </div>
  </div>

</main>
@endsection
@push('scripts')
    <script>
        const YEAR_INIT  = {{ (int) $year }};
        const DT_URL_INQ = @json(route('performance.products.data', ['kind' => 'inq']));
        const DT_URL_PO  = @json(route('performance.products.data', ['kind' => 'po']));
        const KPI_URL    = @json(route('performance.products.kpis'));

        const fmtSAR = n => new Intl.NumberFormat('en-SA', {
            style: 'currency',
            currency: 'SAR',
            maximumFractionDigits: 0
        }).format(Number(n || 0));

        // common columns for both tables
        const columns = [
            { data: 'product', name: 'product', orderable: false, searchable: false },
            { data: 'jan' }, { data: 'feb' }, { data: 'mar' }, { data: 'apr' }, { data: 'may' }, { data: 'jun' },
            { data: 'jul' }, { data: 'aug' }, { data: 'sep' }, { data: 'oct' }, { data: 'nov' }, { data: 'december' },
            { data: 'total' }
        ];

        const moneyRender = function (data, type) {
            if (type === 'display' || type === 'filter') {
                return fmtSAR(data);
            }
            return data;
        };

        function initTable(selector, url, badgeSelector, badgeLabel) {
            return $(selector).DataTable({
                processing: true,
                serverSide: true,
                searching: true,
                order: [[13, 'desc']], // order by Total
                ajax: {
                    url: url,
                    data: d => {
                        d.year = $('#yearSelect').val();
                    }
                },
                columns: columns,
                columnDefs: [
                    {
                        targets: [1,2,3,4,5,6,7,8,9,10,11,12,13],
                        render: moneyRender,
                        className: 'text-end'
                    }
                ],
                drawCallback: function () {
                    const json = this.api().ajax.json() || {};
                    if (badgeSelector && json.sum_total != null) {
                        $(badgeSelector).text(badgeLabel + fmtSAR(json.sum_total));
                    }
                }
            });
        }

        const dtInq = initTable('#tblProdInquiries', DT_URL_INQ, '#badgeInq', 'Inquiries: ');
        const dtPO  = initTable('#tblProdPOs',       DT_URL_PO,  '#badgePO', 'POs: ');

        $('#yearSelect').on('change', function () {
            dtInq.ajax.reload(null, false);
            dtPO.ajax.reload(null, false);
            loadChart();
        });

        async function loadChart() {
            const year = $('#yearSelect').val();
            const res  = await fetch(`${KPI_URL}?year=${year}`, { credentials: 'same-origin' });
            const data = await res.json();

            Highcharts.chart('chartProducts', {
                chart: {
                    type: 'column',
                    backgroundColor: 'transparent',
                    style: {
                        fontFamily: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif'
                    }
                },
                title: {
                    text: `Products comparison — ${year}`,
                    style: {
                        color: '#f8f9fa',
                        fontWeight: '600',
                        fontSize: '14px'
                    }
                },
                xAxis: {
                    categories: data.categories,
                    crosshair: true,
                    labels: {
                        style: { color: '#ced4da', fontSize: '11px' }
                    },
                    lineColor: '#495057',
                    tickColor: '#495057'
                },
                yAxis: {
                    min: 0,
                    title: {
                        text: 'SAR',
                        style: { color: '#ced4da' }
                    },
                    labels: {
                        style: { color: '#adb5bd' }
                    },
                    gridLineColor: '#343a40'
                },
                legend: {
                    itemStyle: { color: '#f8f9fa' },
                    itemHoverStyle: { color: '#ffffff' }
                },
                tooltip: {
                    shared: true,
                    formatter() {
                        const pts = this.points.map(p =>
                            `<span style="color:${p.color}">\u25CF</span> ${p.series.name}: <b>${fmtSAR(p.y)}</b>`
                        ).join('<br/>');
                        return `<b>${this.x}</b><br/>${pts}`;
                    }
                },
                plotOptions: {
                    column: {
                        pointPadding: 0.1,
                        borderWidth: 0,
                        dataLabels: {
                            enabled: true,
                            formatter: function () {
                                if (!this.y) return '';
                                return fmtSAR(this.y);
                            },
                            style: {
                                color: '#f8f9fa',
                                textOutline: 'none',
                                fontSize: '10px',
                                fontWeight: '400'
                            }
                        }
                    }
                },
                series: [
                    { name: 'Inquiries', data: data.inquiries },
                    { name: 'POs',       data: data.pos }
                ],
                credits: { enabled: false }
            });

            $('#badgeInq').text('Inquiries: ' + fmtSAR(data.sum_inquiries));
            $('#badgePO').text('POs: ' + fmtSAR(data.sum_pos));
        }

        $(function () {
            $('#yearSelect').val(YEAR_INIT);
            loadChart();
        });
    </script>
@endpush


