<?php
// app/Http/Controllers/ProjectSubmittalController.php
namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectSubmittal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectSubmittalController extends Controller
{
    public function store(Request $r, Project $project)
    {
        $r->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:25600'], // 25 MB
            'phase' => ['nullable', 'string', 'in:bidding,inhand'],
        ]);

        $phase = strtolower($r->input('phase', 'bidding'));
        $file = $r->file('file');
        $origName = $file->getClientOriginalName();
        $safeBase = Str::limit(Str::slug(pathinfo($origName, PATHINFO_FILENAME)), 64, '');
        $finalName = $safeBase . '-' . now()->format('YmdHis') . '-' . Str::random(4) . '.' . $file->getClientOriginalExtension();

        // Store on the "public" disk
        $relPath = $file->storeAs("submittals/{$project->id}/{$phase}", $finalName, 'public'); // e.g. submittals/3/bidding/foo.pdf

        $sub = ProjectSubmittal::create([
            'project_id' => $project->id,
            'phase' => $phase,
            'file_path' => $relPath,           // store RELATIVE path on 'public' disk
            'original_name' => $origName,
            'mime' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $r->user()?->id,
        ]);

        // Prefer a relative /storage/... path (same-origin safe), plus a streaming fallback
        $relStorageUrl = Storage::disk('public')->url($relPath);        // "/storage/submittals/3/bidding/foo.pdf"
        $streamUrl = route('submittals.stream', ['id' => $sub->id]); // "/submittals/stream/123"

        Log::info('SUBMITTAL_UPLOADED', [
            'rel_path' => $relPath,
            'rel_storage_url' => $relStorageUrl,
            'stream_url' => $streamUrl,
            'exists' => Storage::disk('public')->exists($relPath),
        ]);

        return response()->json([
            'ok' => true,
            'id' => $sub->id,
            'name' => $sub->original_name,
            'url_rel' => $relStorageUrl,   // RETURN RELATIVE URL
            'url_stream' => $streamUrl,       // FALLBACK that works without symlink
            'phase' => $sub->phase,
            'size' => $sub->size_bytes,
            'created' => $sub->created_at->toDateTimeString(),
        ]);
    }

    public function showLatest(Project $project, string $phase)
    {
        $phase = strtolower($phase ?: 'bidding');
        $sub = ProjectSubmittal::where('project_id', $project->id)
            ->where('phase', $phase)
            ->latest('id')->first();

        if (!$sub) {
            return response()->json(['ok' => true, 'exists' => false]);
        }

        $relPath = $sub->file_path;
        $exists = $relPath && Storage::disk('public')->exists($relPath);

        return response()->json([
            'ok' => true,
            'exists' => $exists,
            'name' => $sub->original_name,
            'url_rel' => $exists ? Storage::disk('public')->url($relPath) : null, // "/storage/..."
            'url_stream' => route('submittals.stream', ['id' => $sub->id]),
            'phase' => $sub->phase,
            'id' => $sub->id,
            'size' => $sub->size_bytes,
        ]);
    }

    // Fallback: stream from storage even if symlink is unavailable (GoDaddy-safe)
    public function stream(int $id): StreamedResponse
    {
        $sub = ProjectSubmittal::findOrFail($id);
        abort_unless(Storage::disk('public')->exists($sub->file_path), 404);

        $absPath = Storage::disk('public')->path($sub->file_path);
        return response()->file($absPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . addslashes($sub->original_name) . '"',
            'Cache-Control' => 'public, max-age=604800', // 7 days
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
