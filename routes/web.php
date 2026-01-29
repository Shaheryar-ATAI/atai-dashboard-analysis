<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// Controllers
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectsDatatableController;
use App\Http\Controllers\Api\ProjectApiController;
use App\Http\Controllers\Api\ForecastApiController;

use App\Http\Controllers\ProjectCoordinatorController;
use App\Http\Controllers\ProjectExportController;
use App\Http\Controllers\ProjectSubmittalController;

use App\Http\Controllers\ForecastController;

use App\Http\Controllers\EstimationController;
use App\Http\Controllers\EstimatorReportController;

use App\Http\Controllers\WeeklyReportController;

use App\Http\Controllers\SalesOrderManagerController;
use App\Http\Controllers\SalesOrderController;

use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\SalesmanPerformanceController;

use App\Http\Controllers\BncProjectController;

use App\Http\Controllers\PowerBiApiController;

/* ========================================================================
 | ROOT + AUTH (Public / Guest)
 | ======================================================================== */

// Root: signed-in → /projects, coordinators → /project-coordinator, guests → /login
Route::get('/', function () {
    if (!auth()->check()) {
        return redirect()->route('login');
    }

    $u = auth()->user();

    $isCoordinator = method_exists($u, 'hasAnyRole')
        && $u->hasAnyRole(['project_coordinator_eastern', 'project_coordinator_western']);

    return $isCoordinator
        ? redirect()->route('coordinator.index')
        : redirect()->route('projects.index');
})->name('root');

// Guest-only (Login)
Route::middleware('guest')->group(function () {
    Route::get('/login', [PageController::class, 'login'])->name('login');
    Route::post('/login', [PageController::class, 'loginPost'])->name('login.post');
});


/* ========================================================================
 | AUTHENTICATED AREA (All signed-in users)
 | ======================================================================== */
