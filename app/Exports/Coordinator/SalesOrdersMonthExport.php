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
    protected int $startRow = 9;

    public function __construct(Collection $rows, int $year, int $month, string $regionLabel = '')
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
     * Start table after the header block.
     */
    public function startCell(): string
    {
        return 'A' . $this->startRow;
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
            // ❌ Removed Factory Loc
            'Sales Source',
            'Remarks',
        ];
    }

    public function collection(): Collection
    {
        return $this->rows->map(function ($so) {
            $poValue = $so->po_value ?? $so->total_po_value ?? $so->{'PO Value'} ?? 0;
            $vatValue = $so->value_with_vat ?? $so->{'value_with_vat'} ?? 0;
            $area = $so->area ?? $so->region ?? '';
            $location = $so->location ?? $so->project_location ?? '';
            $projectLocation = $so->project_location ?? $so->location ?? '';
            $status = $so->status ?? ($so->{'Status'} ?? '');
            $oaa = $so->oaa ?? ($so->{'Sales OAA'} ?? '');
            $salesman = $so->salesman ?? ($so->{'Sales Source'} ?? '');
            $remarks = $so->remarks ?? ($so->{'Remarks'} ?? '');

            return [
                $so->client ?? '',
                $area,
                $location,
                $so->date_rec ?? $so->po_date ?? '',
                $so->po_no ?? '',
                $so->atai_products ?? '',
                $so->quotation_no ?? '',
                $so->ref_no ?? '',
                $so->cur ?? 'SAR',
                (float) ($poValue),
                (float) ($vatValue),
                $so->payment_terms ?? '',
                $so->project ?? '',
                $projectLocation,
                $status,
                $oaa,
                $so->job_no ?? '',
                // ❌ Removed $so->factory_loc
                $salesman,
                $remarks,
            ];
        });
    }

    /**
     * Province-wise summary of PO values by status.
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
                continue;
            }

            $po = (float) ($row->po_value ?? $row->total_po_value ?? 0);
            if ($po <= 0) continue;

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
                    break;
            }

            // Total shown in the summary excludes rejected values
            if ($statusNorm !== 'REJECTED') {
                $summary[$provKey]['total'] += $po;
            }
        }

        return $summary;
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
            'S' => 22, // Remarks
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $headerRow    = $this->startRow;
        $dataStartRow = $headerRow + 1;

        // header row (A..S)
        $sheet->getStyle("A{$headerRow}:S{$headerRow}")->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color'    => ['rgb' => 'DDDDDD'],
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);

        $sheet->freezePane("A{$dataStartRow}");

        $highestRow = $sheet->getHighestRow();
        $sheet->getStyle("A{$headerRow}:S{$highestRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Alternating row colors for the data rows
        $altFill = [
            'fillType' => Fill::FILL_SOLID,
            'color'    => ['rgb' => 'F7F7F7'],
        ];

        for ($row = $dataStartRow; $row <= $highestRow; $row++) {
            if ((($row - $dataStartRow) % 2) === 1) {
                $sheet->getStyle("A{$row}:S{$row}")->applyFromArray([
                    'fill' => $altFill,
                ]);
            }
        }

        // Highlight rejected rows (light red)
        $rejectFill = [
            'fillType' => Fill::FILL_SOLID,
            'color'    => ['rgb' => 'FCE8E8'],
        ];
        $idx = 0;
        foreach ($this->rows as $row) {
            $status = $this->extractStatus($row);
            if ($status === 'REJECTED') {
                $r = $dataStartRow + $idx;
                $sheet->getStyle("A{$r}:S{$r}")->applyFromArray([
                    'fill' => $rejectFill,
                ]);
            }
            $idx++;
        }

        return [];
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $provSummary = $this->buildProvinceSummary();
                $order       = ['EASTERN', 'WESTERN', 'CENTRAL', 'EXPORT'];

                $totalPerProvince = [];
                foreach ($order as $key) {
                    $totalPerProvince[$key] = $provSummary[$key]['total'] ?? 0;
                }

                $totalAllProvinces = array_sum($totalPerProvince);

                // RIGHT BLOCK (keep)
                $sheet->setCellValue('H2', 'Regional Concertration');
                $sheet->setCellValue('I2', 'Total Orders Regional Concertration ( Accepted & Pre Acceptance)');
                $sheet->setCellValue('J2', 'Rejected Value');
                    
                $sheet->getStyle('H2:J2')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color'    => ['rgb' => 'FFFF00'],
                    ],
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                ]);

                $row = 3;
                foreach ($order as $key) {
                    $sheet->setCellValue("H{$row}", $provSummary[$key]['label']);
                    $sheet->setCellValue("I{$row}", $totalPerProvince[$key] ?? 0);
                    $sheet->setCellValue("J{$row}", $provSummary[$key]['rejected'] ?? 0);
                    $row++;
                }

                $sheet->getStyle('H2:J6')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                $sheet->setCellValue('H7', 'Total Orders Received');
                $sheet->setCellValue('I7', $totalAllProvinces);
                $sheet->setCellValue('J7', array_sum(array_map(
                    fn($k) => $provSummary[$k]['rejected'] ?? 0,
                    $order
                )));

                $sheet->getStyle('H7:J7')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FF0000']],
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

    protected function extractStatus($row): string
    {
        $raw = '';

        if (isset($row->rejected_at) && !empty($row->rejected_at)) {
            return 'REJECTED';
        }

        if (isset($row->oaa) && trim((string) $row->oaa) !== '') {
            $raw = (string) $row->oaa;
        } elseif (isset($row->status) && trim((string) $row->status) !== '') {
            $raw = (string) $row->status;
        }

        if ($raw === '') return '';

        $rawUpper = strtoupper(trim($raw));

        if (str_contains($rawUpper, 'PRE'))    return 'PRE-ACCEPTANCE';
        if (str_contains($rawUpper, 'ACCEPT')) return 'ACCEPTANCE';
        if (str_contains($rawUpper, 'REJECT')) return 'REJECTED';
        if (str_contains($rawUpper, 'CANCEL')) return 'CANCELLED';

        return $rawUpper;
    }

}
