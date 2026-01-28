<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\Http;

class SalesmanSummaryLlmService
{
    /**
     * Optional: polish wording only.
     * Never change numbers, rag, confidence, structure.
     */
    public function enhance(array $payload, array $baseInsights): array
    {
        $base = $this->ensureNewSkeleton($baseInsights, $payload);

        $apiKey = config('services.openai.key') ?: env('OPENAI_API_KEY');
        if (!$apiKey) {
            $base['meta']['engine'] = 'rules';
            $base['meta']['note'] = 'AI skipped: missing OPENAI_API_KEY';
            return $base;
        }

        $model   = env('OPENAI_MODEL', 'gpt-4.1-mini');
        $timeout = (int) env('OPENAI_TIMEOUT', 20);

        // keep prompt tiny: send only text fields to rewrite
        $draft = [
            'high_insights' => $this->pluckTextFields($base['high_insights'] ?? [], ['title','text','gm_action']),
            'low_insights' => $this->pluckTextFields($base['low_insights'] ?? [], ['title','text','gm_interpretation']),
            'what_needs_attention' => $this->pluckTextFields($base['what_needs_attention'] ?? [], ['title','text']),
            'one_line_summary' => (string)($base['one_line_summary'] ?? ''),
        ];

        $system = <<<SYS
You are an executive copy editor for a GM-facing HVAC sales report in Saudi Arabia.
Return VALID JSON only.
Rewrite wording to be clearer and more decisive.
DO NOT change any numbers, rag, confidence, or structure.
SYS;

        $user = [
            'task' => 'Polish wording only. Keep same JSON structure and item counts.',
            'draft' => $draft,
        ];

        try {
            $resp = Http::timeout($timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.2,
                    'max_tokens' => 700,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => json_encode($user, JSON_UNESCAPED_SLASHES)],
                    ],
                    'response_format' => ['type' => 'json_object'],
                ]);

            if (!$resp->ok()) {
                $base['meta']['engine'] = 'rules';
                $base['meta']['note'] = 'AI skipped: HTTP ' . $resp->status();
                return $base;
            }

            $content = data_get($resp->json(), 'choices.0.message.content');
            $json = json_decode((string)$content, true);
            if (!is_array($json)) {
                $base['meta']['engine'] = 'rules';
                $base['meta']['note'] = 'AI skipped: invalid JSON';
                return $base;
            }

            // Merge only text fields back into base (numbers/rag/confidence preserved)
            $base = $this->mergeTextOnly($base, $json);

            $base['meta']['engine'] = 'rules+ai';
            $base['meta']['note'] = 'AI: wording polish only';
            return $base;

        } catch (\Throwable $e) {
            $base['meta']['engine'] = 'rules';
            $base['meta']['note'] = 'AI failed: ' . $e->getMessage();
            return $base;
        }
    }

    /* ============================================================
     | Helpers
     * ============================================================ */

    private function ensureNewSkeleton(array $ins, array $payload = []): array
    {
        $ins['overall_analysis'] ??= [
            'snapshot' => [],
            'regional_key_points' => [],
            'salesman_key_points' => [],
            'product_key_points' => [],
        ];
        $ins['high_insights'] ??= [];
        $ins['low_insights'] ??= [];
        $ins['what_needs_attention'] ??= [];
        $ins['one_line_summary'] ??= '';
        $ins['meta'] ??= [];

        $ins['meta']['engine'] ??= 'rules';
        $ins['meta']['generated_at'] ??= now()->toDateTimeString();
        $ins['meta']['area'] ??= (string)($payload['area'] ?? ($ins['meta']['area'] ?? 'All'));
        $ins['meta']['year'] ??= (int)($payload['year'] ?? ($ins['meta']['year'] ?? now()->year));

        return $ins;
    }

    private function pluckTextFields(array $items, array $fields): array
    {
        $out = [];
        foreach ($items as $it) {
            if (!is_array($it)) continue;
            $row = [];
            foreach ($fields as $f) $row[$f] = (string)($it[$f] ?? '');
            $out[] = $row;
        }
        return $out;
    }

    private function mergeTextOnly(array $base, array $ai): array
    {
        // high_insights
        if (isset($ai['high_insights']) && is_array($ai['high_insights'])) {
            foreach ($base['high_insights'] as $i => $it) {
                if (!isset($ai['high_insights'][$i]) || !is_array($ai['high_insights'][$i])) continue;
                foreach (['title','text','gm_action'] as $k) {
                    if (isset($ai['high_insights'][$i][$k])) $base['high_insights'][$i][$k] = (string)$ai['high_insights'][$i][$k];
                }
            }
        }

        // low_insights
        if (isset($ai['low_insights']) && is_array($ai['low_insights'])) {
            foreach ($base['low_insights'] as $i => $it) {
                if (!isset($ai['low_insights'][$i]) || !is_array($ai['low_insights'][$i])) continue;
                foreach (['title','text','gm_interpretation'] as $k) {
                    if (isset($ai['low_insights'][$i][$k])) $base['low_insights'][$i][$k] = (string)$ai['low_insights'][$i][$k];
                }
            }
        }

        // attention
        if (isset($ai['what_needs_attention']) && is_array($ai['what_needs_attention'])) {
            foreach ($base['what_needs_attention'] as $i => $it) {
                if (!isset($ai['what_needs_attention'][$i]) || !is_array($ai['what_needs_attention'][$i])) continue;
                foreach (['title','text'] as $k) {
                    if (isset($ai['what_needs_attention'][$i][$k])) $base['what_needs_attention'][$i][$k] = (string)$ai['what_needs_attention'][$i][$k];
                }
            }
        }

        if (isset($ai['one_line_summary'])) {
            $base['one_line_summary'] = (string)$ai['one_line_summary'];
        }

        return $base;
    }
}
