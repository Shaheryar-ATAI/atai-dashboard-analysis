<?php

//use App\Http\Controllers\AiInsightController;
use App\Http\Controllers\BncProjectController;
use App\Http\Controllers\EstimatorReportController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\ForecastPdfController;
use App\Http\Controllers\PowerBiApiController;
use App\Http\Controllers\ProjectCoordinatorController;
use App\Http\Controllers\ProjectExportController;
use App\Http\Controllers\ProjectSubmittalController;
use App\Http\Controllers\WeeklyReportController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

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

/* =======================================================================
 | Root / Auth (public + guest)
 * ======================================================================= */

// Root: signed-in → /projects, guests → /login
Route::get('/', function () {
    if (!auth()->check()) {
        return redirect()->route('login');
    }

    $u = auth()->user();

    $isCoordinator = method_exists($u, 'hasAnyRole')
        && $u->hasAnyRole(['project_coordinator_eastern', 'project_coordinator_western']);

    if ($isCoordinator) {
        return redirect()->route('coordinator.index');
    }

    return redirect()->route('projects.index');
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
    Route::post('/logout', [PageController::class, 'logout'])
        ->name('logout')
        ->middleware('auth');


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


    Route::post('/projects/{project}/checklist/bidding', [ProjectController::class, 'saveBiddingChecklist']);
    Route::post('/projects/{project}/checklist/inhand', [ProjectController::class, 'saveInhandChecklist']);

    Route::post('/projects/{project}/submittal', [ProjectSubmittalController::class, 'store'])
        ->name('projects.submittal.store');               // upload
    Route::get('/projects/{project}/submittal/{phase?}', [ProjectSubmittalController::class, 'showLatest'])
        ->where(['phase' => '.*'])->name('projects.submittal.show'); // fetch latest (json)
    Route::get('/submittals/stream/{id}', [ProjectSubmittalController::class, 'stream'])
        ->name('submittals.stream')
        ->middleware('signed');;

    Route::get('/projects/export-monthly', [ProjectExportController::class, 'monthly'])
        ->name('projects.export.monthly');

    Route::get('/projects/export-weekly', [ProjectExportController::class, 'weekly'])
        ->name('projects.export.weekly');


    Route::get('/projects/export/yearly', [ProjectExportController::class, 'yearly'])
        ->name('projects.export.yearly');


    Route::get('/projects', [\App\Http\Controllers\PageController::class, 'projects'])
        ->name('projects.index');


    Route::get('/projectslog', [PageController::class, 'inquiriesLog'])
        ->name('inquiries.index');

    // Create new inquiry
//        Route::post('/projects', [\App\Http\Controllers\EstimatorReportController::class, 'store'])
//            ->name('projects.store');
//
//// Type-ahead options for project / client
//        Route::get('/projects/options', [EstimatorReportController::class, 'options'])
//            ->name('projects.options');


    /* ===================================================================
     | FORECAST (APIs consumed by projects page)
     * =================================================================== */

    Route::get('/forecast/kpis', [ForecastApiController::class, 'kpis'])->name('forecast.kpis');
    Route::get('/forecast/totals', [ForecastApiController::class, 'totals'])->name('forecast.totals');
    Route::get('/forecast/by-salesman', [ForecastApiController::class, 'bySalesman'])->name('forecast.bySalesman');


// web.php


    Route::prefix('forecast')->group(function () {
        Route::get('/new', [ForecastController::class, 'create'])->name('forecast.create'); // show form
        Route::post('/', [ForecastController::class, 'save'])->name('forecast.save');       // save rows
        Route::match(['GET', 'POST'], '/pdf', [ForecastController::class, 'pdf'])->name('forecast.pdf'); // generate PDF
        Route::get('/suggest/clients', [ForecastController::class, 'suggestClients'])->name('forecast.suggest.clients');
        Route::get('/suggest/projects', [ForecastController::class, 'suggestProjects'])->name('forecast.suggest.projects');
        Route::post('/validate-row', [ForecastController::class, 'validateRow'])->name('forecast.validate.row');




// Page where Sales Manager fills the form
        Route::get('/forecast/targets-2026', [ForecastController::class, 'createTargets2026'])
            ->name('forecast.targets2026.page');

// Auto-filled download (keep ONLY if needed)
        Route::get('/forecast/targets-2026/download', [ForecastController::class, 'downloadTargets2026'])
            ->name('forecast.targets2026.download');

// Form-based download (NEW – required for your idea)
        Route::post('/forecast/targets-2026/download', [ForecastController::class, 'downloadTargets2026FromForm'])
            ->name('forecast.targets2026.downloadFromForm');
    });


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


    /* ===================================================================
        | ESTIMATors
        * =================================================================== */


    Route::middleware(['auth'])->group(function () {

        // ===================== ESTIMATION REPORTS (Excel) =====================
        Route::prefix('estimation')->name('estimation.')->group(function () {
            Route::get('/reports', [EstimatorReportController::class, 'index'])
                ->name('reports.index');

            Route::get('/reports/export-monthly', [EstimatorReportController::class, 'exportMonthly'])
                ->name('reports.export.monthly');

            Route::get('/reports/export-weekly', [EstimatorReportController::class, 'exportWeekly'])
                ->name('reports.export.weekly');
        });

        // ===================== ESTIMATOR MINI LOG =====================
        // URL to open this page:  /estimator/projects
        // Name: estimator.projects.index
        Route::prefix('estimator')->name('estimator.')->group(function () {

            // page with tabs + Add Inquiry modal
//                Route::get('/estimatorLog', [EstimatorReportController::class, 'index'])
//                    ->name('estimatorLog.index');

            // create inquiry (AJAX)
            Route::post('/inquiries', [EstimatorReportController::class, 'store'])
                ->name('inquiries.store');

            // Select2 typeahead for project/client
            Route::get('/inquiries/options', [EstimatorReportController::class, 'options'])
                ->name('inquiries.options');

            // DataTables JSON for All / Bidding / In-Hand
            Route::get('/inquiries/datatable', [EstimatorReportController::class, 'data'])
                ->name('inquiries.datatable');
            Route::get('{inquiry}', [EstimatorReportController::class, 'show'])->name('inquiries.show');     // NEW
            Route::put('{inquiry}', [EstimatorReportController::class, 'update'])->name('inquiries.update'); // NEW
            Route::delete('{inquiry}', [EstimatorReportController::class, 'destroy'])->name('inquiries.destroy'); // NEW
        });


    });

    /* ===================================================================
     | Project Coordinator
     * =================================================================== */

    Route::get('/project-coordinator', [ProjectCoordinatorController::class, 'index'])
        ->name('coordinator.index');

    Route::post('/project-coordinator/store-po', [ProjectCoordinatorController::class, 'storePo'])
        ->name('coordinator.storePo');

    Route::get('/coordinator/salesorders/{salesorder}', [ProjectCoordinatorController::class, 'showSalesOrder'])
        ->name('coordinator.salesorders.show');

    Route::get('/coordinator/salesorders-export', [ProjectCoordinatorController::class, 'exportSalesOrders'])
        ->name('coordinator.salesorders.export');

    Route::get('/coordinator/salesorders-export-year', [ProjectCoordinatorController::class, 'exportSalesOrdersYear'])
        ->name('coordinator.salesorders.exportYear');

    Route::get(
        '/coordinator/salesorders/{salesorder}/attachments',
        [ProjectCoordinatorController::class, 'salesOrderAttachments']
    )->name('coordinator.salesorders.attachments');

    /* NEW: soft delete endpoints */
    Route::delete(
        '/coordinator/projects/{project}',
        [ProjectCoordinatorController::class, 'destroyProject']
    )->name('coordinator.projects.destroy');

//    Route::delete(
//        '/coordinator/salesorders/{salesorder}',
//        [ProjectCoordinatorController::class, 'destroySalesOrder']
//    )->name('coordinator.salesorders.destroy');

    Route::delete(
        '/coordinator/salesorders/{salesorder}',
        [ProjectCoordinatorController::class, 'destroySalesOrder']
    )->name('coordinator.salesorders.destroy');



    Route::get(
        '/coordinator/related-quotations',
        [ProjectCoordinatorController::class, 'relatedQuotations']
    )->name('coordinator.relatedQuotations');
    Route::get(
        '/coordinator/search-quotations',
        [ProjectCoordinatorController::class, 'searchQuotations']
    )->name('coordinator.searchQuotations');
    /* ===================================================================
     | ESTIMATION (Sales + GM/Admin)
     * =================================================================== */


    Route::prefix('weekly')->name('weekly.')
        ->controller(WeeklyReportController::class)
        ->group(function () {
            Route::get('/', 'create')->name('create');              // open form (/weekly)
            Route::get('/new', 'create');                           // optional alias (/weekly/new)
            Route::post('/', 'save')->name('save');                 // save form
            Route::get('/{report}/pdf', 'pdf')->name('pdf');        // generate PDF
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







    Route::middleware(['auth'])->group(function () {

        // Main page
        Route::get('/bnc', [BncProjectController::class, 'index'])
            ->name('bnc.index');

        // DataTable AJAX
        Route::get('/bnc/datatable', [BncProjectController::class, 'datatable'])
            ->name('bnc.datatable');

        // Upload BNC CSV
        Route::post('/bnc/upload', [BncProjectController::class, 'upload'])
            ->name('bnc.upload');

        // Show single project (for modal)
        Route::get('/bnc/{bncProject}', [BncProjectController::class, 'show'])
            ->name('bnc.show');

        // Update checkpoints from modal
        Route::post('/bnc/{bncProject}', [BncProjectController::class, 'update'])
            ->name('bnc.update');
    });




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
        Route::get('/area-summary/pdf', [PerformanceController::class, 'pdf'])
            ->name('area-summary.pdf')
            ->middleware(['auth']);

        Route::post(
            '/performance/area-chart/save',
            [PerformanceController::class, 'saveAreaChart']
        )->name('performance.area-chart.save');

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






        // ---------- BNC Project Manager Routes ----------
        // (admins upload files, gm/admin can view all regions)
        Route::post('/bnc/upload', [BncProjectController::class, 'upload'])
            ->name('bnc.upload');

        Route::get('/bnc/manage', [BncProjectController::class, 'manage'])
            ->name('bnc.manage'); // optional manager page
    });


    // ================= END GM / ADMIN ONLY =================
});

Route::middleware('powerbi') ->get('/powerbi/projects', [PowerBiApiController::class, 'projects']);
Route::middleware('powerbi')->get('/powerbi/salesorders', [PowerBiApiController::class, 'salesOrders']);
Route::middleware('powerbi')->get('/powerbi/forecast', [PowerBiApiController::class, 'forecast']);
