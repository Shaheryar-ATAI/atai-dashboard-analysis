<?php

namespace App\Exports;

use App\Models\Project;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProjectsMonthlyExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithTitle,
    WithEvents,
    WithColumnWidths
{
    /** @var \Illuminate\Support\Collection|Project[] */
    protected Collection $rows;

    // e.g. "July 2025"
    protected string $title;

    // logged-in estimator name to show in header
    protected ?string $estimatorName;

    public function __construct(Collection $rows, string $title, ?string $estimatorName = null)
    {
        $this->rows          = $rows;
        $this->title         = $title;
        $this->estimatorName = $estimatorName;
    }

    /* -------------------------------------------------------------
     |  Data
     * ------------------------------------------------------------- */

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Project Name',             // A
            'Client Name',              // B
            'Area',                     // C
            'Project Location',         // D
            'Salesman',                 // E
            'Quotation No',             // F
            'Quotation Date',           // G
            'Date Received',            // H
            'Product',                  // I (ATAI Products)
            'Quotation Value (SAR)',    // J
            'Project Type',             // K
            'Status',                   // L
        ];
    }

    public function map($p): array
    {
        /** @var Project $p */
        return [
            $p->project_name,
            $p->client_name,
            $p->area,
            $p->project_location,
            $p->salesman ?? $p->salesperson,
            $p->quotation_no,
            optional($p->quotation_date)->format('Y-m-d'),
            optional($p->date_rec)->format('Y-m-d'),
            $p->atai_products,
            $p->quotation_value ?? $p->value_with_vat,
            $p->project_type,
            $p->status_current ?? $p->status,
        ];
    }

    public function title(): string
    {
        // sheet tab name – keep as provided (e.g. "July 2025")
        return $this->title;
    }

    /* -------------------------------------------------------------
     |  Layout
     * ------------------------------------------------------------- */

    public function columnWidths(): array
    {
        return [
            'A' => 28, // Project
            'B' => 25, // Client
            'C' => 10, // Area
            'D' => 20, // Location
            'E' => 16, // Salesman
            'F' => 18, // Quotation No
            'G' => 14, // Quotation Date
            'H' => 14, // Date Received
            'I' => 20, // Product
            'J' => 18, // Value
            'K' => 16, // Project Type
            'L' => 14, // Status
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1) Push headings + data down by 5 rows
                $sheet->insertNewRowBefore(1, 5);

                $rowsCount = max($this->rows->count(), 1);

                $headerRow = 6;                 // heading row
                $dataStart = $headerRow + 1;    // 7
                $dataEnd   = $dataStart + $rowsCount - 1;
                $totalRow  = $dataEnd + 1;

                // 2) Dynamic header pieces
                $first      = $this->rows->first();
                $regionRaw  = $first->area ?? '';
                $regionText = $regionRaw ? strtoupper($regionRaw).' REGION' : '';
                $salesman   = $first->salesman ?? $first->salesperson ?? '';
                $estimator  = $this->estimatorName ?? '';
                $monthLabel = 'Month: '.$this->title;

                // 3) Top green header block (rows 1–5)

                // Row 1 – company
                $sheet->mergeCells('A1:L1');
                $sheet->setCellValue('A1', 'ARABIAN THERMAL AIRE INDUSTRIES CO. LTD.');

                // Row 2 – title
                $sheet->mergeCells('A2:L2');
                $sheet->setCellValue('A2', 'MONTHLY  REPORT');

                // Row 3 – region
                $sheet->mergeCells('A3:L3');
                $sheet->setCellValue('A3', $regionText);

                // Row 4 – section label
                $sheet->mergeCells('A4:L4');
                $sheet->setCellValue('A4', 'Project & Quotation Details');

                // Row 5 – Salesman | Estimator | Month
                // Left: Salesman
                $sheet->mergeCells('A5:D5');
                $sheet->setCellValue('A5', 'Salesman: '.$salesman);

                // Middle: Estimator
                $sheet->mergeCells('E5:H5');
                $sheet->setCellValue('E5', 'Estimator: '.$estimator);

                // Right: Month
                $sheet->mergeCells('I5:L5');
                $sheet->setCellValue('I5', $monthLabel);

                // Green fill
                $sheet->getStyle('A1:L5')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('9BBB59');

                // Center align header text
                $sheet->getStyle('A1:L4')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->getStyle('A1:L1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A2:L4')->getFont()->setBold(true);

                $sheet->getStyle('A5:L5')->getFont()->setBold(true);
                $sheet->getStyle('A5:L5')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // 4) Table header styling
                $sheet->getStyle("A{$headerRow}:L{$headerRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$headerRow}:L{$headerRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->getStyle("A{$headerRow}:L{$headerRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F4B183'); // light orange

                // 5) Borders for entire table + total row
                $sheet->getStyle("A{$headerRow}:L{$totalRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                // 6) TOTAL MONTHLY VALUE row
                $sheet->setCellValue("A{$totalRow}", 'TOTAL MONTHLY VALUE');
                $sheet->mergeCells("A{$totalRow}:I{$totalRow}");

                // Sum of Quotation Value column (J)
                $sheet->setCellValue("J{$totalRow}", "=SUM(J{$dataStart}:J{$dataEnd})");

                $sheet->getStyle("A{$totalRow}:L{$totalRow}")->getFont()->setBold(true);
            },
        ];
    }
}
