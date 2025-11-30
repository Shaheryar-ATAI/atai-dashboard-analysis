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

class WeekSheetExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithTitle,
    WithEvents,
    WithColumnWidths
{
    /** @var \Illuminate\Support\Collection|Project[] */
    protected Collection $rows;

    // sheet title like "23-11-2025 to 27-11-2025"
    protected string $title;

    // logged-in estimator (Jaafar, Aamir, …)
    protected ?string $estimatorName;

    public function __construct(Collection $rows, string $title, ?string $estimatorName = null)
    {
        $this->rows          = $rows;
        $this->title         = $title;
        $this->estimatorName = $estimatorName;
    }

    /* -----------------------------------------------------------------
     |  Data
     * ----------------------------------------------------------------- */

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Project Name',            // A
            'Client Name',             // B
            'Area',                    // C
            'Project Location',        // D
            'Salesman',                // E
            'Quotation No',            // F
            'Received Date',           // G (date_rec)
            'Quotation Date',          // H (quotation_date)
            'Product',                 // I (atai_products)
            'Weekly Net Value (SAR)',  // J (quotation_value)
            'Project Type',            // K
            'Technical Submittal',     // L
            'Technical Base',          // M
            'Contact Person',          // N
            'Contact Number',          // O
            'Contact Email',           // P
            'Company Address',         // Q
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
            optional($p->date_rec)->format('Y-m-d'),
            optional($p->quotation_date)->format('Y-m-d'),
            $p->atai_products,
            $p->quotation_value ?? $p->value_with_vat ?? 0,
            $p->project_type,
            $p->technical_submittal ?? null,
            $p->technical_base ?? null,
            $p->contact_person ?? null,
            $p->contact_number ?? null,
            $p->contact_email ?? null,
            $p->company_address ?? null,
        ];
    }

    public function title(): string
    {
        // Excel sheet title limit is 31 characters
        return mb_substr($this->title, 0, 31);
    }

    /* -----------------------------------------------------------------
     |  Layout: widths
     * ----------------------------------------------------------------- */

    public function columnWidths(): array
    {
        return [
            'A' => 28,
            'B' => 25,
            'C' => 10,
            'D' => 18,
            'E' => 15,
            'F' => 18,
            'G' => 14,
            'H' => 14,
            'I' => 18,
            'J' => 18,
            'K' => 14,
            'L' => 16,
            'M' => 16,
            'N' => 22,
            'O' => 16,
            'P' => 28,
            'Q' => 32,
        ];
    }

    /* -----------------------------------------------------------------
     |  Events: header block + totals + styling
     * ----------------------------------------------------------------- */

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                $sheet = $event->sheet->getDelegate();

                // ---- 1. Push headings + data down by 5 rows ----
                // WithHeadings writes headings at row 1 by default.
                // Insert 5 rows so headings go to row 6.
                $sheet->insertNewRowBefore(1, 5);

                $rowsCount = max($this->rows->count(), 1);

                $headerRow = 6;                 // headings row
                $dataStart = $headerRow + 1;    // 7
                $dataEnd   = $dataStart + $rowsCount - 1;
                $totalRow  = $dataEnd + 1;

                // ---- 2. Dynamic texts from data ----
                $first      = $this->rows->first();
                $regionRaw  = $first->area ?? '';
                $regionText = $regionRaw ? strtoupper($regionRaw).' REGION' : '';
                $salesman   = $first->salesman ?? $first->salesperson ?? '';
                $estimator  = $this->estimatorName ?? '';
                $weekLabel  = 'Week: '.$this->title;

                // ---- 3. Company header (rows 1–4) ----
                // Row 1: Company name
                $sheet->mergeCells('A1:Q1');
                $sheet->setCellValue('A1', 'ARABIAN THERMAL AIRE INDUSTRIES CO. LTD.');

                // Row 2: Monthly & Weekly Report
                $sheet->mergeCells('A2:Q2');
                $sheet->setCellValue('A2', ' WEEKLY REPORT');

                // Row 3: Region
                $sheet->mergeCells('A3:Q3');
                $sheet->setCellValue('A3', $regionText);

                // Row 4: Section title
                $sheet->mergeCells('A4:Q4');
                $sheet->setCellValue('A4', 'Project & Quotation Details');

                // Row 5: Salesman | Estimator | Week
                $sheet->mergeCells('A5:F5');
                $sheet->setCellValue('A5', 'Salesman: '.$salesman);

                $sheet->mergeCells('G5:L5');
                $sheet->setCellValue('G5', 'Estimator: '.$estimator);

                $sheet->mergeCells('M5:Q5');
                $sheet->setCellValue('M5', $weekLabel);

                // Green background for top block
                $sheet->getStyle('A1:Q5')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('9BBB59');

                // Center alignment for header text
                $sheet->getStyle('A1:Q4')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->getStyle('A1:Q1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A2:Q4')->getFont()->setBold(true);

                // Row 5 slightly smaller but bold
                $sheet->getStyle('A5:Q5')->getFont()->setBold(true);
                $sheet->getStyle('A5:Q5')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // ---- 4. Table header styling (row 6) ----
                $sheet->getStyle("A{$headerRow}:Q{$headerRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$headerRow}:Q{$headerRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->getStyle("A{$headerRow}:Q{$headerRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F4B183'); // light orange, like your template

                // ---- 5. Borders for table + total row ----
                $sheet->getStyle("A{$headerRow}:Q{$totalRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                // ---- 6. Total weekly value row ----
                $sheet->setCellValue("A{$totalRow}", 'TOTAL WEEKLY VALUE');
                $sheet->mergeCells("A{$totalRow}:I{$totalRow}");

                // Sum Weekly Net Value column (J)
                $sheet->setCellValue("J{$totalRow}", "=SUM(J{$dataStart}:J{$dataEnd})");

                $sheet->getStyle("A{$totalRow}:Q{$totalRow}")->getFont()->setBold(true);
            },
        ];
    }
}
