<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title','ATAI')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@600;700&display=swap" rel="stylesheet">

    {{-- Bootstrap CSS (CDN + local fallback) --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet"
          onerror="this.onerror=null;this.href='{{ asset('vendor/bootstrap/5.3.3/bootstrap.min.css') }}';">

    {{-- Bootstrap Icons (because your navbar uses bi icons) --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    {{-- DataTables CSS (CDN + local fallback) --}}
    <link rel="stylesheet"
          href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css"
          onerror="this.onerror=null;this.href='{{ asset('vendor/datatables/1.13.8/css/dataTables.bootstrap5.min.css') }}';">

    {{-- Select2 CSS (CDN + local fallback) --}}
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css"
          onerror="this.onerror=null;this.href='{{ asset('vendor/select2/4.1.0-rc.0/css/select2.min.css') }}';">

    {{-- YOUR THEME (ONLY ONCE) --}}
    @php
        $themeVersion = @filemtime(public_path('css/atai-theme-20260210.css')) ?: time();
        $isProd = app()->environment('production');
        $isHttps = request()->isSecure() || request()->header('X-Forwarded-Proto') === 'https';
        $assetBase = config('app.asset_url');
        if (!empty($assetBase)) {
            $themeHref = rtrim($assetBase, '/') . '/css/atai-theme-20260210.css';
        } else {
            $themeHref = $isProd ? secure_asset('css/atai-theme-20260210.css') : ($isHttps ? secure_asset('css/atai-theme-20260210.css') : asset('css/atai-theme-20260210.css'));
        }
    @endphp
    <link id="atai-theme-css"
          rel="stylesheet"
          href="{{ $themeHref }}?v={{ $themeVersion }}"
          onerror="this.onerror=null;this.href='{{ asset('css/atai-theme-20260210.css') }}?v={{ $themeVersion }}';">

    <script>
        (function () {
            const link = document.getElementById('atai-theme-css');
            if (!link) return;

            function hasThemeVars() {
                const v = getComputedStyle(document.documentElement).getPropertyValue('--atai-green-50');
                return v && v.trim().length > 0;
            }

            window.addEventListener('load', function () {
                if (!hasThemeVars()) {
                    link.href = '/css/atai-theme-20260210.css?v={{ $themeVersion }}';
                }
            });
        })();
    </script>

    @stack('head')
</head>
<body class="atai-app atai-future @yield('body-class')">

{{-- NAVBAR --}}
@include('partials.navbar')

{{-- PAGE CONTENT --}}
<main class="container-fluid py-4 atai-page-shell">
    @yield('content')
</main>

{{-- ============================================================
   GLOBAL JS (ORDER MATTERS)
   ============================================================ --}}

{{-- jQuery (CDN + local fallback) --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"
        onerror="this.onerror=null;this.src='{{ asset('vendor/jquery/3.7.1/jquery.min.js') }}';"></script>
<script>
    // Hard fallback: some network errors do not reliably trigger onerror.
    if (!window.jQuery) {
        document.write('<script src="{{ asset('vendor/jquery/3.7.1/jquery.min.js') }}"><\/script>');
    }
</script>

{{-- Bootstrap Bundle (CDN + local fallback) --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        onerror="this.onerror=null;this.src='{{ asset('vendor/bootstrap/5.3.3/bootstrap.bundle.min.js') }}';"></script>
<script>
    if (typeof window.bootstrap === 'undefined') {
        document.write('<script src="{{ asset('vendor/bootstrap/5.3.3/bootstrap.bundle.min.js') }}"><\/script>');
    }
</script>

{{-- DataTables (CDN + local fallback) --}}
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"
        onerror="this.onerror=null;this.src='{{ asset('vendor/datatables/1.13.8/js/jquery.dataTables.min.js') }}';"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"
        onerror="this.onerror=null;this.src='{{ asset('vendor/datatables/1.13.8/js/dataTables.bootstrap5.min.js') }}';"></script>
<script>
    if (!(window.jQuery && jQuery.fn && jQuery.fn.dataTable)) {
        document.write('<script src="{{ asset('vendor/datatables/1.13.8/js/jquery.dataTables.min.js') }}"><\/script>');
        document.write('<script src="{{ asset('vendor/datatables/1.13.8/js/dataTables.bootstrap5.min.js') }}"><\/script>');
    }
</script>

{{-- Highcharts (multi-CDN + local fallback) --}}
<script src="https://code.highcharts.com/highcharts.js"
        onerror="this.onerror=function(){this.onerror=function(){this.onerror=null;this.src='{{ asset('vendor/highcharts/11.4.6/highcharts.js') }}';};this.src='https://cdnjs.cloudflare.com/ajax/libs/highcharts/11.4.6/highcharts.js';};this.src='https://cdn.jsdelivr.net/npm/highcharts@11.4.6/highcharts.js';"></script>
<script src="https://code.highcharts.com/highcharts-more.js"
        onerror="this.onerror=function(){this.onerror=function(){this.onerror=null;this.src='{{ asset('vendor/highcharts/11.4.6/highcharts-more.js') }}';};this.src='https://cdnjs.cloudflare.com/ajax/libs/highcharts/11.4.6/highcharts-more.js';};this.src='https://cdn.jsdelivr.net/npm/highcharts@11.4.6/highcharts-more.js';"></script>
<script src="https://code.highcharts.com/modules/solid-gauge.js"
        onerror="this.onerror=function(){this.onerror=function(){this.onerror=null;this.src='{{ asset('vendor/highcharts/11.4.6/modules/solid-gauge.js') }}';};this.src='https://cdnjs.cloudflare.com/ajax/libs/highcharts/11.4.6/modules/solid-gauge.js';};this.src='https://cdn.jsdelivr.net/npm/highcharts@11.4.6/modules/solid-gauge.js';"></script>
<script src="https://code.highcharts.com/modules/no-data-to-display.js"
        onerror="this.onerror=function(){this.onerror=function(){this.onerror=null;this.src='{{ asset('vendor/highcharts/11.4.6/modules/no-data-to-display.js') }}';};this.src='https://cdnjs.cloudflare.com/ajax/libs/highcharts/11.4.6/modules/no-data-to-display.js';};this.src='https://cdn.jsdelivr.net/npm/highcharts@11.4.6/modules/no-data-to-display.js';"></script>
<script src="https://code.highcharts.com/modules/funnel.js"
        onerror="this.onerror=function(){this.onerror=function(){this.onerror=null;this.src='{{ asset('vendor/highcharts/11.4.6/modules/funnel.js') }}';};this.src='https://cdnjs.cloudflare.com/ajax/libs/highcharts/11.4.6/modules/funnel.js';};this.src='https://cdn.jsdelivr.net/npm/highcharts@11.4.6/modules/funnel.js';"></script>

<script>
    (function () {
        if (window.Highcharts) return;
        const noop = function () {};
        const stubChart = function () {
            return {
                series: [{ setData: noop }],
                update: noop,
                redraw: noop,
                showNoData: noop,
                hideNoData: noop,
                addSeries: noop,
                destroy: noop
            };
        };
        window.Highcharts = {
            chart: stubChart,
            numberFormat: function (n, dec) {
                const d = Number.isFinite(dec) ? dec : 0;
                return Number(n || 0).toLocaleString(undefined, {
                    minimumFractionDigits: d,
                    maximumFractionDigits: d
                });
            },
            Chart: { prototype: { showNoData: noop, hideNoData: noop } }
        };
        console.warn('Highcharts failed to load. Using no-op stub to avoid runtime errors.');
    })();
</script>

{{-- Select2 (CDN + local fallback) --}}
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.full.min.js"
        onerror="this.onerror=null;this.src='{{ asset('vendor/select2/4.1.0-rc.0/js/select2.full.min.js') }}';"></script>
<script>
    if (!(window.jQuery && jQuery.fn && jQuery.fn.select2)) {
        document.write('<script src="{{ asset('vendor/select2/4.1.0-rc.0/js/select2.full.min.js') }}"><\/script>');
    }
</script>

{{-- GLOBAL SCROLL + MODAL BODY FIX --}}
<script>
    (function () {
        const isEditable = (el) => {
            if (!el) return false;
            const tag = (el.tagName || '').toLowerCase();
            return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable;
        };

        const syncNavbarOffset = () => {
            const nav = document.querySelector('.navbar-atai');
            if (!nav) return;
            document.documentElement.style.setProperty('--atai-navbar-offset', `${nav.offsetHeight}px`);
        };

        const syncBodyScroll = () => {
            const hasOpenModal = !!document.querySelector('.modal.show');
            if (hasOpenModal) {
                document.body.classList.add('modal-open');
                return;
            }
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        };

        document.addEventListener('shown.bs.modal', syncBodyScroll);
        document.addEventListener('hidden.bs.modal', syncBodyScroll);
        window.addEventListener('load', syncBodyScroll);
        window.addEventListener('resize', syncBodyScroll);
        window.addEventListener('load', syncNavbarOffset);
        window.addEventListener('resize', syncNavbarOffset);

        document.addEventListener('keydown', function (e) {
            if (isEditable(e.target)) return;
            const step = e.altKey ? 200 : 60;
            switch (e.key) {
                case 'ArrowDown':
                    window.scrollBy({ top: step, left: 0, behavior: 'auto' });
                    e.preventDefault();
                    break;
                case 'ArrowUp':
                    window.scrollBy({ top: -step, left: 0, behavior: 'auto' });
                    e.preventDefault();
                    break;
                case 'ArrowRight':
                    window.scrollBy({ top: 0, left: step, behavior: 'auto' });
                    e.preventDefault();
                    break;
                case 'ArrowLeft':
                    window.scrollBy({ top: 0, left: -step, behavior: 'auto' });
                    e.preventDefault();
                    break;
                case 'PageDown':
                    window.scrollBy({ top: window.innerHeight * 0.9, left: 0, behavior: 'auto' });
                    e.preventDefault();
                    break;
                case 'PageUp':
                    window.scrollBy({ top: -window.innerHeight * 0.9, left: 0, behavior: 'auto' });
                    e.preventDefault();
                    break;
                case 'Home':
                    window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
                    e.preventDefault();
                    break;
                case 'End':
                    window.scrollTo({ top: document.body.scrollHeight, left: 0, behavior: 'auto' });
                    e.preventDefault();
                    break;
                default:
                    break;
            }
        }, { passive: false });
    })();
</script>

{{-- Navbar dropdown hard fallback (works even if Bootstrap JS is present but blocked/broken) --}}
<script>
    (function () {
        const nav = document.querySelector('.navbar-atai');
        if (!nav) return;

        const closeAll = () => {
            nav.querySelectorAll('.dropdown.show').forEach(d => {
                d.classList.remove('show');
                const m = d.querySelector('.dropdown-menu');
                if (m) m.classList.remove('show');
                const t = d.querySelector('[data-atai-dropdown]');
                if (t) t.setAttribute('aria-expanded', 'false');
            });
        };

        document.addEventListener('click', function (e) {
            const toggle = e.target.closest('.navbar-atai [data-atai-dropdown]');
            if (toggle) {
                e.preventDefault();
                e.stopImmediatePropagation();

                const parent = toggle.closest('.dropdown');
                const menu = parent ? parent.querySelector('.dropdown-menu') : null;
                if (!parent || !menu) return;

                const isOpen = parent.classList.contains('show') || menu.classList.contains('show');
                closeAll();
                if (!isOpen) {
                    parent.classList.add('show');
                    menu.classList.add('show');
                    toggle.setAttribute('aria-expanded', 'true');
                }
                return;
            }

            if (!e.target.closest('.navbar-atai .dropdown')) {
                closeAll();
            }
        }, true);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeAll();
        });
    })();
</script>

{{-- PAGE-SPECIFIC JS --}}
@stack('modals')
@stack('scripts')

</body>
</html>
