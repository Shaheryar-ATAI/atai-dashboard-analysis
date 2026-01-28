<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BncProject extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'value_usd'           => 'decimal:2',
        'award_date'          => 'date',
        'expected_close_date' => 'date',
        'bnc_exported_at'     => 'datetime',
        'scraped_at'          => 'datetime',

        'approached'          => 'boolean',
        'boq_shared'          => 'boolean',
        'submittal_shared'    => 'boolean',
        'submittal_approved'  => 'boolean',

        'penetration_percent' => 'integer',

        // Returns array/object in PHP instead of JSON string
        'raw_parties'         => 'array',
    ];

    // Optional: include this in JSON automatically
    protected $appends = [
        'parties_structured',
    ];

    /* ============================
     * Relationships
     * ============================ */

    public function responsibleSalesman()
    {
        return $this->belongsTo(User::class, 'responsible_salesman_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    /* ============================
     * Accessors
     * ============================ */

    /**
     * Returns structured parties even if raw_parties is empty.
     * This is what your modal + PDF can rely on safely.
     */
    public function getPartiesStructuredAttribute(): array
    {
        $rp = $this->raw_parties;

        // If raw_parties exists & looks usable, normalize it to array
        if (is_array($rp) && !empty($rp)) {
            return [
                'owners'        => $this->normalizePartyBlock($rp['owners'] ?? null),
                'consultant'    => $this->normalizePartyBlock($rp['lead_consultant'] ?? ($rp['consultant'] ?? null)),
                'main_epc'      => $this->normalizePartyBlock($rp['main_epc'] ?? ($rp['main_contractor'] ?? null)),
                'mep_contractor'=> $this->normalizePartyBlock($rp['mep_contractor'] ?? null),
            ];
        }

        // Fallback: parse the plain text columns (your screenshot column)
        return [
            'owners'         => self::parsePartyDetails($this->client ?? null),
            'consultant'     => self::parsePartyDetails($this->consultant ?? null),
            'main_epc'       => self::parsePartyDetails($this->main_contractor ?? null),
            'mep_contractor' => self::parsePartyDetails($this->mep_contractor ?? null),
        ];
    }

    /* ============================
     * Helpers
     * ============================ */

    /**
     * Normalize any party block into the same array format:
     * [ ['name'=>..,'status'=>..,'location'=>..], ... ]
     */
    protected function normalizePartyBlock($block): array
    {
        if (empty($block)) return [];

        // If it is already an array of items
        if (is_array($block)) {
            // Some BNC exports store objects like {name, status, location} or plain strings
            $out = [];

            foreach ($block as $item) {
                if (is_string($item)) {
                    $parsed = self::parsePartyDetails($item);
                    foreach ($parsed as $p) $out[] = $p;
                } elseif (is_array($item)) {
                    $name = trim((string)($item['name'] ?? ''));
                    if ($name === '') continue;

                    $out[] = [
                        'name'     => $name,
                        'status'   => isset($item['status']) ? trim((string)$item['status']) : null,
                        'location' => isset($item['location']) ? trim((string)$item['location']) : null,
                    ];
                }
            }

            return $out;
        }

        // If it is a string (long text)
        return self::parsePartyDetails((string)$block);
    }

    /**
     * Parse long text like:
     * "- Awarded (1) Al Mnabr ... Status : Awarded Location : Riyadh ..."
     */
    public static function parsePartyDetails(?string $text): array
    {
        $text = trim((string)$text);
        if ($text === '' || strtolower($text) === 'null') return [];

        // Split by "- " bullets
        $chunks = preg_split('/\R?\s*-\s*/u', $text);
        $chunks = array_values(array_filter(array_map('trim', $chunks)));

        $out = [];

        foreach ($chunks as $c) {
            $name = $c;
            $status = null;
            $location = null;

            // Status
            if (preg_match('/Status\s*:\s*([^:]+?)(?:\s+Location\s*:|$)/i', $c, $m)) {
                $status = trim($m[1]);
            }

            // Location
            if (preg_match('/Location\s*:\s*(.+)$/i', $c, $m)) {
                $location = trim($m[1]);
            }

            // Remove "Awarded (x)" prefix and any trailing status/location parts from name
            $name = preg_replace('/^Awarded\s*\(\d+\)\s*/i', '', $name);
            $name = preg_replace('/\s*Status\s*:.*$/i', '', $name);
            $name = trim($name);

            if ($name !== '') {
                $out[] = [
                    'name'     => $name,
                    'status'   => $status,
                    'location' => $location,
                ];
            }
        }

        return $out;
    }
}
