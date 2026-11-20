<?php

namespace App\Http\Controllers;

use App\Models\BulkJob;
use App\Models\EnvelopeTemplate;
use App\Models\Letter;
use App\Services\Documents\EnvelopePdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnvelopeDocumentController extends Controller
{
    public function preview(Request $request, int $letterId, EnvelopePdfService $service): Response
    {
        $letter = Letter::withoutGlobalScopes()->with(['workspace', 'activeNumber'])->findOrFail($letterId);
        $user = $request->user();
        if (! $letter->workspace || ! $letter->workspace->hasMember($user)) {
            abort(403);
        }
        if (! $user->can('amplop.preview_download') && ! $user->can('kop_surat.manage') && ! $user->can('surat_keluar.view')) {
            abort(403, 'Anda tidak memiliki izin melihat pratinjau amplop.');
        }

        $templateId = $request->query('template_id');
        $template = $templateId ? EnvelopeTemplate::find($templateId) : null;

        $custom = [
            'tujuan' => $request->query('tujuan'),
            'instansi' => $request->query('instansi'),
            'alamat' => $request->query('alamat'),
            'nomor_surat' => $request->query('nomor_surat'),
        ];
        $custom = array_filter($custom, fn ($v) => filled($v));

        $res = $service->renderSingle($letter, $template, $custom);
        if (! $res['ok'] || empty($res['path']) || ! Storage::disk('local')->exists($res['path'])) {
            abort(500, $res['message'] ?? 'Gagal menghasilkan pratinjau amplop');
        }

        $fullPath = Storage::disk('local')->path($res['path']);

        return response()->file($fullPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="amplop-'.Str::slug($letter->formatted_number ?? "letter-{$letter->id}").'.pdf"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function download(Request $request, int $letterId, EnvelopePdfService $service): Response
    {
        $letter = Letter::withoutGlobalScopes()->with(['workspace', 'activeNumber'])->findOrFail($letterId);
        $user = $request->user();
        if (! $letter->workspace || ! $letter->workspace->hasMember($user)) {
            abort(403);
        }
        if (! $user->can('amplop.preview_download') && ! $user->can('kop_surat.manage') && ! $user->can('surat_keluar.view')) {
            abort(403, 'Anda tidak memiliki izin mengunduh amplop.');
        }

        $templateId = $request->query('template_id');
        $template = $templateId ? EnvelopeTemplate::find($templateId) : null;

        $custom = [
            'tujuan' => $request->query('tujuan'),
            'instansi' => $request->query('instansi'),
            'alamat' => $request->query('alamat'),
            'nomor_surat' => $request->query('nomor_surat'),
        ];
        $custom = array_filter($custom, fn ($v) => filled($v));

        $res = $service->renderSingle($letter, $template, $custom);
        if (! $res['ok'] || empty($res['path']) || ! Storage::disk('local')->exists($res['path'])) {
            abort(500, $res['message'] ?? 'Gagal menghasilkan amplop PDF');
        }

        if ($request->query('format') === 'docx') {
            if (! empty($res['docx_path']) && Storage::disk('local')->exists($res['docx_path'])) {
                $docxFullPath = Storage::disk('local')->path($res['docx_path']);
                $docxName = 'amplop-'.Str::slug($letter->formatted_number ?? "letter-{$letter->id}").'.docx';
                return response()->download($docxFullPath, $docxName);
            }
        }

        $fullPath = Storage::disk('local')->path($res['path']);
        $fileName = 'amplop-'.Str::slug($letter->formatted_number ?? "letter-{$letter->id}").'.pdf';

        return response()->download($fullPath, $fileName, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function downloadBulk(Request $request, string $bulkJobId, EnvelopePdfService $service): Response
    {
        @set_time_limit(180);

        $job = BulkJob::withoutGlobalScopes()->with('workspace')->findOrFail($bulkJobId);
        $user = $request->user();
        if (! $job->workspace || ! $job->workspace->hasMember($user)) {
            abort(403);
        }
        if (! $user->can('amplop.preview_download') && ! $user->can('bulk.preview_download') && ! $user->can('kop_surat.manage') && ! $user->can('surat_keluar.view')) {
            abort(403, 'Anda tidak memiliki izin mengunduh berkas amplop massal.');
        }

        $templateId = $request->query('template_id');
        $template = $templateId ? EnvelopeTemplate::find($templateId) : null;

        $res = $service->renderBulk($job, $template);
        if (! $res['ok'] || empty($res['path']) || ! Storage::disk('local')->exists($res['path'])) {
            abort(500, $res['message'] ?? 'Gagal menghasilkan gabungan PDF amplop');
        }

        $fullPath = Storage::disk('local')->path($res['path']);
        $fileName = 'semua-amplop-batch-'.Str::slug($job->perihal ?: $job->id).'.pdf';

        if (! $request->boolean('download')) {
            return response()->file($fullPath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$fileName.'"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]);
        }

        return response()->download($fullPath, $fileName, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
