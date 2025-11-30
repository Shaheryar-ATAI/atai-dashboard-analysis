<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ForecastPdfController extends Controller
{

    public function pdf(Request $request)
    {
        $data = $request->all();

        $pdf = Pdf::loadView('forecast.pdf', ['data' => $data])
            ->setPaper('a4', 'landscape'); // <- makes columns roomy

        return $pdf->stream('Forecast-' . now()->format('Y-m-d') . '.pdf');
    }
}
