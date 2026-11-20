<?php

namespace App\Actions\Templates;

use App\Enums\TemplateScope;
use App\Exceptions\DomainException;
use App\Models\LetterTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Documents\DocxRenderer;
use App\Services\Templates\TemplateParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadTemplateAction
{
    public function __construct(
        private readonly TemplateParser $parser,
        private readonly DocxRenderer $docxRenderer,
    ) {}

    /**
     * Validates and stores a .docx template for a workspace or as a global template.
     *
     * @throws DomainException
     */
    public function execute(
        UploadedFile $file,
        string $name,
        User $actor,
        ?Workspace $workspace = null,
        TemplateScope $scope = TemplateScope::Workspace,
        ?string $description = null,
        bool $hasEmbeddedKop = false,
    ): LetterTemplate {
        if ($scope === TemplateScope::Global) {
            if (! $actor->is_super_admin) {
                throw DomainException::workspaceForbidden('Hanya Super Admin yang dapat mengunggah template global.');
            }
        } else {
            if (! $workspace || ! $workspace->roleOf($actor)?->canWrite()) {
                throw DomainException::workspaceForbidden('Anda tidak memiliki izin mengunggah template pada workspace ini.');
            }
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext !== 'docx') {
            throw DomainException::templateInvalid('Format file harus .docx (Microsoft Word OpenXML).');
        }

        $maxBytes = ((int) config('suratinaja.uploads.template_max_kb', 5120)) * 1024;
        if ($file->getSize() > $maxBytes) {
            throw DomainException::templateInvalid('Ukuran file template melebihi batas maksimum yang diizinkan.');
        }

        $schema = $this->parser->parse($file->getRealPath());

        $folder = $scope === TemplateScope::Global
            ? 'templates/global'
            : "templates/workspaces/{$workspace->id}";

        $filename = Str::slug($name ?: 'template').'-'.Str::lower(Str::random(6)).'.docx';
        $storedPath = $file->storeAs($folder, $filename, 'local');

        if ($storedPath !== false) {
            $this->docxRenderer->sanitizeHeaderAndMarginsInDocx(
                Storage::disk('local')->path($storedPath),
                $hasEmbeddedKop
            );
        }

        return LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $scope === TemplateScope::Global ? null : $workspace->id,
            'scope' => $scope,
            'name' => trim($name),
            'description' => $description,
            'file_path' => $storedPath,
            'variables_schema' => $schema,
            'has_embedded_kop' => $hasEmbeddedKop,
            'version' => 1,
            'is_deprecated' => false,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * Creates a new version of an existing global template and optionally deprecates the old one (FR-11).
     */
    public function uploadNewVersion(
        LetterTemplate $existing,
        UploadedFile $file,
        User $actor,
        bool $deprecatePrevious = true,
    ): LetterTemplate {
        if ($existing->isGlobal() && ! $actor->is_super_admin) {
            throw DomainException::workspaceForbidden('Hanya Super Admin yang dapat memperbarui versi template global.');
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext !== 'docx') {
            throw DomainException::templateInvalid('Format file harus .docx.');
        }

        $schema = $this->parser->parse($file->getRealPath());
        $folder = $existing->isGlobal() ? 'templates/global' : "templates/workspaces/{$existing->workspace_id}";
        $nextVersion = ((int) $existing->version) + 1;
        $filename = Str::slug($existing->name)."-v{$nextVersion}-".Str::lower(Str::random(5)).'.docx';
        $storedPath = $file->storeAs($folder, $filename, 'local');

        if ($storedPath !== false) {
            $this->docxRenderer->sanitizeHeaderAndMarginsInDocx(
                Storage::disk('local')->path($storedPath),
                (bool) $existing->has_embedded_kop
            );
        }

        if ($deprecatePrevious) {
            $existing->forceFill(['is_deprecated' => true])->save();
        }

        return LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $existing->workspace_id,
            'scope' => $existing->scope,
            'name' => $existing->name,
            'description' => $existing->description,
            'file_path' => $storedPath,
            'variables_schema' => $schema,
            'has_embedded_kop' => (bool) $existing->has_embedded_kop,
            'version' => $nextVersion,
            'source_template_id' => $existing->id,
            'is_deprecated' => false,
            'created_by' => $actor->id,
        ]);
    }
}
