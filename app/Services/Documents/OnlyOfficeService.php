<?php

namespace App\Services\Documents;

use App\Enums\LetterStatus;
use App\Exceptions\DomainException;
use App\Models\EnvelopeTemplate;
use App\Models\Letter;
use App\Models\LetterActivity;
use App\Models\LetterTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Templates\TemplateParser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class OnlyOfficeService
{
    public const VARIABLE_PLUGIN_GUID = 'asc.{9DC93CDB-B576-4F07-B55E-FCC9C48DD007}';

    public function __construct(
        private readonly DocxRenderer $docxRenderer,
        private readonly TemplateParser $templateParser,
    ) {}

    /**
     * Build the full DocsAPI configuration array for OnlyOffice DocEditor.
     */
    public function buildEditorConfig(Letter $letter, ?User $user = null): array
    {
        // Ensure the DOCX file exists on disk
        if (! $letter->generated_file_path || ! Storage::disk('local')->exists($letter->generated_file_path)) {
            $docxPath = $this->docxRenderer->renderSingle($letter);
            $letter->forceFill(['generated_file_path' => $docxPath])->saveQuietly();
        }

        $canEdit = ($letter->status === LetterStatus::Draft || $letter->status === LetterStatus::MenungguTtd);

        $downloadToken = $this->generateAccessToken($letter, 'download');
        $callbackToken = $this->generateAccessToken($letter, 'callback');

        $baseUrl = $this->getAppServiceBaseUrl();
        $downloadUrl = rtrim($baseUrl, '/') . "/api/onlyoffice/download/{$letter->id}?token={$downloadToken}";
        $callbackUrl = rtrim($baseUrl, '/') . "/api/onlyoffice/callback/{$letter->id}?token={$callbackToken}";

        // Key must change whenever the document is updated so OnlyOffice reloads it
        $docKey = 'letter_' . $letter->id . '_v' . ($letter->version ?? 1) . '_' . ($letter->updated_at?->timestamp ?? time());

        $docTitle = ($letter->reference_number ?: 'Draft_' . $letter->id);
        if (! str_ends_with(strtolower($docTitle), '.docx')) {
            $docTitle .= '.docx';
        }

        $config = [
            'type' => 'desktop',
            'width' => '100%',
            'height' => '100%',
            'documentType' => 'word',
            'document' => [
                'title' => $docTitle,
                'url' => $downloadUrl,
                'fileType' => 'docx',
                'key' => $docKey,
                'permissions' => [
                    'edit' => $canEdit,
                    'download' => true,
                    'print' => true,
                    'review' => false,
                    'comment' => false,
                    'copy' => true,
                ],
            ],
            'editorConfig' => [
                'mode' => $canEdit ? 'edit' : 'view',
                'lang' => 'id',
                'callbackUrl' => $callbackUrl,
                'user' => [
                    'id' => (string) ($user ? $user->id : '1'),
                    'name' => $user ? $user->name : 'Pengguna',
                ],
                'customization' => [
                    'autosave' => true,
                    'forcesave' => true,
                    'compactHeader' => false,
                    'toolbarNoTabs' => false,
                    'help' => false,
                    'feedback' => false,
                    'uiTheme' => 'theme-light',
                    'unit' => 'cm',
                ],
            ],
        ];

        // Sign configuration with JWT token for OnlyOffice
        $jwtSecret = config('suratinaja.onlyoffice.jwt_secret');
        if (! empty($jwtSecret)) {
            $config['token'] = $this->generateJwtToken($config, $jwtSecret);
        }

        return $config;
    }

    /**
     * Generate standard HMAC-SHA256 JWT Token.
     */
    public function generateJwtToken(array $payload, ?string $secret = null): string
    {
        $secret = $secret ?: config('suratinaja.onlyoffice.jwt_secret', 'secret');

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $base64UrlHeader = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $base64UrlPayload = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));

        $signature = hash_hmac('sha256', "{$base64UrlHeader}.{$base64UrlPayload}", $secret, true);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        return "{$base64UrlHeader}.{$base64UrlPayload}.{$base64UrlSignature}";
    }

    /**
     * Validate JWT Token and return payload if valid.
     */
    public function validateJwtToken(string $token, ?string $secret = null): ?array
    {
        $secret = $secret ?: config('suratinaja.onlyoffice.jwt_secret', 'secret');
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $expectedSig = $this->base64UrlEncode(hash_hmac('sha256', "{$headerB64}.{$payloadB64}", $secret, true));

        if (! hash_equals($expectedSig, $signatureB64)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($payloadB64), true);

        return is_array($payload) ? $payload : null;
    }

    /**
     * Generate an expiring access token for internal route authorization.
     */
    public function generateAccessToken(Letter $letter, string $action, int $ttlSeconds = 86400): string
    {
        $secret = config('app.key', 'suratinaja-key');
        $expires = time() + $ttlSeconds;
        $data = "{$letter->id}|{$action}|{$expires}";
        $hash = hash_hmac('sha256', $data, $secret);

        return $this->base64UrlEncode("{$data}|{$hash}");
    }

    /**
     * Verify the expiring access token.
     */
    public function verifyAccessToken(string $token, Letter $letter, string $action): bool
    {
        $secret = config('app.key', 'suratinaja-key');
        $decoded = $this->base64UrlDecode($token);
        $parts = explode('|', $decoded);

        if (count($parts) !== 4) {
            return false;
        }

        [$letterId, $tokenAction, $expires, $hash] = $parts;

        if ((int) $letterId !== (int) $letter->id || $tokenAction !== $action) {
            return false;
        }

        if (time() > (int) $expires) {
            return false;
        }

        $expectedHash = hash_hmac('sha256', "{$letterId}|{$tokenAction}|{$expires}", $secret);

        return hash_equals($expectedHash, $hash);
    }

    /**
     * Handle the save callback from OnlyOffice Document Server.
     * Status codes from OnlyOffice DocsAPI:
     * 1 - document being edited
     * 2 - document ready for saving
     * 3 - document saving error
     * 4 - document closed without changes
     * 6 - document being saved (forcesave)
     * 7 - error occurred while forcesaving
     */
    public function handleSaveCallback(Letter $letter, array $payload, ?User $actor = null): array
    {
        $status = (int) ($payload['status'] ?? 0);

        // Status 2 (ready for saving) or Status 6 (forcesave/autosave triggered)
        if ($status === 2 || $status === 6) {
            $downloadUrl = $payload['url'] ?? null;

            if (! $downloadUrl) {
                Log::warning("OnlyOffice callback missing file url for letter {$letter->id}", $payload);
                return ['error' => 1, 'message' => 'Missing download url'];
            }

            // Adjust internal docker url if needed
            $downloadUrl = $this->normalizeInternalDownloadUrl($downloadUrl);

            try {
                $response = Http::timeout(60)->withoutVerifying()->get($downloadUrl);

                if (! $response->successful()) {
                    Log::error("Failed to download updated docx from OnlyOffice for letter {$letter->id}: HTTP {$response->status()}");
                    return ['error' => 1, 'message' => 'Failed to download modified file'];
                }

                $content = $response->body();

                // Save or overwrite DOCX on disk
                $docxPath = $letter->generated_file_path ?: "generated/{$letter->workspace_id}/letter-{$letter->id}.docx";
                Storage::disk('local')->put($docxPath, $content);

                // Invalidate old preview PDF if exists
                if ($letter->preview_pdf_path && Storage::disk('local')->exists($letter->preview_pdf_path)) {
                    Storage::disk('local')->delete($letter->preview_pdf_path);
                }

                // Increment version, touch updated_at, and reset preview_pdf_path
                $letter->forceFill([
                    'generated_file_path' => $docxPath,
                    'preview_pdf_path' => null,
                    'version' => ($letter->version ?? 1) + 1,
                    'updated_at' => now(),
                ])->saveQuietly();

                // Re-render PDF preview immediately via OnlyOffice ConvertService (100% WYSIWYG)
                try {
                    $this->convertToPdf($letter, force: true);
                } catch (\Throwable $pdfError) {
                    Log::warning("Post-save PDF conversion background warning for letter {$letter->id}: {$pdfError->getMessage()}");
                }

                // Record audit log activity
                LetterActivity::create([
                    'letter_id' => $letter->id,
                    'workspace_id' => $letter->workspace_id,
                    'actor_id' => $actor?->id ?? $letter->created_by,
                    'action' => 'edited',
                    'payload' => [
                        'source' => 'onlyoffice',
                        'version' => $letter->version,
                        'description' => 'Dokumen .docx diperbarui melalui Editor Word (OnlyOffice)',
                    ],
                    'created_at' => now(),
                ]);

                Log::info("Successfully saved letter {$letter->id} from OnlyOffice callback (v{$letter->version}).");

                return ['error' => 0];
            } catch (Throwable $e) {
                Log::error("Exception handling OnlyOffice save callback for letter {$letter->id}: {$e->getMessage()}");
                return ['error' => 1, 'message' => $e->getMessage()];
            }
        }

        // Other statuses (1, 4, etc.) are normal notifications, acknowledge with error = 0
        return ['error' => 0];
    }

    /**
     * Resolves the server-to-server URL of OnlyOffice Document Server.
     * Prefers internal container URL (e.g. http://onlyoffice:80) for Docker networks,
     * falling back to the public URL.
     */
    private function getServerServiceUrl(): string
    {
        $internal = config('suratinaja.onlyoffice.internal_url');
        if (! empty($internal)) {
            return rtrim($internal, '/');
        }

        return rtrim((string) config('suratinaja.onlyoffice.url', 'http://127.0.0.1:8082'), '/');
    }

    /**
     * Resolves the base URL of this Laravel app accessible by the OnlyOffice container.
     */
    private function getAppServiceBaseUrl(): string
    {
        $appServiceUrl = config('suratinaja.onlyoffice.app_service_url');
        if (! empty($appServiceUrl)) {
            return $appServiceUrl;
        }

        $appUrl = config('app.url', 'http://localhost');
        // Ensure HTTPS for local dev domain suratinaja.test to avoid 301/302 redirects converting POST to GET
        if (str_contains($appUrl, 'suratinaja.test') && str_starts_with($appUrl, 'http://')) {
            $appUrl = str_replace('http://', 'https://', $appUrl);
        }

        return $appUrl;
    }

    /**
     * If OnlyOffice returns an internal URL or localhost port, rewrite to internal container name if configured.
     */
    private function normalizeInternalDownloadUrl(string $url): string
    {
        $internalOnlyOffice = config('suratinaja.onlyoffice.internal_url');
        if (empty($internalOnlyOffice)) {
            return $url;
        }

        $publicUrl = config('suratinaja.onlyoffice.url', 'http://127.0.0.1:8082');
        $publicHost = parse_url($publicUrl, PHP_URL_HOST);
        $urlHost = parse_url($url, PHP_URL_HOST);

        // If the URL matches OnlyOffice public host or localhost, redirect via internal endpoint
        if ($urlHost === $publicHost || $urlHost === 'office-suratinaja.test' || $urlHost === 'localhost' || $urlHost === '127.0.0.1') {
            $parsedPath = parse_url($url, PHP_URL_PATH);
            $parsedQuery = parse_url($url, PHP_URL_QUERY);
            $newUrl = rtrim($internalOnlyOffice, '/') . $parsedPath;
            if ($parsedQuery) {
                $newUrl .= '?' . $parsedQuery;
            }

            return $newUrl;
        }

        return $url;
    }

    /**
     * Convert a letter's .docx file to .pdf using OnlyOffice Document Server Conversion API.
     * This achieves 100% WYSIWYG parity between the online editor and exported PDF.
     *
     * @return array{ok: bool, path?: string, message?: string}
     */
    public function convertToPdf(Letter $letter, bool $force = false): array
    {
        $relativePdf = "previews/{$letter->workspace_id}/letter-{$letter->id}-v" . ($letter->version ?? 1) . '.pdf';

        if (! $force && $letter->preview_pdf_path && Storage::disk('local')->exists($letter->preview_pdf_path)) {
            return ['ok' => true, 'path' => $letter->preview_pdf_path];
        }

        if (! $letter->generated_file_path || ! Storage::disk('local')->exists($letter->generated_file_path)) {
            $docxPath = $this->docxRenderer->renderSingle($letter);
            $letter->forceFill(['generated_file_path' => $docxPath])->saveQuietly();
        }

        $downloadToken = $this->generateAccessToken($letter, 'download');
        $baseUrl = $this->getAppServiceBaseUrl();
        $downloadUrl = rtrim($baseUrl, '/') . "/api/onlyoffice/download/{$letter->id}?token={$downloadToken}";

        $mtime = Storage::disk('local')->exists($letter->generated_file_path)
            ? Storage::disk('local')->lastModified($letter->generated_file_path)
            : time();
        $key = 'conv_' . $letter->id . '_' . $mtime . '_v' . ($letter->version ?? 1);

        $docTitle = ($letter->reference_number ?: 'Draft_' . $letter->id);
        if (! str_ends_with(strtolower($docTitle), '.docx')) {
            $docTitle .= '.docx';
        }

        $payload = [
            'async' => false,
            'filetype' => 'docx',
            'key' => $key,
            'outputtype' => 'pdf',
            'title' => $docTitle,
            'url' => $downloadUrl,
        ];

        $jwtSecret = config('suratinaja.onlyoffice.jwt_secret');
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if (! empty($jwtSecret)) {
            $jwtToken = $this->generateJwtToken($payload, $jwtSecret);
            $payload['token'] = $jwtToken;
            $headers['Authorization'] = 'Bearer ' . $jwtToken;
        }

        $convertUrl = $this->getServerServiceUrl() . '/ConvertService.ashx';

        try {
            $response = Http::timeout(30)
                ->withoutVerifying()
                ->withHeaders($headers)
                ->post($convertUrl, $payload);

            if (! $response->successful()) {
                Log::error("OnlyOffice ConvertService HTTP error for letter {$letter->id}: {$response->status()}", [
                    'body' => $response->body(),
                    'payload' => $payload,
                ]);
                return ['ok' => false, 'message' => "OnlyOffice ConvertService error: HTTP {$response->status()}"];
            }

            $data = $response->json();

            if (isset($data['error']) && (int) $data['error'] !== 0) {
                Log::error("OnlyOffice ConvertService returned error code {$data['error']} for letter {$letter->id}", [
                    'data' => $data,
                    'payload' => $payload,
                ]);
                return ['ok' => false, 'message' => "OnlyOffice ConvertService error code: {$data['error']}"];
            }

            $fileUrl = $data['fileUrl'] ?? null;
            if (! $fileUrl) {
                Log::error("OnlyOffice ConvertService missing fileUrl for letter {$letter->id}", ['data' => $data]);
                return ['ok' => false, 'message' => 'OnlyOffice ConvertService did not return fileUrl'];
            }

            // Normalize internal docker URL if needed
            $fileUrl = $this->normalizeInternalDownloadUrl($fileUrl);

            $downloadRes = Http::timeout(60)->withoutVerifying()->get($fileUrl);
            if (! $downloadRes->successful()) {
                Log::error("Failed to download converted PDF from OnlyOffice for letter {$letter->id}: HTTP {$downloadRes->status()}");
                return ['ok' => false, 'message' => 'Failed to download converted PDF from OnlyOffice'];
            }

            Storage::disk('local')->put($relativePdf, $downloadRes->body());

            $letter->forceFill(['preview_pdf_path' => $relativePdf])->saveQuietly();

            Log::info("Successfully converted letter {$letter->id} to PDF via OnlyOffice ConvertService.");

            return ['ok' => true, 'path' => $relativePdf];
        } catch (Throwable $e) {
            Log::error("Exception in OnlyOffice ConvertService for letter {$letter->id}: {$e->getMessage()}", [
                'exception' => $e,
            ]);
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send forcesave command to OnlyOffice Document Server CommandService.
     *
     * @return array{ok: bool, error: int, message: string}
     */
    public function forceSave(Letter $letter, ?string $key = null): array
    {
        $docKey = $key ?: ('letter_' . $letter->id . '_v' . ($letter->version ?? 1) . '_' . ($letter->updated_at?->timestamp ?? time()));

        $payload = [
            'c' => 'forcesave',
            'key' => $docKey,
        ];

        $jwtSecret = config('suratinaja.onlyoffice.jwt_secret');
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if (! empty($jwtSecret)) {
            $jwtToken = $this->generateJwtToken($payload, $jwtSecret);
            $payload['token'] = $jwtToken;
            $headers['Authorization'] = 'Bearer ' . $jwtToken;
        }

        $commandUrl = $this->getServerServiceUrl() . '/coauthoring/CommandService.ashx';

        try {
            $response = Http::timeout(10)
                ->withoutVerifying()
                ->withHeaders($headers)
                ->post($commandUrl, $payload);

            if (! $response->successful()) {
                Log::warning("OnlyOffice CommandService HTTP error for letter {$letter->id}: {$response->status()}", [
                    'body' => $response->body(),
                ]);
                return ['ok' => false, 'error' => -1, 'message' => "HTTP {$response->status()}"];
            }

            $data = $response->json() ?? [];
            $errorCode = (int) ($data['error'] ?? -1);

            // error 0: Success (forcesave dispatched)
            // error 4: No document with such key found (no unsaved edits in OnlyOffice memory)
            $isOk = ($errorCode === 0 || $errorCode === 4);
            $msg = match ($errorCode) {
                0 => 'Forcesave command dispatched successfully',
                4 => 'No document in active editing session',
                default => 'OnlyOffice CommandService error code: ' . $errorCode,
            };

            return ['ok' => $isOk, 'error' => $errorCode, 'message' => $msg];
        } catch (Throwable $e) {
            Log::warning("Exception calling OnlyOffice CommandService for letter {$letter->id}: {$e->getMessage()}");
            return ['ok' => false, 'error' => -1, 'message' => $e->getMessage()];
        }
    }

    /**
     * Trigger forcesave and wait briefly for callback to persist if an editing session was active,
     * then ensure PDF is converted from the latest .docx.
     *
     * @return array{ok: bool, path?: string, message?: string}
     */
    public function forceSaveAndSync(Letter $letter, int $maxWaitMs = 1500, ?string $key = null): array
    {
        $prevUpdatedAt = $letter->updated_at?->timestamp;
        $fsResult = $this->forceSave($letter, $key);

        // If forcesave was dispatched (error === 0), wait briefly for callback to hit
        if ($fsResult['error'] === 0) {
            $elapsed = 0;
            $interval = 100 * 1000; // 100ms in microseconds
            $maxMicroseconds = $maxWaitMs * 1000;

            while ($elapsed < $maxMicroseconds) {
                usleep($interval);
                $elapsed += $interval;
                $letter->refresh();

                if ($letter->updated_at?->timestamp !== $prevUpdatedAt) {
                    // Callback has completed and touched updated_at!
                    break;
                }
            }
        }

        return $this->convertToPdf($letter, force: true);
    }

    /**
     * Build the document key for a LetterTemplate so OnlyOffice reloads when updated.
     */
    public function getTemplateDocumentKey(LetterTemplate $template): string
    {
        return 'template_' . $template->id . '_v' . ($template->version ?? 1) . '_' . ($template->updated_at?->timestamp ?? time());
    }

    /**
     * Build the full DocsAPI configuration array for editing/viewing a Master LetterTemplate in OnlyOffice.
     */
    public function buildTemplateEditorConfig(LetterTemplate $template, ?User $user = null, bool $canEdit = true): array
    {
        if (! $template->file_path || ! Storage::disk('local')->exists($template->file_path)) {
            throw DomainException::templateInvalid('File master template .docx tidak ditemukan di storage.');
        }

        $downloadToken = $this->generateTemplateAccessToken($template, 'download');
        $callbackToken = $this->generateTemplateAccessToken($template, 'callback');

        $baseUrl = $this->getAppServiceBaseUrl();
        $downloadUrl = rtrim($baseUrl, '/') . "/api/onlyoffice/templates/download/{$template->id}?token={$downloadToken}";
        $callbackUrl = rtrim($baseUrl, '/') . "/api/onlyoffice/templates/callback/{$template->id}?token={$callbackToken}";

        $docKey = $this->getTemplateDocumentKey($template);
        $docTitle = trim((string) $template->name) ?: "Template_{$template->id}";
        if (! str_ends_with(strtolower($docTitle), '.docx')) {
            $docTitle .= '.docx';
        }

        $editorConfig = [
            'mode' => $canEdit ? 'edit' : 'view',
            'lang' => 'id',
            'callbackUrl' => $callbackUrl,
            'user' => [
                'id' => (string) ($user ? $user->id : '1'),
                'name' => $user ? $user->name : 'Pengguna',
            ],
            'customization' => [
                'autosave' => true,
                'forcesave' => true,
                'compactHeader' => false,
                'toolbarNoTabs' => false,
                'help' => false,
                'feedback' => false,
                'uiTheme' => 'theme-light',
                'unit' => 'cm',
            ],
        ];

        // Attach the native background variable-insertion plugin when user has edit permission
        if ($canEdit) {
            $publicAppUrl = $this->getPublicBrowserBaseUrl();
            $editorConfig['plugins'] = [
                'autostart' => [self::VARIABLE_PLUGIN_GUID],
                'pluginsData' => [
                    rtrim($publicAppUrl, '/') . '/api/onlyoffice/plugins/suratinaja-variables/config.json',
                ],
            ];
        }

        $config = [
            'type' => 'desktop',
            'width' => '100%',
            'height' => '100%',
            'documentType' => 'word',
            'document' => [
                'title' => $docTitle,
                'url' => $downloadUrl,
                'fileType' => 'docx',
                'key' => $docKey,
                'permissions' => [
                    'edit' => $canEdit,
                    'download' => true,
                    'print' => true,
                    'review' => false,
                    'comment' => false,
                    'copy' => true,
                ],
            ],
            'editorConfig' => $editorConfig,
        ];

        $jwtSecret = config('suratinaja.onlyoffice.jwt_secret');
        if (! empty($jwtSecret)) {
            $config['token'] = $this->generateJwtToken($config, $jwtSecret);
        }

        return $config;
    }

    /**
     * Generate a template-scoped expiring access token (distinct "tpl|" namespace from letter tokens).
     */
    public function generateTemplateAccessToken(LetterTemplate $template, string $action, int $ttlSeconds = 86400): string
    {
        $secret = config('app.key', 'suratinaja-key');
        $expires = time() + $ttlSeconds;
        $data = "tpl|{$template->id}|{$action}|{$expires}";
        $hash = hash_hmac('sha256', $data, $secret);

        return $this->base64UrlEncode("{$data}|{$hash}");
    }

    /**
     * Verify a template-scoped expiring access token.
     */
    public function verifyTemplateAccessToken(string $token, LetterTemplate $template, string $action): bool
    {
        $secret = config('app.key', 'suratinaja-key');
        $decoded = $this->base64UrlDecode($token);
        $parts = explode('|', $decoded);

        if (count($parts) !== 5) {
            return false;
        }

        [$prefix, $templateId, $tokenAction, $expires, $hash] = $parts;

        if ($prefix !== 'tpl' || (int) $templateId !== (int) $template->id || $tokenAction !== $action) {
            return false;
        }

        if (time() > (int) $expires) {
            return false;
        }

        $expectedHash = hash_hmac('sha256', "{$prefix}|{$templateId}|{$tokenAction}|{$expires}", $secret);

        return hash_equals($expectedHash, $hash);
    }

    /**
     * Handle the save / forcesave callback from OnlyOffice Document Server for a Master Template.
     * Automatically heals split XML variable runs and refreshes `variables_schema`.
     */
    public function handleTemplateSaveCallback(LetterTemplate $template, array $payload): array
    {
        $status = (int) ($payload['status'] ?? 0);

        if ($status === 2 || $status === 6) {
            $downloadUrl = $payload['url'] ?? null;

            if (! $downloadUrl) {
                Log::warning("OnlyOffice template callback missing file url for template {$template->id}", $payload);

                return ['error' => 1, 'message' => 'Missing download url'];
            }

            $downloadUrl = $this->normalizeInternalDownloadUrl($downloadUrl);
            $tmpFile = tempnam(sys_get_temp_dir(), 'sa_oo_tpl_') . '.docx';

            try {
                $response = Http::timeout(60)->withoutVerifying()->get($downloadUrl);

                if (! $response->successful()) {
                    Log::error("Failed to download updated template docx from OnlyOffice for template {$template->id}: HTTP {$response->status()}");

                    return ['error' => 1, 'message' => 'Failed to download modified template file'];
                }

                file_put_contents($tmpFile, $response->body());

                // Auto-heal split runs / proofErr tags and extract the latest variables schema.
                // If user saved a work-in-progress template without variables yet, keep saving cleanly without rejecting OnlyOffice.
                try {
                    $schema = $this->templateParser->parse($tmpFile);
                } catch (DomainException $e) {
                    Log::info("Template {$template->id} saved via OnlyOffice with zero detected variables (WIP): {$e->getMessage()}");
                    $schema = [];
                }

                $targetPath = $template->file_path ?: "templates/workspaces/{$template->workspace_id}/template-{$template->id}.docx";
                Storage::disk('local')->put($targetPath, (string) file_get_contents($tmpFile));

                $template->forceFill([
                    'file_path' => $targetPath,
                    'variables_schema' => $schema,
                    'version' => ((int) ($template->version ?? 1)) + 1,
                    'updated_at' => now(),
                ])->saveQuietly();

                Log::info("Successfully saved template {$template->id} from OnlyOffice callback (v{$template->version}, " . count($schema) . ' variables).');

                return ['error' => 0];
            } catch (Throwable $e) {
                Log::error("Exception handling OnlyOffice save callback for template {$template->id}: {$e->getMessage()}");

                return ['error' => 1, 'message' => $e->getMessage()];
            } finally {
                @unlink($tmpFile);
            }
        }

        return ['error' => 0];
    }

    /**
     * Send forcesave command to OnlyOffice Document Server for an active template editing session.
     *
     * @return array{ok: bool, error: int, message: string}
     */
    public function forceSaveTemplate(LetterTemplate $template, ?string $key = null): array
    {
        $docKey = $key ?: $this->getTemplateDocumentKey($template);

        $payload = [
            'c' => 'forcesave',
            'key' => $docKey,
        ];

        $jwtSecret = config('suratinaja.onlyoffice.jwt_secret');
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if (! empty($jwtSecret)) {
            $jwtToken = $this->generateJwtToken($payload, $jwtSecret);
            $payload['token'] = $jwtToken;
            $headers['Authorization'] = 'Bearer ' . $jwtToken;
        }

        $commandUrl = $this->getServerServiceUrl() . '/coauthoring/CommandService.ashx';

        try {
            $response = Http::timeout(10)
                ->withoutVerifying()
                ->withHeaders($headers)
                ->post($commandUrl, $payload);

            if (! $response->successful()) {
                return ['ok' => false, 'error' => -1, 'message' => "HTTP {$response->status()}"];
            }

            $data = $response->json() ?? [];
            $errorCode = (int) ($data['error'] ?? -1);
            $isOk = ($errorCode === 0 || $errorCode === 4);

            return [
                'ok' => $isOk,
                'error' => $errorCode,
                'message' => match ($errorCode) {
                    0 => 'Forcesave command dispatched successfully',
                    4 => 'No active unsaved changes in editor',
                    default => 'OnlyOffice CommandService error code: ' . $errorCode,
                },
            ];
        } catch (Throwable $e) {
            Log::warning("Exception calling OnlyOffice CommandService for template {$template->id}: {$e->getMessage()}");

            return ['ok' => false, 'error' => -1, 'message' => $e->getMessage()];
        }
    }

    /**
     * Trigger forcesave on OnlyOffice, wait briefly for callback persistence, and re-parse template variables from disk.
     *
     * @return array{ok: bool, variables: array<int, array<string, mixed>>, has_variables: bool, version: int}
     */
    public function forceSaveAndSyncTemplate(LetterTemplate $template, int $maxWaitMs = 1500, ?string $key = null): array
    {
        $prevUpdatedAt = $template->updated_at?->timestamp;
        $fsResult = $this->forceSaveTemplate($template, $key);

        if ($fsResult['error'] === 0) {
            $elapsed = 0;
            $interval = 100 * 1000;
            $maxMicroseconds = $maxWaitMs * 1000;

            while ($elapsed < $maxMicroseconds) {
                usleep($interval);
                $elapsed += $interval;
                $template->refresh();

                if ($template->updated_at?->timestamp !== $prevUpdatedAt) {
                    break;
                }
            }
        }

        return $this->syncTemplateVariablesFromDisk($template);
    }

    /**
     * Re-scans the template .docx file on disk and updates its `variables_schema`.
     *
     * @return array{ok: bool, variables: array<int, array<string, mixed>>, has_variables: bool, version: int}
     */
    public function syncTemplateVariablesFromDisk(LetterTemplate $template): array
    {
        $template->refresh();

        if (! $template->file_path || ! Storage::disk('local')->exists($template->file_path)) {
            return [
                'ok' => false,
                'variables' => [],
                'has_variables' => false,
                'version' => (int) ($template->version ?? 1),
            ];
        }

        $absPath = Storage::disk('local')->path($template->file_path);

        try {
            $schema = $this->templateParser->parse($absPath);
        } catch (DomainException) {
            $schema = [];
        }

        $template->forceFill(['variables_schema' => $schema])->saveQuietly();

        return [
            'ok' => true,
            'variables' => $schema,
            'has_variables' => count($schema) > 0,
            'version' => (int) ($template->version ?? 1),
        ];
    }

    public function getEnvelopeDocumentKey(EnvelopeTemplate $template): string
    {
        return 'envelope_' . $template->id . '_v' . ($template->version ?? 1) . '_' . ($template->updated_at?->timestamp ?? time());
    }

    public function generateEnvelopeAccessToken(EnvelopeTemplate $template, string $action, int $ttlSeconds = 86400): string
    {
        $payload = [
            'type' => 'envelope_template',
            'id' => $template->id,
            'action' => $action,
            'exp' => time() + $ttlSeconds,
        ];
        $json = json_encode($payload);
        $sig = hash_hmac('sha256', $json, config('app.key'));

        return $this->base64UrlEncode($json) . '.' . $this->base64UrlEncode($sig);
    }

    public function verifyEnvelopeAccessToken(string $token, EnvelopeTemplate $template, string $action): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }

        $json = $this->base64UrlDecode($parts[0]);
        $sig = $this->base64UrlDecode($parts[1]);

        $expectedSig = hash_hmac('sha256', $json, config('app.key'));
        if (! hash_equals($expectedSig, $sig)) {
            return false;
        }

        $data = json_decode($json, true);
        if (! is_array($data)) {
            return false;
        }

        if (($data['type'] ?? '') !== 'envelope_template' || (int) ($data['id'] ?? 0) !== (int) $template->id || ($data['action'] ?? '') !== $action) {
            return false;
        }

        return ($data['exp'] ?? 0) >= time();
    }

    public function buildEnvelopeEditorConfig(EnvelopeTemplate $template, ?User $user = null, bool $canEdit = true): array
    {
        if (! $template->file_path || ! Storage::disk('local')->exists($template->file_path)) {
            throw DomainException::templateInvalid('File master template amplop .docx tidak ditemukan di storage.');
        }

        $downloadToken = $this->generateEnvelopeAccessToken($template, 'download');
        $callbackToken = $this->generateEnvelopeAccessToken($template, 'callback');

        $baseUrl = $this->getAppServiceBaseUrl();
        $downloadUrl = rtrim($baseUrl, '/') . "/api/onlyoffice/envelopes/download/{$template->id}?token={$downloadToken}";
        $callbackUrl = rtrim($baseUrl, '/') . "/api/onlyoffice/envelopes/callback/{$template->id}?token={$callbackToken}";

        $docKey = $this->getEnvelopeDocumentKey($template);
        $docTitle = trim((string) $template->name) ?: "Amplop_{$template->id}";
        if (! str_ends_with(strtolower($docTitle), '.docx')) {
            $docTitle .= '.docx';
        }

        $editorConfig = [
            'mode' => $canEdit ? 'edit' : 'view',
            'lang' => 'id',
            'callbackUrl' => $callbackUrl,
            'user' => [
                'id' => (string) ($user ? $user->id : '1'),
                'name' => $user ? $user->name : 'Pengguna',
            ],
            'customization' => [
                'autosave' => true,
                'forcesave' => true,
                'compactHeader' => false,
                'toolbarNoTabs' => false,
                'help' => false,
                'feedback' => false,
                'uiTheme' => 'theme-light',
                'unit' => 'cm',
            ],
        ];

        $config = [
            'documentType' => 'word',
            'document' => [
                'fileType' => 'docx',
                'key' => $docKey,
                'title' => $docTitle,
                'url' => $downloadUrl,
                'permissions' => [
                    'edit' => $canEdit,
                    'download' => true,
                    'print' => true,
                    'review' => false,
                    'comment' => false,
                ],
            ],
            'editorConfig' => $editorConfig,
            'type' => 'desktop',
            'width' => '100%',
            'height' => '100%',
        ];

        $jwtSecret = config('suratinaja.onlyoffice.jwt_secret');
        if (! empty($jwtSecret)) {
            $config['token'] = $this->generateJwtToken($config, $jwtSecret);
        }

        return $config;
    }

    public function handleEnvelopeSaveCallback(EnvelopeTemplate $template, array $payload): array
    {
        $status = (int) ($payload['status'] ?? 0);
        $downloadUrl = (string) ($payload['url'] ?? '');

        if (in_array($status, [2, 6], true) && ! empty($downloadUrl)) {
            $response = Http::timeout(60)
                ->withoutVerifying()
                ->get($downloadUrl);

            if ($response->successful()) {
                Storage::disk('local')->put($template->file_path, $response->body());
                $template->forceFill([
                    'version' => ((int) ($template->version ?? 1)) + 1,
                    'updated_at' => now(),
                ])->save();

                return ['error' => 0];
            }

            return ['error' => 1, 'message' => 'Gagal mengunduh file hasil simpan OnlyOffice'];
        }

        return ['error' => 0];
    }

    public function getKopMasterDocumentKey(Workspace $workspace): string
    {
        return 'kop_master_' . $workspace->id . '_v' . ($workspace->kop_master_version ?? 1) . '_' . ($workspace->updated_at?->timestamp ?? time());
    }

    public function generateKopMasterAccessToken(Workspace $workspace, string $action, int $ttlSeconds = 86400): string
    {
        $payload = [
            'type' => 'kop_master',
            'id' => $workspace->id,
            'action' => $action,
            'exp' => time() + $ttlSeconds,
        ];
        $json = json_encode($payload);
        $sig = hash_hmac('sha256', $json, config('app.key'));

        return $this->base64UrlEncode($json) . '.' . $this->base64UrlEncode($sig);
    }

    public function verifyKopMasterAccessToken(string $token, Workspace $workspace, string $action): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }

        $json = $this->base64UrlDecode($parts[0]);
        $sig = $this->base64UrlDecode($parts[1]);

        $expectedSig = hash_hmac('sha256', $json, config('app.key'));
        if (! hash_equals($expectedSig, $sig)) {
            return false;
        }

        $data = json_decode($json, true);
        if (! is_array($data)) {
            return false;
        }

        if (($data['type'] ?? '') !== 'kop_master' || (int) ($data['id'] ?? 0) !== (int) $workspace->id || ($data['action'] ?? '') !== $action) {
            return false;
        }

        return ($data['exp'] ?? 0) >= time();
    }

    public function buildKopMasterEditorConfig(Workspace $workspace, ?User $user = null, bool $canEdit = true): array
    {
        if (! $workspace->kop_master_docx_path || ! Storage::disk('local')->exists($workspace->kop_master_docx_path)) {
            throw DomainException::templateInvalid('File master kop surat .docx tidak ditemukan di storage.');
        }

        $downloadToken = $this->generateKopMasterAccessToken($workspace, 'download');
        $callbackToken = $this->generateKopMasterAccessToken($workspace, 'callback');

        $baseUrl = $this->getAppServiceBaseUrl();
        $downloadUrl = rtrim($baseUrl, '/') . "/api/onlyoffice/kop-master/download/{$workspace->id}?token={$downloadToken}";
        $callbackUrl = rtrim($baseUrl, '/') . "/api/onlyoffice/kop-master/callback/{$workspace->id}?token={$callbackToken}";

        $docKey = $this->getKopMasterDocumentKey($workspace);
        $docTitle = 'Master_Kop_' . \Illuminate\Support\Str::slug($workspace->name) . '.docx';

        $editorConfig = [
            'mode' => $canEdit ? 'edit' : 'view',
            'lang' => 'id',
            'callbackUrl' => $callbackUrl,
            'user' => [
                'id' => (string) ($user ? $user->id : '1'),
                'name' => $user ? $user->name : 'Pengguna',
            ],
            'customization' => [
                'autosave' => true,
                'forcesave' => true,
                'compactHeader' => false,
                'toolbarNoTabs' => false,
                'help' => false,
                'feedback' => false,
                'uiTheme' => 'theme-light',
                'unit' => 'cm',
            ],
        ];

        $config = [
            'documentType' => 'word',
            'document' => [
                'fileType' => 'docx',
                'key' => $docKey,
                'title' => $docTitle,
                'url' => $downloadUrl,
                'permissions' => [
                    'edit' => $canEdit,
                    'download' => true,
                    'print' => true,
                    'review' => false,
                    'comment' => false,
                ],
            ],
            'editorConfig' => $editorConfig,
            'type' => 'desktop',
            'width' => '100%',
            'height' => '100%',
        ];

        $jwtSecret = config('suratinaja.onlyoffice.jwt_secret');
        if (! empty($jwtSecret)) {
            $config['token'] = $this->generateJwtToken($config, $jwtSecret);
        }

        return $config;
    }

    public function handleKopMasterSaveCallback(Workspace $workspace, array $payload): array
    {
        $status = (int) ($payload['status'] ?? 0);
        $downloadUrl = (string) ($payload['url'] ?? '');

        if (in_array($status, [2, 6], true) && ! empty($downloadUrl)) {
            $response = Http::timeout(60)
                ->withoutVerifying()
                ->get($downloadUrl);

            if ($response->successful()) {
                Storage::disk('local')->put($workspace->kop_master_docx_path, $response->body());
                $workspace->forceFill([
                    'kop_master_version' => ((int) ($workspace->kop_master_version ?? 1)) + 1,
                    'updated_at' => now(),
                ])->save();

                return ['error' => 0];
            }

            return ['error' => 1, 'message' => 'Gagal mengunduh file hasil simpan OnlyOffice'];
        }

        return ['error' => 0];
    }

    public function convertArbitraryDocxToPdf(string $docxRelativePath, string $pdfRelativePath, string $key, string $title = 'Dokumen.docx'): array
    {
        if (! Storage::disk('local')->exists($docxRelativePath)) {
            return ['ok' => false, 'message' => "File .docx tidak ditemukan di {$docxRelativePath}"];
        }

        // Check if PDF already exists and is fresh
        if (Storage::disk('local')->exists($pdfRelativePath)) {
            $docxMtime = Storage::disk('local')->lastModified($docxRelativePath);
            $pdfMtime = Storage::disk('local')->lastModified($pdfRelativePath);
            if ($pdfMtime >= $docxMtime) {
                return ['ok' => true, 'path' => $pdfRelativePath];
            }
        }

        $downloadToken = hash_hmac('sha256', $docxRelativePath, config('app.key'));
        $baseUrl = $this->getAppServiceBaseUrl();
        $downloadUrl = rtrim($baseUrl, '/') . '/api/onlyoffice/raw-download?path=' . urlencode($docxRelativePath) . '&token=' . $downloadToken;

        $payload = [
            'async' => false,
            'filetype' => 'docx',
            'key' => $key . '_' . time(),
            'outputtype' => 'pdf',
            'title' => $title,
            'url' => $downloadUrl,
        ];

        $jwtSecret = config('suratinaja.onlyoffice.jwt_secret');
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if (! empty($jwtSecret)) {
            $jwtToken = $this->generateJwtToken($payload, $jwtSecret);
            $payload['token'] = $jwtToken;
            $headers['Authorization'] = 'Bearer ' . $jwtToken;
        }

        $convertUrl = $this->getServerServiceUrl() . '/ConvertService.ashx';

        try {
            $response = Http::timeout(30)
                ->withoutVerifying()
                ->withHeaders($headers)
                ->post($convertUrl, $payload);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['fileUrl']) && ! empty($data['fileUrl'])) {
                    $pdfContent = Http::timeout(30)->withoutVerifying()->get($data['fileUrl'])->body();
                    Storage::disk('local')->put($pdfRelativePath, $pdfContent);

                    return ['ok' => true, 'path' => $pdfRelativePath];
                }
            }
        } catch (\Throwable $e) {
            Log::warning("OnlyOffice conversion fallback: {$e->getMessage()}");
        }

        // Secondary fallback to Gotenberg
        try {
            $baseUrl = rtrim((string) config('suratinaja.preview.gotenberg_url', 'http://localhost:3000'), '/');
            $docxAbs = Storage::disk('local')->path($docxRelativePath);
            $response = Http::connectTimeout(1)->timeout(3)
                ->attach('files', fopen($docxAbs, 'r'), 'doc.docx')
                ->post("{$baseUrl}/forms/libreoffice/convert");

            if ($response->successful()) {
                Storage::disk('local')->put($pdfRelativePath, $response->body());

                return ['ok' => true, 'path' => $pdfRelativePath];
            }
        } catch (\Throwable) {}

        return ['ok' => false, 'message' => 'Konversi PDF gagal'];
    }

    /**
     * Resolves the browser-facing origin of this Laravel app (used for OnlyOffice plugin iframe URLs).
     */
    private function getPublicBrowserBaseUrl(): string
    {
        $reqHost = request()?->getSchemeAndHttpHost();
        if (! empty($reqHost) && ! str_contains($reqHost, 'localhost:80')) {
            if (str_contains($reqHost, 'suratinaja.test') && str_starts_with($reqHost, 'http://')) {
                return str_replace('http://', 'https://', $reqHost);
            }

            return $reqHost;
        }

        return $this->getAppServiceBaseUrl();
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
