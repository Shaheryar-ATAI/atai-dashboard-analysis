<?php

use Illuminate\Support\Facades\Route;

// Controllers
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectsDatatableController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\SalesmanPerformanceController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\EstimationController;
use App\Http\Controllers\Api\ProjectApiController;
use App\Http\Controllers\Api\ForecastApiController;
use App\Http\Controllers\SalesOrderManagerController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Notes
| - All routes are kept EXACTLY the same (paths + names).
| - Only structure/organization/comments improved.
| - Order-specific routes BEFORE wildcard/numeric routes to avoid conflicts.
*/

Route::middleware('web')->group(function () {

    /* =======================================================================
     | Root / Auth (public + guest)
     * ======================================================================= */

    // Root: signed-in → /projects, guests → /login
    Route::get('/', function () {
        return auth()->check()
            ? redirect()->route('projects.index')
            : redirect()->route('login');
    })->name('root');

    // ----- Guest-only -----
    Route::middleware('guest')->group(function () {
        Route::get('/login', [PageController::class, 'login'])->name('login');
        Route::post('/login', [PageController::class, 'loginPost'])->name('login.post');
    });

    /* =======================================================================
     | Authenticated area (all signed-in users)
     * ======================================================================= */
    Route::middleware('auth')->group(function () {

        /* ---------- Session helpers ---------- */
        Route::post('/logout', [PageController::class, 'logout'])->name('logout');

        // Small helper for navbar/user chip
        Route::get('/me', function () {
            $u = auth()->user();
            abort_unless($u, 401);

            // Collect roles safely
            $roles = method_exists($u, 'getRoleNames')
                ? $u->getRoleNames()
                : collect(method_exists($u, 'roleNames') ? $u->roleNames() : []);

            $canViewAll = (method_exists($u, 'hasAnyRole') && $u->hasAnyRole(['admin', 'gm']))
                || (method_exists($u, 'can') && $u->can('projects.view-all'));

            return response()->json([
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'region' => $u->region,  // Eastern | Central | Western | null
                'roles' => $roles,
                'canViewAll' => (bool)$canViewAll,
            ]);
        })->name('me');

        /* ===================================================================
         | PROJECTS (pages + APIs)
         | (kept as separate endpoints but grouped visually)
         * =================================================================== */

        // Pages project
        // KPI page (charts)
//        Route::get('/projects', [PageController::class, 'projects'])
//            ->name('projects.index');   // <— new name
//
//        // Inquiries Log page (table only)
//                Route::get('/projectslog', [PageController::class, 'inquiriesLog'])
//                    ->name('inquiries.index'); // <— new page for DataTable
//
//        // DataTables JSON endpoint (unchanged)
//                Route::get('/projects/datatable', [ProjectsDatatableController::class, 'data'])
//            ->name('projects.datatable');
//
//        Route::get('/projects/{project}', [ProjectController::class, 'detail'])
//            ->whereNumber('project') // avoid catching 'datatable', etc.
//            ->name('projects.detail');
//
//        // Global KPIs for projects page (path is /kpis by design; keep it)
//        Route::get('/kpis', [ProjectApiController::class, 'kpis'])->name('projects.kpis');
//
////        Route::get('/projects/{project}', [ProjectController::class,'show'])->name('projects.show');
//        Route::post('/projects/{project}',[ProjectController::class,'update'])->name('projects.update');



// --- Projects (Quotation Log) ---
        Route::get('/projects', [\App\Http\Controllers\PageController::class, 'projects'])
            ->name('projects.index');

        Route::get('/projectslog', [\App\Http\Controllers\PageController::class, 'inquiriesLog'])
            ->name('inquiries.index');

// DataTables JSON endpoint (used by your view JS)
        Route::get(
            '/projects/datatable',
            [\App\Http\Controllers\ProjectsDatatableController::class, 'data']
        )->name('projects.datatable');

// Detail for modal
        Route::get('/projects/{project}', [\App\Http\Controllers\ProjectController::class, 'detail'])
            ->whereNumber('project')
            ->name('projects.detail');

// Totals/KPIs (leave as-is if your page uses it elsewhere)
        Route::get('/kpis', [\App\Http\Controllers\Api\ProjectApiController::class, 'kpis'])
            ->name('projects.kpis');

// Update (save)
        Route::post('/projects/{project}', [\App\Http\Controllers\ProjectController::class, 'update'])
            ->name('projects.update');








        /* ===================================================================
         | FORECAST (APIs consumed by projects page)
         * =================================================================== */

        Route::get('/forecast/kpis', [ForecastApiController::class, 'kpis'])->name('forecast.kpis');
        Route::get('/forecast/totals', [ForecastApiController::class, 'totals'])->name('forecast.totals');
        Route::get('/forecast/by-salesman', [ForecastApiController::class, 'bySalesman'])->name('forecast.bySalesman');

        /* ===================================================================
         | ESTIMATION (Sales + GM/Admin)
         * =================================================================== */
        Route::prefix('estimation')->name('estimation.')->group(function () {
            Route::get('/', [EstimationController::class, 'index'])->name('index');
            Route::get('/estimators', [EstimationController::class, 'estimators'])->name('estimators'); // dynamic pills
            Route::get('/kpis', [EstimationController::class, 'kpis'])->name('kpis');             // filtered KPIs
            // DataTables (filtered)
            Route::get('/datatable/all', [EstimationController::class, 'datatableAll'])->name('datatable.all');
            Route::get('/datatable/region', [EstimationController::class, 'datatableRegion'])->name('datatable.region');
            Route::get('/datatable/product', [EstimationController::class, 'datatableProduct'])->name('datatable.product');
        });





        // -------- Sales Orders (Regional / Sales Manager view) --------
        // LOG (this view)
        Route::get('/sales-orders/manager', [SalesOrderManagerController::class, 'index'])
            ->name('salesorders.manager.index');

// 2️⃣ DataTable AJAX JSON endpoint
        Route::get('/sales-orders/manager/datatable', [SalesOrderManagerController::class, 'datatable'])
            ->name('salesorders.manager.datatable');

// 3️⃣ KPIs JSON endpoint (used for charts)
        Route::get('/sales-orders/manager/kpis', [SalesOrderManagerController::class, 'kpis'])
            ->name('salesorders.manager.kpis');

// 4️⃣ Optional KPI Page (visual dashboard page)
        Route::view('/sales-orders/manager/kpi', 'sales_orders.manager.manager_kpi')
            ->name('salesorders.manager.kpi');
        /* ===================================================================
         | GM / ADMIN ONLY
         * =================================================================== */
        Route::middleware('role:gm|admin')->group(function () {

            // -------- Performance (pages) --------
            Route::get('/performance', [PerformanceController::class, 'index'])->name('performance.index');

            // Area pages + data
            Route::get('/performance/area', [PerformanceController::class, 'area'])->name('performance.area');
            Route::get('/performance/area/data', [PerformanceController::class, 'areaData'])->name('performance.area.data');
            Route::get('/performance/area/kpis', [PerformanceController::class, 'areaKpis'])->name('performance.area.kpis');

            // Salesman pages + data
            Route::prefix('performance')->group(function () {
                Route::get('/salesman', [SalesmanPerformanceController::class, 'index'])->name('performance.salesman');
                Route::get('/salesman/data', [SalesmanPerformanceController::class, 'data'])->name('performance.salesman.data');
                Route::get('/salesman/kpis', [SalesmanPerformanceController::class, 'kpis'])->name('performance.salesman.kpis');
            });

            // Product summary + data (singular)
            Route::get('/performance/product', [PerformanceController::class, 'products'])->name('performance.product');
            Route::get('/performance/products/data', [PerformanceController::class, 'productsData'])->name('performance.products.data');
            Route::get('/performance/products/kpis', [PerformanceController::class, 'productsKpis'])->name('performance.products.kpis');

            // Raw performance APIs (if still used by charts)
            Route::prefix('api/performance')->group(function () {
                Route::get('/orders/factory', [PerformanceController::class, 'ordersFactory']);
                Route::get('/orders/by-area', [PerformanceController::class, 'ordersByArea']);
                Route::get('/orders/by-sales', [PerformanceController::class, 'ordersBySales']);
                Route::get('/quotes/by-area', [PerformanceController::class, 'quotesByArea']);
                Route::get('/quotes/by-sales', [PerformanceController::class, 'quotesBySales']);
                Route::get('/quotes/by-product', [PerformanceController::class, 'quotesByProduct']);
                Route::get('/kpis', [PerformanceController::class, 'kpis'])->name('performance.kpis');
            });

            // -------- Sales Orders --------
            Route::get('/sales-orders', [SalesOrderController::class, 'index'])->name('salesorders.index');
            Route::get('/sales-orders/datatable', [SalesOrderController::class, 'datatableLog'])->name('salesorders.datatable');
            Route::get('/sales-orders/kpis', [SalesOrderController::class, 'kpis'])->name('salesorders.kpis');
            Route::get('/sales-orders/territory-mix', [\App\Http\Controllers\SalesOrderController::class, 'territorySales'])
                ->name('sales-orders.territory-sales');





            Route::get('/projects/territory-mix', [\App\Http\Controllers\SalesOrderController::class, 'territoryInquiries'])
                ->name('projects.territory-inquiries');








            // -------- Power BI (external) --------
            Route::get('/powerbi', function () {
                $url = config('services.powerbi_url');
                abort_if(empty($url), 404);
                return redirect()->away($url);
            })->name('powerbi.jump');
        });
        // ================= END GM / ADMIN ONLY =================
    });
});
