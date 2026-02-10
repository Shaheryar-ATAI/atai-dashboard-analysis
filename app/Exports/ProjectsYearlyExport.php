<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProjectsYearlyExport implements
    FromCollection,
    WithHeadings,
    WithTitle,
    WithEvents,
    WithColumnWidths
{
    use Exportable;

    protected Collection $rows;
    protected string $yearLabel;     // e.g. "2025"
    protected ?string $preparedBy;   // estimator name (optional)

    public function __construct(Collection $rows, string $yearLabel, ?string $preparedBy = null)
    {
        $this->rows       = $rows;
        $this->yearLabel  = $yearLabel;
        $this->preparedBy = $preparedBy;
    }

    /* -----------------------------------------------------------------
     |  Data
     * ----------------------------------------------------------------- */

    public function collection(): Collection
    {
        // Keep columns consistent with your MONTHLY export:
        return $this->rows->map(function ($p) {
            return [
                'Project Name'           => $p->name ?? $p->project_name,
                'Client Name'            => $p->client ?? $p->client_name,
                'Area'                   => $p->area,
                'Project Location'       => $p->location ?? $p->project_location,
                'Sales Source'           => $p->salesperson ?? $p->salesman,
                'Quotation No'           => $p->quotation_no,
                'Quotation Date'         => optional($p->quotation_date)->format('Y-m-d'),
                'Date Received'          => optional($p->date_rec)->format('Y-m-d'),
                'Product'                => $p->atai_products,
                'Quotation Value (SAR)'  => $p->quotation_value,
                'Project Type'           => $p->project_type ?? '',
                'Status'                 => $p->status_display ?? $p->status,
            ];
        });
    }

    public function headings(): array
    {
        // Row 6 in your template
        return [
            'Project Name',
            'Client Name',
            'Area',
            'Project Location',
            'Sales Source',
            'Quotation No',
            'Quotation Date',
            'Date Received',
            'Product',
            'Quotation Value (SAR)',
            'Project Type',
            'Status',
        ];
    }

    public function title(): string
    {
        // Sheet tab name
        return 'Year ' . $this->yearLabel;
    }

    /* -----------------------------------------------------------------
     |  Column widths (similar to Monthly)
     * ----------------------------------------------------------------- */

    public function columnWidths(): array
    {
        return [
            'A' => 28, // Project
            'B' => 25, // Client
            'C' => 10, // Area
            'D' => 20, // Location
            'E' => 14, // Sales Source
            'F' => 20, // Quotation No
            'G' => 14, // Quotation Date
            'H' => 14, // Date Received
            'I' => 18, // Product
            'J' => 18, // Quotation Value
            'K' => 16, // Project Type
            'L' => 12, // Status
        ];
    }

    /* -----------------------------------------------------------------
     |  Styling – copy of Monthly layout
     * ----------------------------------------------------------------- */

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                /** @var Worksheet $sheet */
                $sheet = $event->sheet->getDelegate();

                // 1) Insert 5 rows at top so headings start at row 6
                $sheet->insertNewRowBefore(1, 5);

                $rowsCount = max($this->rows->count(), 1);

                $headerRow = 6;                 // headings row
                $dataStart = $headerRow + 1;    // 7
                $dataEnd   = $dataStart + $rowsCount - 1;
                $totalRow  = $dataEnd + 1;

                // 2) Dynamic info from first row
                $first       = $this->rows->first();
                $regionRaw   = $first->area ?? '';
                $regionText  = $regionRaw ? strtoupper($regionRaw).' REGION' : '';
                $salesman    = $first->salesperson ?? $first->salesman ?? '';
                $estimator   = $this->preparedBy ?? '';
                $yearLabel   = 'Year: ' . $this->yearLabel;

                // 3) COMPANY HEADER BLOCK  (rows 1–5) – match Monthly layout
                // row 1: company name
                $sheet->mergeCells('A1:L1');
                $sheet->setCellValue('A1', 'ARABIAN THERMAL AIRE INDUSTRIES CO. LTD.');

                // row 2: report title
                $sheet->mergeCells('A2:L2');
                $sheet->setCellValue('A2', ' YEARLY REPORT');

                // row 3: region
                $sheet->mergeCells('A3:L3');
                $sheet->setCellValue('A3', $regionText);

                // row 4: section heading
                $sheet->mergeCells('A4:L4');
                $sheet->setCellValue('A4', 'Project & Quotation Details');

                // row 5: Sales Source | Estimator | Year
                $sheet->mergeCells('A5:D5');
                $sheet->setCellValue('A5', 'Sales Source: ' . $salesman);

                $sheet->mergeCells('E5:H5');
                $sheet->setCellValue('E5', 'Estimator: ' . $estimator);

                $sheet->mergeCells('I5:L5');
                $sheet->setCellValue('I5', $yearLabel);

                // green background
                $sheet->getStyle('A1:L5')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('9BBB59');

                // alignment + fonts
                $sheet->getStyle('A1:L4')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->getStyle('A1:L1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A2:L4')->getFont()->setBold(true);

                $sheet->getStyle('A5:L5')->getFont()->setBold(true);
                $sheet->getStyle('A5:L5')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // 4) TABLE HEADER ROW (row 6) – orange bar, centered, bold
                $sheet->getStyle("A{$headerRow}:L{$headerRow}")->getFont()->setBold(true);

                $sheet->getStyle("A{$headerRow}:L{$headerRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                $sheet->getStyle("A{$headerRow}:L{$headerRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F4B183'); // light orange as in your template

                // 5) BORDERS for table + total row
                $sheet->getStyle("A{$headerRow}:L{$totalRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                // 6) TOTAL YEARLY VALUE row under data
                $sheet->setCellValue("A{$totalRow}", 'TOTAL YEARLY VALUE');
                $sheet->mergeCells("A{$totalRow}:I{$totalRow}");

                // Sum quotation values in column J
                $sheet->setCellValue("J{$totalRow}", "=SUM(J{$dataStart}:J{$dataEnd})");

                $sheet->getStyle("A{$totalRow}:L{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("J{$dataStart}:J{$totalRow}")
                    ->getNumberFormat()->setFormatCode('#,##0');

                // right-align quotation column
                $sheet->getStyle("J{$dataStart}:J{$totalRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
