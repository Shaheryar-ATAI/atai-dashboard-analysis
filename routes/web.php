<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\ProjectsDatatableController;
use App\Http\Controllers\Api\ProjectApiController;
use App\Http\Controllers\SalesmanPerformanceController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\EstimationController;
use App\Http\Controllers\Api\ForecastApiController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| NOTE:
| - All public pages are behind 'web'.
| - Guests: /login (GET/POST)
| - Authenticated users:
|     - Everyone: /projects (+ its APIs)
|     - GM/Admin only (server-side): /performance/*, /sales-orders/*, /powerbi
|
| Ensure these aliases exist in app/Http/Kernel.php:
|   'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
|   'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
|   'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
*/

Route::middleware('web')->group(function () {
    /**
     * Root: signed-in users -> projects, guests -> login
     */
    Route::get('/', function () {
        return auth()->check()
            ? redirect()->route('projects.index')
            : redirect()->route('login');
    })->name('root');

    /**
     * Authentication (guest-only)
     */
    Route::middleware('guest')->group(function () {
        Route::get('/login',  [PageController::class, 'login'])->name('login');
        Route::post('/login', [PageController::class, 'loginPost'])->name('login.post');
    });

    /**
     * Authenticated area
     */
    Route::middleware('auth')->group(function () {
        // Logout
        Route::post('/logout', [PageController::class, 'logout'])->name('logout');

        // Small helper for the frontend (navbar/user chip)
        Route::get('/me', function () {
            $u = auth()->user();
            abort_unless($u, 401);

            // Spatie roles (fallback-safe)
            $roles = method_exists($u, 'getRoleNames')
                ? $u->getRoleNames()
                : collect(method_exists($u, 'roleNames') ? $u->roleNames() : []);

            $canViewAll = (method_exists($u, 'hasAnyRole') && $u->hasAnyRole(['admin', 'gm']))
                || (method_exists($u, 'can') && $u->can('projects.view-all'));

            return response()->json([
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'region'     => $u->region,       // Eastern | Central | Western | null
                'roles'      => $roles,
                'canViewAll' => (bool) $canViewAll,
            ]);
        })->name('me');

        /**
         * PAGES available to all authenticated users (sales + GM/Admin)
         */
        Route::get('/projects', [PageController::class, 'projects'])->name('projects.index');

        /**
         * Projects data (DataTables / APIs) – controllers must enforce region
         */
        Route::get('/projects/datatable', [ProjectsDatatableController::class, 'data'])
            ->name('projects.datatable');

        // KPIs consumed by Projects page
        Route::get('/kpis', [ProjectApiController::class, 'kpis'])->name('projects.kpis');

        // Forecast
        Route::get('/forecast/kpis', [ForecastApiController::class, 'kpis'])->name('forecast.kpis');
        Route::get('/forecast/totals', [ForecastApiController::class, 'totals'])->name('forecast.totals');
        // Estimation (Sales + GM/Admin)
          // Estimation
                Route::prefix('estimation')->name('estimation.')->group(function () {
                    Route::get('/', [\App\Http\Controllers\EstimationController::class, 'index'])->name('index');

                    // dynamic estimator pills
                    Route::get('/estimators', [\App\Http\Controllers\EstimationController::class, 'estimators'])
                        ->name('estimators');

                    // KPIs (filtered)
                    Route::get('/kpis', [\App\Http\Controllers\EstimationController::class, 'kpis'])
                        ->name('kpis');

                    // DataTables (filtered)
                    Route::get('/datatable/all', [\App\Http\Controllers\EstimationController::class, 'datatableAll'])
                        ->name('datatable.all');
                    Route::get('/datatable/region', [\App\Http\Controllers\EstimationController::class, 'datatableRegion'])
                        ->name('datatable.region');
                    Route::get('/datatable/product', [\App\Http\Controllers\EstimationController::class, 'datatableProduct'])
                        ->name('datatable.product');
                });

        /**
         * ================= GM / ADMIN ONLY =================
         * Everything below is blocked for sales users (server side).
         */
        Route::middleware('role:gm|admin')->group(function () {
            // Performance dashboard (landing)
            Route::get('/performance', [PerformanceController::class, 'index'])
                ->name('performance.index');

            // Area pages + data
            Route::get('/performance/area',        [PerformanceController::class, 'area'])
                ->name('performance.area');
            Route::get('/performance/area/data',   [PerformanceController::class, 'areaData'])
                ->name('performance.area.data');
            Route::get('/performance/area/kpis',   [PerformanceController::class, 'areaKpis'])
                ->name('performance.area.kpis');

            // Salesman pages + data
            Route::prefix('performance')->group(function () {
                Route::get('/salesman',      [SalesmanPerformanceController::class, 'index'])
                    ->name('performance.salesman');
                Route::get('/salesman/data', [SalesmanPerformanceController::class, 'data'])
                    ->name('performance.salesman.data');
                Route::get('/salesman/kpis', [SalesmanPerformanceController::class, 'kpis'])
                    ->name('performance.salesman.kpis');
            });

            // Product summary + data (singular)
            Route::get('/performance/product',      [PerformanceController::class, 'product'])
                ->name('performance.product');
            Route::get('/performance/product/data', [PerformanceController::class, 'productData'])
                ->name('performance.product.data');

            // Product summary (plural aliases for older links)
            Route::get('/performance/products',        [PerformanceController::class, 'products'])
                ->name('performance.products');
            Route::get('/performance/products/data',   [PerformanceController::class, 'productsData'])
                ->name('performance.products.data');
            Route::get('/performance/products/kpis',   [PerformanceController::class, 'productsKpis'])
                ->name('performance.products.kpis');

            // Raw performance APIs (if still needed by charts)
            Route::prefix('api/performance')->group(function () {
                Route::get('/orders/factory',    [PerformanceController::class, 'ordersFactory']);
                Route::get('/orders/by-area',    [PerformanceController::class, 'ordersByArea']);
                Route::get('/orders/by-sales',   [PerformanceController::class, 'ordersBySales']);
                Route::get('/quotes/by-area',    [PerformanceController::class, 'quotesByArea']);
                Route::get('/quotes/by-sales',   [PerformanceController::class, 'quotesBySales']);
                Route::get('/quotes/by-product', [PerformanceController::class, 'quotesByProduct']);
                Route::get('/kpis',              [PerformanceController::class, 'kpis'])
                    ->name('performance.kpis');
            });

            // Sales Orders (logs) + KPIs
            Route::get('/sales-orders',              [SalesOrderController::class, 'index'])
                ->name('salesorders.index');
            Route::get('/sales-orders/datatable',    [SalesOrderController::class, 'datatableLog'])
                ->name('salesorders.datatable');
            Route::get('/sales-orders/kpis',         [SalesOrderController::class, 'kpis'])
                ->name('salesorders.kpis');

            // Power BI (only for signed-in GM/Admin)
            Route::get('/powerbi', function () {
                $url = config('services.powerbi_url');
                abort_if(empty($url), 404);
                return redirect()->away($url);
            })->name('powerbi.jump');
        });
        // ================= END GM / ADMIN ONLY =================
    });
});