Route::middleware('auth')->group(function () {

    /* --------------------------------------------------------------------
     | Session / User helper
     | -------------------------------------------------------------------- */

    Route::post('/logout', [PageController::class, 'logout'])->name('logout');

    // Small helper for navbar/user chip
    Route::get('/me', function () {
        $u = auth()->user();
        abort_unless($u, 401);

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


    /* ====================================================================
     | PROJECTS (Pages + APIs)
     | ==================================================================== */

    // Projects page
    Route::get('/projects', [PageController::class, 'projects'])->name('projects.index');

    // Inquiry log page
    Route::get('/projectslog', [PageController::class, 'inquiriesLog'])->name('projects.inquiries_log');

    // DataTables JSON endpoint
    Route::get('/projects/datatable', [ProjectsDatatableController::class, 'data'])
        ->name('projects.datatable');

    // Project detail (modal)
    Route::get('/projects/{project}', [ProjectController::class, 'detail'])
        ->whereNumber('project')
        ->name('projects.detail');

    // Project update/save
    Route::post('/projects/{project}', [ProjectController::class, 'update'])
        ->name('projects.update');

    // Checklist save endpoints
    Route::post('/projects/{project}/checklist/bidding', [ProjectController::class, 'saveBiddingChecklist']);
    Route::post('/projects/{project}/checklist/inhand', [ProjectController::class, 'saveInhandChecklist']);

    // KPIs (used by projects view)
    Route::get('/kpis', [ProjectApiController::class, 'kpis'])->name('projects.kpis');

    // Submittals
    Route::post('/projects/{project}/submittal', [ProjectSubmittalController::class, 'store'])
        ->name('projects.submittal.store');

    Route::get('/projects/{project}/submittal/{phase?}', [ProjectSubmittalController::class, 'showLatest'])
        ->where(['phase' => '.*'])
        ->name('projects.submittal.show');

    Route::get('/submittals/stream/{id}', [ProjectSubmittalController::class, 'stream'])
        ->name('submittals.stream')
        ->middleware('signed');

    // Project exports
    Route::get('/projects/export-monthly', [ProjectExportController::class, 'monthly'])
        ->name('projects.export.monthly');

    Route::get('/projects/export-weekly', [ProjectExportController::class, 'weekly'])
        ->name('projects.export.weekly');

    Route::get('/projects/export/yearly', [ProjectExportController::class, 'yearly'])
        ->name('projects.export.yearly');


    /* ====================================================================
     | FORECAST (API + Web)
     | ==================================================================== */

    // APIs consumed by Projects page
    Route::get('/forecast/kpis', [ForecastApiController::class, 'kpis'])->name('forecast.kpis');
    Route::get('/forecast/totals', [ForecastApiController::class, 'totals'])->name('forecast.totals');
    Route::get('/forecast/by-salesman', [ForecastApiController::class, 'bySalesman'])->name('forecast.bySalesman');

    // Forecast module routes
    Route::prefix('forecast')->group(function () {

        // Form + save
        Route::get('/new', [ForecastController::class, 'create'])->name('forecast.create');
        Route::post('/', [ForecastController::class, 'save'])->name('forecast.save');

        // PDF generation
        Route::match(['GET', 'POST'], '/pdf', [ForecastController::class, 'pdf'])->name('forecast.pdf');

        // Suggestions / validation
        Route::get('/suggest/clients', [ForecastController::class, 'suggestClients'])->name('forecast.suggest.clients');
        Route::get('/suggest/projects', [ForecastController::class, 'suggestProjects'])->name('forecast.suggest.projects');
        Route::post('/validate-row', [ForecastController::class, 'validateRow'])->name('forecast.validate.row');

        // Targets 2026
        Route::get('/forecast/targets-2026', [ForecastController::class, 'createTargets2026'])
            ->name('forecast.targets2026.page');

        Route::get('/forecast/targets-2026/download', [ForecastController::class, 'downloadTargets2026'])
            ->name('forecast.targets2026.download');

        Route::post('/forecast/targets-2026/download', [ForecastController::class, 'downloadTargets2026FromForm'])
            ->name('forecast.targets2026.downloadFromForm');
    });


    /* ====================================================================
     | ESTIMATION (GM/Admin + Sales filtering)
     | ==================================================================== */

    Route::prefix('estimation')->name('estimation.')->group(function () {
        Route::get('/', [EstimationController::class, 'index'])->name('index');

        // Pills / KPIs
        Route::get('/estimators', [EstimationController::class, 'estimators'])->name('estimators');
        Route::get('/kpis', [EstimationController::class, 'kpis'])->name('kpis');

        // DataTables endpoints
        Route::get('/datatable/all', [EstimationController::class, 'datatableAll'])->name('datatable.all');
        Route::get('/datatable/region', [EstimationController::class, 'datatableRegion'])->name('datatable.region');
        Route::get('/datatable/product', [EstimationController::class, 'datatableProduct'])->name('datatable.product');

        // Estimator reports (Excel exports)
        Route::get('/reports', [EstimatorReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export-monthly', [EstimatorReportController::class, 'exportMonthly'])->name('reports.export.monthly');
        Route::get('/reports/export-weekly', [EstimatorReportController::class, 'exportWeekly'])->name('reports.export.weekly');
    });


    /* ====================================================================
     | ESTIMATOR MINI LOG (Estimator module)
     | ==================================================================== */

    Route::prefix('estimator')->name('estimator.')->group(function () {

        // create inquiry (AJAX)
        Route::post('/inquiries', [EstimatorReportController::class, 'store'])
            ->name('inquiries.store');

        // Select2 typeahead for project/client
        Route::get('/inquiries/options', [EstimatorReportController::class, 'options'])
            ->name('inquiries.options');

        // DataTables JSON
        Route::get('/inquiries/datatable', [EstimatorReportController::class, 'data'])
            ->name('inquiries.datatable');

        // CRUD (modal)
        Route::get('{inquiry}', [EstimatorReportController::class, 'show'])->name('inquiries.show');
        Route::put('{inquiry}', [EstimatorReportController::class, 'update'])->name('inquiries.update');
        Route::delete('{inquiry}', [EstimatorReportController::class, 'destroy'])->name('inquiries.destroy');
    });


    /* ====================================================================
     | PROJECT COORDINATOR
     | ==================================================================== */

    Route::get('/project-coordinator', [ProjectCoordinatorController::class, 'index'])
        ->name('coordinator.index');

    Route::post('/project-coordinator/store-po', [ProjectCoordinatorController::class, 'storePo'])
        ->name('coordinator.storePo');

    // Sales Orders (Coordinator)
    Route::get('/coordinator/salesorders/{salesorder}', [ProjectCoordinatorController::class, 'showSalesOrder'])
        ->name('coordinator.salesorders.show');

    Route::get('/coordinator/salesorders-export', [ProjectCoordinatorController::class, 'exportSalesOrders'])
        ->name('coordinator.salesorders.export');

    Route::get('/coordinator/salesorders-export-year', [ProjectCoordinatorController::class, 'exportSalesOrdersYear'])
        ->name('coordinator.salesorders.exportYear');

    Route::get('/coordinator/salesorders/{salesorder}/attachments', [ProjectCoordinatorController::class, 'salesOrderAttachments'])
        ->name('coordinator.salesorders.attachments');

    // Attachment secure viewer (fix GoDaddy 403 for /storage direct access)
    Route::get('/coordinator/attachments/{attachment}/view', [ProjectCoordinatorController::class, 'viewAttachment'])
        ->name('coordinator.attachments.view');

    // Soft delete endpoints
    Route::delete('/coordinator/projects/{project}', [ProjectCoordinatorController::class, 'destroyProject'])
        ->name('coordinator.projects.destroy');

    Route::delete('/coordinator/salesorders/{salesorder}', [ProjectCoordinatorController::class, 'destroySalesOrder'])
        ->name('coordinator.salesorders.destroy');

    // Related / Search quotations
    Route::get('/coordinator/related-quotations', [ProjectCoordinatorController::class, 'relatedQuotations'])
        ->name('coordinator.relatedQuotations');

    Route::get('/coordinator/search-quotations', [ProjectCoordinatorController::class, 'searchQuotations'])
        ->name('coordinator.searchQuotations');


    /* ====================================================================
     | WEEKLY REPORT
     | ==================================================================== */

    Route::prefix('weekly')->name('weekly.')
        ->controller(WeeklyReportController::class)
        ->group(function () {
            Route::get('/', 'create')->name('create');
            Route::get('/new', 'create');
            Route::post('/', 'save')->name('save');
            Route::get('/{report}/pdf', 'pdf')->name('pdf');
        });


    /* ====================================================================
     | SALES ORDERS (Manager view)
     | ==================================================================== */

    Route::get('/sales-orders/manager', [SalesOrderManagerController::class, 'index'])
        ->name('salesorders.manager.index');

    Route::get('/sales-orders/manager/datatable', [SalesOrderManagerController::class, 'datatable'])
        ->name('salesorders.manager.datatable');

    Route::get('/sales-orders/manager/kpis', [SalesOrderManagerController::class, 'kpis'])
        ->name('salesorders.manager.kpis');

    Route::view('/sales-orders/manager/kpi', 'sales_orders.manager.manager_kpi')
        ->name('salesorders.manager.kpi');

    Route::get('/sales-orders/manager/salesmen', [SalesOrderManagerController::class, 'salesmen'])
        ->name('salesorders.manager.salesmen');


    /* ====================================================================
     | BNC (All authenticated; upload visibility is handled in controller/ui)
     | ==================================================================== */

    Route::get('/bnc', [BncProjectController::class, 'index'])->name('bnc.index');
    Route::get('/bnc/datatable', [BncProjectController::class, 'datatable'])->name('bnc.datatable');
    Route::post('/bnc/upload', [BncProjectController::class, 'upload'])->name('bnc.upload');
    Route::get('/bnc/{bncProject}', [BncProjectController::class, 'show'])->name('bnc.show');
    Route::post('/bnc/{bncProject}', [BncProjectController::class, 'update'])->name('bnc.update');
    Route::get('/bnc/export/pdf', [BncProjectController::class, 'exportPdf'])->name('bnc.export.pdf');
    Route::get('/bnc-projects/{id}/quotes', [BncProjectController::class, 'quotesForBnc'])->name('bnc.quotes');


    /* ====================================================================
     | GM / ADMIN ONLY
     | ==================================================================== */

    Route::middleware('role:gm|admin')->group(function () {

        // Performance pages
        Route::get('/performance', [PerformanceController::class, 'index'])->name('performance.index');

        // Area performance
        Route::get('/performance/area', [PerformanceController::class, 'area'])->name('performance.area');
        Route::get('/performance/area/data', [PerformanceController::class, 'areaData'])->name('performance.area.data');
        Route::get('/performance/area/kpis', [PerformanceController::class, 'areaKpis'])->name('performance.area.kpis');

        Route::get('/area-summary/pdf', [PerformanceController::class, 'pdf'])
            ->name('area-summary.pdf');

        Route::post('/performance/area-chart/save', [PerformanceController::class, 'saveAreaChart'])
            ->name('performance.area-chart.save');

        // Salesman performance
        Route::prefix('performance')->group(function () {
            Route::get('/salesman', [SalesmanPerformanceController::class, 'index'])->name('performance.salesman');
            Route::get('/salesman/data', [SalesmanPerformanceController::class, 'data'])->name('performance.salesman.data');
            Route::get('/salesman/kpis', [SalesmanPerformanceController::class, 'kpis'])->name('performance.salesman.kpis');
            Route::get('/salesman/pdf', [SalesmanPerformanceController::class, 'pdf'])->name('performance.salesman.pdf');
            Route::post('/salesman/insights', [SalesmanPerformanceController::class, 'insights'])
                ->name('performance.salesman.insights');

            Route::get('/performance/salesman/matrix', [SalesmanPerformanceController::class, 'matrix'])
                ->name('performance.salesman.matrix');
        });

        // Product performance
        Route::get('/performance/product', [PerformanceController::class, 'products'])->name('performance.product');
        Route::get('/performance/products/data', [PerformanceController::class, 'productsData'])->name('performance.products.data');
        Route::get('/performance/products/kpis', [PerformanceController::class, 'productsKpis'])->name('performance.products.kpis');

        // Raw performance APIs
        Route::prefix('api/performance')->group(function () {
            Route::get('/orders/factory', [PerformanceController::class, 'ordersFactory']);
            Route::get('/orders/by-area', [PerformanceController::class, 'ordersByArea']);
            Route::get('/orders/by-sales', [PerformanceController::class, 'ordersBySales']);
            Route::get('/quotes/by-area', [PerformanceController::class, 'quotesByArea']);
            Route::get('/quotes/by-sales', [PerformanceController::class, 'quotesBySales']);
            Route::get('/quotes/by-product', [PerformanceController::class, 'quotesByProduct']);
            Route::get('/kpis', [PerformanceController::class, 'kpis'])->name('performance.kpis');
        });

        // Sales Orders (GM/Admin log)
        Route::get('/sales-orders', [SalesOrderController::class, 'index'])->name('salesorders.index');
        Route::get('/sales-orders/datatable', [SalesOrderController::class, 'datatableLog'])->name('salesorders.datatable');
        Route::get('/sales-orders/kpis', [SalesOrderController::class, 'kpis'])->name('salesorders.kpis');

        // Territory mix pages
        Route::get('/sales-orders/territory-mix', [SalesOrderController::class, 'territorySales'])
            ->name('sales-orders.territory-sales');

        Route::get('/projects/territory-mix', [SalesOrderController::class, 'territoryInquiries'])
            ->name('projects.territory-inquiries');

        // Power BI redirect
        Route::get('/powerbi', function () {
            $url = config('services.powerbi_url');
            abort_if(empty($url), 404);
            return redirect()->away($url);
        })->name('powerbi.jump');

        // Optional manager page (if used)
        Route::get('/bnc/manage', [BncProjectController::class, 'manage'])
            ->name('bnc.manage');
    });

});


/* ========================================================================
 | POWER BI API (Middleware-protected)
 | ======================================================================== */
Route::middleware('powerbi')->get('/powerbi/projects', [PowerBiApiController::class, 'projects']);
Route::middleware('powerbi')->get('/powerbi/salesorders', [PowerBiApiController::class, 'salesOrders']);
Route::middleware('powerbi')->get('/powerbi/forecast', [PowerBiApiController::class, 'forecast']);


/* ========================================================================
 | DEBUG (AI ping)
 | ======================================================================== */
Route::get('/debug/ai-ping', function () {
    $apiKey = config('services.openai.key');
    if (!$apiKey) {
        return response()->json(['ok' => false, 'reason' => 'missing services.openai.key'], 500);
    }

    $resp = \Illuminate\Support\Facades\Http::timeout(20)
        ->withToken($apiKey)
        ->post('https://api.openai.com/v1/chat/completions', [
            'model' => config('services.openai.model', 'gpt-4.1-mini'),
            'messages' => [
                ['role' => 'system', 'content' => 'Return JSON only.'],
                ['role' => 'user', 'content' => json_encode(['ping' => true])]
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.2,
        ]);

    return response()->json([
        'ok' => $resp->ok(),
        'status' => $resp->status(),
        'body' => $resp->json(),
    ], $resp->ok() ? 200 : 500);
});
