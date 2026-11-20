<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Services\Documents\DocxRenderer;
use App\Services\Documents\OnlyOfficeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OnlyOfficeController extends Controller
{
    public function __construct(
        private readonly OnlyOfficeService $onlyOfficeService,
        private readonly DocxRenderer $docxRenderer,
    ) {}

    /**
     * Download .docx document for OnlyOffice Document Server.
     */
    public function download(Request $request, Letter $letter): BinaryFileResponse|JsonResponse
    {
        $token = (string) $request->query('token');

        // Verify token authenticity
        if (! $this->onlyOfficeService->verifyAccessToken($token, $letter, 'download')) {
            Log::warning("Unauthorized OnlyOffice download request for letter {$letter->id}");
            return response()->json(['error' => 'Unauthorized or expired token'], 403);
        }

        // Ensure file exists
        if (! $letter->generated_file_path || ! Storage::disk('local')->exists($letter->generated_file_path)) {
            $docxPath = $this->docxRenderer->renderSingle($letter);
            $letter->forceFill(['generated_file_path' => $docxPath])->saveQuietly();
        }

        $fullPath = Storage::disk('local')->path($letter->generated_file_path);
        $fileName = ($letter->reference_number ?: "Draft_{$letter->id}") . '.docx';

        return response()->download($fullPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    /**
     * Handle save/autosave callback from OnlyOffice Document Server.
     */
    public function callback(Request $request, Letter $letter): JsonResponse
    {
        $token = (string) $request->query('token');

        // Check query token authorization
        $authorized = $this->onlyOfficeService->verifyAccessToken($token, $letter, 'callback');

        $body = $request->json()->all();

        // Check if OnlyOffice sent a JWT token in header or body
        $jwtToken = $request->bearerToken() ?? ($body['token'] ?? null);
        if ($jwtToken) {
            $jwtPayload = $this->onlyOfficeService->validateJwtToken($jwtToken);
            if ($jwtPayload) {
                $authorized = true;
                // If payload is wrapped in jwt, use inner payload
                if (isset($jwtPayload['payload']) && is_array($jwtPayload['payload'])) {
                    $body = $jwtPayload['payload'];
                } elseif (isset($jwtPayload['status'])) {
                    $body = $jwtPayload;
                }
            }
        }

        if (! $authorized) {
            Log::warning("Unauthorized OnlyOffice callback request for letter {$letter->id}");
            return response()->json(['error' => 1, 'message' => 'Unauthorized callback'], 403);
        }

        // DemoGuard: Prevent saving changes to demo workspace documents
        if ($letter->workspace?->slug === 'demo-workspace' || $letter->workspace?->name === 'Himpunan Mahasiswa Demo') {
            Log::info("OnlyOffice callback for demo letter {$letter->id} ignored safely.");
            return response()->json(['error' => 0]);
        }

        $result = $this->onlyOfficeService->handleSaveCallback($letter, $body);

        return response()->json($result);
    }

    /**
     * Download master template .docx for OnlyOffice Document Server.
     */
    public function downloadTemplate(Request $request, int $templateId): BinaryFileResponse|JsonResponse
    {
        $template = LetterTemplate::withoutGlobalScopes()->findOrFail($templateId);
        $token = (string) $request->query('token');

        if (! $this->onlyOfficeService->verifyTemplateAccessToken($token, $template, 'download')) {
            Log::warning("Unauthorized OnlyOffice template download request for template {$template->id}");

            return response()->json(['error' => 'Unauthorized or expired token'], 403);
        }

        if (! $template->file_path || ! Storage::disk('local')->exists($template->file_path)) {
            return response()->json(['error' => 'Template file not found'], 404);
        }

        $fullPath = Storage::disk('local')->path($template->file_path);
        $fileName = Str::slug($template->name ?: "template-{$template->id}").'.docx';

        return response()->download($fullPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    /**
     * Handle save / forcesave callback from OnlyOffice Document Server for a Master Template.
     */
    public function callbackTemplate(Request $request, int $templateId): JsonResponse
    {
        $template = LetterTemplate::withoutGlobalScopes()->findOrFail($templateId);
        $token = (string) $request->query('token');

        $authorized = $this->onlyOfficeService->verifyTemplateAccessToken($token, $template, 'callback');
        $body = $request->json()->all();

        $jwtToken = $request->bearerToken() ?? ($body['token'] ?? null);
        if ($jwtToken) {
            $jwtPayload = $this->onlyOfficeService->validateJwtToken($jwtToken);
            if ($jwtPayload) {
                $authorized = true;
                if (isset($jwtPayload['payload']) && is_array($jwtPayload['payload'])) {
                    $body = $jwtPayload['payload'];
                } elseif (isset($jwtPayload['status'])) {
                    $body = $jwtPayload;
                }
            }
        }

        if (! $authorized) {
            Log::warning("Unauthorized OnlyOffice template callback request for template {$template->id}");

            return response()->json(['error' => 1, 'message' => 'Unauthorized callback'], 403);
        }

        // DemoGuard: Prevent saving changes to demo templates
        if ($template->workspace?->slug === 'demo-workspace' || $template->workspace?->name === 'Himpunan Mahasiswa Demo') {
            Log::info("OnlyOffice callback for demo template {$template->id} ignored safely.");
            return response()->json(['error' => 0]);
        }

        $result = $this->onlyOfficeService->handleTemplateSaveCallback($template, $body);

        return response()->json($result);
    }

    /**
     * Serve OnlyOffice background plugin config.json for 1-click variable & repeater table insertion.
     */
    public function pluginConfig(): JsonResponse
    {
        $config = [
            'name' => 'SuratinAja Variables Bridge',
            'guid' => OnlyOfficeService::VARIABLE_PLUGIN_GUID,
            'version' => '1.0.0',
            'variations' => [
                [
                    'description' => 'Menyisipkan variabel sistem, variabel kustom, dan tabel dinamis SuratinAja langsung ke posisi kursor.',
                    'url' => 'index.html',
                    'icons' => ['icon.svg'],
                    'isViewer' => false,
                    'isDisplayedInViewer' => false,
                    'EditorsSupport' => ['word'],
                    'isVisual' => false,
                    'isModal' => false,
                    'isInsideMode' => false,
                    'isSystem' => true,
                    'initDataType' => 'none',
                    'isUpdateOleOnResize' => false,
                    'buttons' => [],
                ],
            ],
        ];

        return response()->json($config, 200, $this->pluginCorsHeaders());
    }

    /**
     * Serve OnlyOffice background plugin index.html (same origin as SuratinAja host page).
     */
    public function pluginIndex(): Response
    {
        $ooUrl = rtrim((string) config('suratinaja.onlyoffice.url', 'http://127.0.0.1:8082'), '/');
        $sdkScript = htmlspecialchars($ooUrl.'/sdkjs-plugins/v1/plugins.js', ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>SuratinAja Variables Bridge</title>
    <script type="text/javascript" src="{$sdkScript}"></script>
</head>
<body>
<script>
(function (window) {
    var HOST_ORIGIN = window.location.origin;

    function notifyHostReady() {
        try {
            if (window.top && window.top !== window) {
                window.top.postMessage({
                    source: 'suratinaja-oo-plugin',
                    type: 'ready'
                }, HOST_ORIGIN);
            }
        } catch (e) {}
    }

    function replyAck(sourceWin, origin, id, ok, error) {
        try {
            if (sourceWin) {
                sourceWin.postMessage({
                    source: 'suratinaja-oo-plugin',
                    type: 'insert_ack',
                    id: id,
                    ok: !!ok,
                    error: error || null
                }, origin || HOST_ORIGIN);
            }
        } catch (e) {}
    }

    if (window.Asc && window.Asc.plugin) {
        window.Asc.plugin.init = function () {
            notifyHostReady();
            setTimeout(notifyHostReady, 400);
            setTimeout(notifyHostReady, 1200);
        };
        window.Asc.plugin.button = function () {};
    }

    window.addEventListener('message', function (event) {
        if (event.origin !== HOST_ORIGIN) return;
        var data = event.data;
        if (!data || data.source !== 'suratinaja-host') return;

        if (data.type === 'ping') {
            notifyHostReady();
            return;
        }

        if (data.type === 'insert_text' && typeof data.text === 'string') {
            try {
                window.Asc.plugin.executeMethod('PasteText', [data.text], function () {
                    replyAck(event.source, event.origin, data.id, true);
                });
            } catch (err) {
                replyAck(event.source, event.origin, data.id, false, err && err.message ? err.message : 'PasteText failed');
            }
            return;
        }

        if (data.type === 'insert_table' && Array.isArray(data.headers) && Array.isArray(data.tokens)) {
            try {
                Asc.scope.headers = data.headers;
                Asc.scope.tokens = data.tokens;
                window.Asc.plugin.callCommand(function () {
                    var oDocument = Api.GetDocument();
                    var headers = Asc.scope.headers || [];
                    var tokens = Asc.scope.tokens || [];
                    var colCount = headers.length;
                    if (colCount < 1) return;

                    var oTable = Api.CreateTable(colCount, 2);
                    oTable.SetWidth('percent', 100);
                    oTable.SetTableBorderTop('single', 4, 0, 0, 0, 0);
                    oTable.SetTableBorderBottom('single', 4, 0, 0, 0, 0);
                    oTable.SetTableBorderLeft('single', 4, 0, 0, 0, 0);
                    oTable.SetTableBorderRight('single', 4, 0, 0, 0, 0);
                    oTable.SetTableBorderInsideH('single', 4, 0, 0, 0, 0);
                    oTable.SetTableBorderInsideV('single', 4, 0, 0, 0, 0);
                    oTable.SetTableCellMarginTop(60);
                    oTable.SetTableCellMarginBottom(60);
                    oTable.SetTableCellMarginLeft(100);
                    oTable.SetTableCellMarginRight(100);

                    var headerRow = oTable.GetRow(0);
                    var dataRow = oTable.GetRow(1);

                    for (var i = 0; i < colCount; i++) {
                        var hCell = headerRow.GetCell(i);
                        if (typeof hCell.SetShd === 'function') {
                            hCell.SetShd('clear', 235, 235, 235, false);
                        }
                        var hPara = hCell.GetContent().GetElement(0);
                        hPara.SetJc('center');
                        var hRun = hPara.AddText(String(headers[i] || ''));
                        hRun.SetBold(true);

                        var dCell = dataRow.GetCell(i);
                        var dPara = dCell.GetContent().GetElement(0);
                        if (i === 0) {
                            dPara.SetJc('center');
                        }
                        dPara.AddText(String(tokens[i] || ''));
                    }

                    oDocument.InsertContent([oTable], true);
                }, false, true, function () {
                    replyAck(event.source, event.origin, data.id, true);
                });
            } catch (err) {
                replyAck(event.source, event.origin, data.id, false, err && err.message ? err.message : 'InsertTable failed');
            }
        }
    });
})(window);
</script>
</body>
</html>
HTML;

        return response($html, 200, array_merge($this->pluginCorsHeaders(), [
            'Content-Type' => 'text/html; charset=utf-8',
        ]));
    }

    /**
     * Serve lightweight SVG icon for OnlyOffice plugin manifest.
     */
    public function pluginIcon(): Response
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>';

        return response($svg, 200, array_merge($this->pluginCorsHeaders(), [
            'Content-Type' => 'image/svg+xml',
        ]));
    }

    public function downloadEnvelopeTemplate(Request $request, int $templateId): BinaryFileResponse|JsonResponse
    {
        $template = \App\Models\EnvelopeTemplate::findOrFail($templateId);
        $token = (string) $request->query('token');

        if (! $this->onlyOfficeService->verifyEnvelopeAccessToken($token, $template, 'download')) {
            Log::warning("Unauthorized OnlyOffice envelope download request for template {$template->id}");

            return response()->json(['error' => 'Unauthorized or expired token'], 403);
        }

        if (! $template->file_path || ! Storage::disk('local')->exists($template->file_path)) {
            return response()->json(['error' => 'Template file not found'], 404);
        }

        $fullPath = Storage::disk('local')->path($template->file_path);
        $fileName = Str::slug($template->name ?: "envelope-{$template->id}").'.docx';

        return response()->download($fullPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    public function callbackEnvelopeTemplate(Request $request, int $templateId): JsonResponse
    {
        $template = \App\Models\EnvelopeTemplate::findOrFail($templateId);
        $token = (string) $request->query('token');

        $authorized = $this->onlyOfficeService->verifyEnvelopeAccessToken($token, $template, 'callback');
        $body = $request->json()->all();

        $jwtToken = $request->bearerToken() ?? ($body['token'] ?? null);
        if ($jwtToken) {
            $jwtPayload = $this->onlyOfficeService->validateJwtToken($jwtToken);
            if ($jwtPayload) {
                $authorized = true;
                if (isset($jwtPayload['payload']) && is_array($jwtPayload['payload'])) {
                    $body = $jwtPayload['payload'];
                } elseif (isset($jwtPayload['status'])) {
                    $body = $jwtPayload;
                }
            }
        }

        if (! $authorized) {
            Log::warning("Unauthorized OnlyOffice envelope callback request for template {$template->id}");

            return response()->json(['error' => 1, 'message' => 'Unauthorized callback'], 403);
        }

        // DemoGuard: Prevent saving changes to demo envelope templates
        if ($template->workspace?->slug === 'demo-workspace' || $template->workspace?->name === 'Himpunan Mahasiswa Demo') {
            Log::info("OnlyOffice callback for demo envelope template {$template->id} ignored safely.");
            return response()->json(['error' => 0]);
        }

        $result = $this->onlyOfficeService->handleEnvelopeSaveCallback($template, $body);

        return response()->json($result);
    }

    public function downloadKopMaster(Request $request, int $workspaceId): BinaryFileResponse|JsonResponse
    {
        $workspace = \App\Models\Workspace::findOrFail($workspaceId);
        $token = (string) $request->query('token');

        if (! $this->onlyOfficeService->verifyKopMasterAccessToken($token, $workspace, 'download')) {
            Log::warning("Unauthorized OnlyOffice kop master download request for workspace {$workspace->id}");

            return response()->json(['error' => 'Unauthorized or expired token'], 403);
        }

        if (! $workspace->kop_master_docx_path || ! Storage::disk('local')->exists($workspace->kop_master_docx_path)) {
            return response()->json(['error' => 'Kop master file not found'], 404);
        }

        $fullPath = Storage::disk('local')->path($workspace->kop_master_docx_path);
        $fileName = 'master-kop-'.Str::slug($workspace->name).'.docx';

        return response()->download($fullPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    public function callbackKopMaster(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = \App\Models\Workspace::findOrFail($workspaceId);
        $token = (string) $request->query('token');

        $authorized = $this->onlyOfficeService->verifyKopMasterAccessToken($token, $workspace, 'callback');
        $body = $request->json()->all();

        $jwtToken = $request->bearerToken() ?? ($body['token'] ?? null);
        if ($jwtToken) {
            $jwtPayload = $this->onlyOfficeService->validateJwtToken($jwtToken);
            if ($jwtPayload) {
                $authorized = true;
                if (isset($jwtPayload['payload']) && is_array($jwtPayload['payload'])) {
                    $body = $jwtPayload['payload'];
                } elseif (isset($jwtPayload['status'])) {
                    $body = $jwtPayload;
                }
            }
        }

        if (! $authorized) {
            Log::warning("Unauthorized OnlyOffice kop master callback request for workspace {$workspace->id}");

            return response()->json(['error' => 1, 'message' => 'Unauthorized callback'], 403);
        }

        // DemoGuard: Prevent saving changes to demo workspace kop master
        if ($workspace->slug === 'demo-workspace' || $workspace->name === 'Himpunan Mahasiswa Demo') {
            Log::info("OnlyOffice callback for demo kop master in workspace {$workspace->id} ignored safely.");
            return response()->json(['error' => 0]);
        }

        $result = $this->onlyOfficeService->handleKopMasterSaveCallback($workspace, $body);

        return response()->json($result);
    }

    public function rawDownload(Request $request): BinaryFileResponse|JsonResponse
    {
        $relPath = (string) $request->query('path');
        $token = (string) $request->query('token');

        $expectedToken = hash_hmac('sha256', $relPath, config('app.key'));
        if (! hash_equals($expectedToken, $token) || ! Storage::disk('local')->exists($relPath)) {
            return response()->json(['error' => 'Unauthorized or file missing'], 403);
        }

        $fullPath = Storage::disk('local')->path($relPath);

        return response()->download($fullPath, basename($relPath), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function pluginCorsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => '*',
            'Cache-Control' => 'no-cache, must-revalidate',
        ];
    }
}
