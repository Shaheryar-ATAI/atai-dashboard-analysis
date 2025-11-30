<?php

namespace App\Exports\Coordinator;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Events\BeforeSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SalesOrdersMonthExport implements
    FromCollection,
    WithHeadings,
    WithStyles,
    WithTitle,
    WithCustomStartCell,
    WithColumnWidths,
    WithEvents,
    WithDrawings
{
    use Exportable;

    protected Collection $rows;
    protected int $year;
    protected int $month;
    protected string $regionLabel;

    public function __construct(Collection $rows, int $year, int $month, string $regionLabel = 'All Regions')
    {
        $this->rows        = $rows;
        $this->year        = $year;
        $this->month       = $month;
        $this->regionLabel = $regionLabel;
    }

    public function title(): string
    {
        $monthName = date('M', mktime(0, 0, 0, $this->month, 1));
        return "{$monthName}-{$this->year}";
    }

    /**
     * Start table after the header block (row 9).
     */
    public function startCell(): string
    {
        return 'A9';
    }

    public function headings(): array
    {
        return [
            'Client Name',
            'Count/Region',
            'Location',
            'Date Rec',
            'PO No.',
            'Products',
            'Quote No.',
            'Ref No.',
            'Cur',
            'PO Value',
            'Value with VAT',
            'Payment Terms',
            'Project Name',
            'Project Location',
            'Status (OAA)',
            'Sales OAA',
            'Job No',
            'Factory Loc',
            'Sales Source',
            'Remarks',
        ];
    }

    /**
     * Province-wise summary of PO values by status.
     * LEFT BLOCK: Pre-Acceptance / Accepted / Rejected / Cancelled → sums of PO VALUE.
     * RIGHT BLOCK: Total Orders (Accepted & Pre-Acceptance) → sum of all PO VALUE per province.
     */
    protected function buildProvinceSummary(): array
    {
        $provinces = [
            'EASTERN' => 'KSA Eastern Province',
            'WESTERN' => 'KSA Western Province',
            'CENTRAL' => 'KSA Central Province',
            'EXPORT'  => 'Export ( Qatar, Bahrain & Kuwait)',
        ];

        $summary = [];
        foreach ($provinces as $key => $label) {
            $summary[$key] = [
                'label'     => $label,
                'pre'       => 0.0,
                'accepted'  => 0.0,
                'rejected'  => 0.0,
                'cancelled' => 0.0,
                'total'     => 0.0,
            ];
        }

        foreach ($this->rows as $row) {
            // ---- Region (we use alias "area" from the selectRaw) ----
            $regionRaw = strtoupper(trim((string) ($row->area ?? '')));

            if (str_contains($regionRaw, 'EAST')) {
                $provKey = 'EASTERN';
            } elseif (str_contains($regionRaw, 'WEST')) {
                $provKey = 'WESTERN';
            } elseif (str_contains($regionRaw, 'CENT')) {
                $provKey = 'CENTRAL';
            } elseif (
                str_contains($regionRaw, 'EXPORT') ||
                str_contains($regionRaw, 'QATAR')  ||
                str_contains($regionRaw, 'BAHRAIN')||
                str_contains($regionRaw, 'KUWAIT')
            ) {
                $provKey = 'EXPORT';
            } else {
                // Unknown region → ignore for header
                continue;
            }

            // ---- PO VALUE (ALWAYS) ----
            $po = (float) ($row->po_value ?? 0);
            if ($po <= 0) {
                // nothing to add, skip to next row
                continue;
            }

            // total PO value per province (RIGHT block)
            $summary[$provKey]['total'] += $po;

            // status buckets (LEFT block)
            $statusNorm = $this->extractStatus($row);

            switch ($statusNorm) {
                case 'PRE-ACCEPTANCE':
                    $summary[$provKey]['pre'] += $po;
                    break;
                case 'ACCEPTANCE':
                    $summary[$provKey]['accepted'] += $po;
                    break;
                case 'REJECTED':
                    $summary[$provKey]['rejected'] += $po;
                    break;
                case 'CANCELLED':
                    $summary[$provKey]['cancelled'] += $po;
                    break;
                default:
                    // statuses like "PURCHASE ORDER" etc are ignored in left block
                    break;
            }
        }

        return $summary;
    }

    public function collection(): Collection
    {
        return $this->rows->map(function ($so) {
            return [
                $so->client,
                $so->area,
                $so->location,
                $so->date_rec,
                $so->po_no,
                $so->atai_products,
                $so->quotation_no,
                $so->ref_no ?? '',
                $so->cur ?? 'SAR',
                (float) ($so->po_value),
                (float) ($so->value_with_vat ?? 0),
                $so->payment_terms,
                $so->project,
                $so->project_location,
                $so->status,
                $so->oaa ?? '',
                $so->job_no,
                $so->factory_loc,
                $so->salesman,
                $so->remarks,
            ];
        });
    }

    public function columnWidths(): array
    {
        return [
            'A' => 26,
            'B' => 12,
            'C' => 16,
            'D' => 12,
            'E' => 16,
            'F' => 18,
            'G' => 18,
            'H' => 25,
            'I' => 20,
            'J' => 20,
            'K' => 18,
            'L' => 18,
            'M' => 26,
            'N' => 22,
            'O' => 16,
            'P' => 12,
            'Q' => 12,
            'R' => 14,
            'S' => 14,
            'T' => 22,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // table header row
        $sheet->getStyle('A9:T9')->applyFromArray([
            'font' => [
                'bold' => true,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color'    => ['rgb' => 'DDDDDD'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ]);

        // freeze after header
        $sheet->freezePane('A10');

        // borders for data
        $highestRow = $sheet->getHighestRow();
        $sheet->getStyle("A9:T{$highestRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        return [];
    }

    public function drawings()
    {
        $logoPath = public_path('images/atai-logo.png');
        if (! file_exists($logoPath)) {
            return [];
        }

        $drawing = new Drawing();
        $drawing->setName('ATAI Logo');
        $drawing->setDescription('ATAI');
        $drawing->setPath($logoPath);
        $drawing->setHeight(45);
        $drawing->setCoordinates('A1');

        return [$drawing];
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $provSummary = $this->buildProvinceSummary();
                $order       = ['EASTERN', 'WESTERN', 'CENTRAL', 'EXPORT'];

                // totals across provinces (LEFT block)
                $totPre       = 0.0;
                $totAccepted  = 0.0;
                $totRejected  = 0.0;
                $totCancelled = 0.0;

                // totals for RIGHT block
                $totalPerProvince = [];

                foreach ($order as $key) {
                    $rowSummary = $provSummary[$key] ?? [
                        'pre'       => 0,
                        'accepted'  => 0,
                        'rejected'  => 0,
                        'cancelled' => 0,
                        'total'     => 0,
                    ];

                    $totPre       += $rowSummary['pre'];
                    $totAccepted  += $rowSummary['accepted'];
                    $totRejected  += $rowSummary['rejected'];
                    $totCancelled += $rowSummary['cancelled'];

                    $totalPerProvince[$key] = $rowSummary['total'] ?? 0;
                }

                $totalAllProvinces = array_sum($totalPerProvince);

                /*
                 * LEFT BLOCK: Province vs Pre / Accepted / Rejected / Cancelled (PO VALUE)
                 */
                $sheet->setCellValue('A2', 'Province');
                $sheet->setCellValue('B2', 'Pre-Acceptance');
                $sheet->setCellValue('C2', 'Accepted');
                $sheet->setCellValue('D2', 'Rejected');
                $sheet->setCellValue('E2', 'Cancelled');

                $sheet->getStyle('A2:E2')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color'    => ['rgb' => 'FFFF00'],
                    ],
                    'font' => ['bold' => true],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                ]);

                $row = 3;
                foreach ($order as $key) {
                    $s = $provSummary[$key];

                    $sheet->setCellValue("A{$row}", $s['label']);
                    $sheet->setCellValue("B{$row}", $s['pre']);
                    $sheet->setCellValue("C{$row}", $s['accepted']);
                    $sheet->setCellValue("D{$row}", $s['rejected']);
                    $sheet->setCellValue("E{$row}", $s['cancelled']);

                    $row++;
                }

                $sheet->getStyle('A2:E6')
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                $sheet->setCellValue('A7', 'Total Orders Received');
                $sheet->setCellValue('B7', $totPre);
                $sheet->setCellValue('C7', $totAccepted);
                $sheet->setCellValue('D7', $totRejected);
                $sheet->setCellValue('E7', $totCancelled);

                $sheet->getStyle('A7:E7')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FF0000'],
                    ],
                    'borders' => [
                        'top'    => ['borderStyle' => Border::BORDER_THIN],
                        'bottom' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                ]);

                /*
                 * RIGHT BLOCK: Total Orders (Accepted & Pre Acceptance)
                 * = simple PO value total per province (no extra status filter)
                 */
                $sheet->setCellValue('H2', 'Province');
                $sheet->setCellValue('I2', 'Total Orders ( Accepted & Pre Acceptance)');
                $sheet->mergeCells('I2:K2');

                $sheet->getStyle('H2:K2')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color'    => ['rgb' => 'FFFF00'],
                    ],
                    'font' => ['bold' => true],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                ]);

                $row = 3;
                foreach ($order as $key) {
                    $sheet->setCellValue("H{$row}", $provSummary[$key]['label']);
                    $sheet->setCellValue("I{$row}", $totalPerProvince[$key] ?? 0);
                    $row++;
                }

                $sheet->getStyle('H2:K6')
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                $sheet->setCellValue('H7', 'Total Orders Received');
                $sheet->setCellValue('I7', $totalAllProvinces);

                $sheet->getStyle('H7:I7')->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'color' => ['rgb' => 'FF0000'],
                    ],
                    'borders' => [
                        'top'    => ['borderStyle' => Border::BORDER_THIN],
                        'bottom' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                ]);

                $monthNameShort = date('M', mktime(0, 0, 0, $this->month, 1));
                $sheet->setCellValue('N2', "{$monthNameShort}-{$this->year}");
            },
        ];
    }

    /**
     * Normalise status from the row (uses aliases "oaa" and "status").
     */
    protected function extractStatus($row): string
    {
        // We only care about OAA-style statuses, from Sales OAA or Status.
        $raw = '';

        if (isset($row->oaa) && trim((string) $row->oaa) !== '') {
            $raw = (string) $row->oaa;
        } elseif (isset($row->status) && trim((string) $row->status) !== '') {
            $raw = (string) $row->status;
        }

        if ($raw === '') {
            return '';
        }

        $rawUpper = strtoupper(trim($raw));

        if (str_contains($rawUpper, 'PRE')) {
            return 'PRE-ACCEPTANCE';
        }
        if (str_contains($rawUpper, 'ACCEPT')) {
            // (not PRE → already caught above)
            return 'ACCEPTANCE';
        }
        if (str_contains($rawUpper, 'REJECT')) {
            return 'REJECTED';
        }
        if (str_contains($rawUpper, 'CANCEL')) {
            return 'CANCELLED';
        }

        return $rawUpper; // fallback
    }
}
