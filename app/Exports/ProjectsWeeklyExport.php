<?php

namespace App\Exports;

use App\Models\Project;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProjectsWeeklyExport implements WithMultipleSheets
{
    /** @var array<string, \Illuminate\Support\Collection|Project[]> */
    protected array $groups;

    protected ?string $estimatorName;

    /**
     * @param array<string, Project[]> $groups
     */
    public function __construct(array $groups, ?string $estimatorName = null)
    {
        $this->groups        = [];
        $this->estimatorName = $estimatorName;

        // Normalize to Collections
        foreach ($groups as $label => $rows) {
            $this->groups[$label] = $rows instanceof Collection
                ? $rows
                : collect($rows);
        }
    }

    public function sheets(): array
    {
        $sheets = [];

        foreach ($this->groups as $label => $rows) {
            $sheets[] = new WeekSheetExport($rows, $label, $this->estimatorName);
        }

        return $sheets;
    }
}
