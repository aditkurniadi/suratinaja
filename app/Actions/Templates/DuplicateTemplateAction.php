<?php

namespace App\Actions\Templates;

use App\Enums\TemplateScope;
use App\Exceptions\DomainException;
use App\Models\LetterTemplate;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DuplicateTemplateAction
{
    /**
     * Duplicates a global (or workspace) template into the target workspace as a writable copy (DEC-001, AC-03-03).
     *
     * @throws DomainException
     */
    public function execute(LetterTemplate $source, Workspace $workspace, User $actor, ?string $newName = null): LetterTemplate
    {
        if (! $workspace->roleOf($actor)?->canWrite()) {
            throw DomainException::workspaceForbidden('Peran Ketua tidak diizinkan menduplikasi template.');
        }

        if (! $source->isAccessibleBy($workspace)) {
            throw DomainException::workspaceForbidden('Template sumber tidak dapat diakses oleh workspace ini.');
        }

        if (! Storage::disk('local')->exists($source->file_path)) {
            throw DomainException::templateInvalid('File fisik template sumber tidak ditemukan.');
        }

        $targetName = trim($newName ?: $source->name.' (Salinan '.$workspace->name.')');
        $targetPath = "templates/workspaces/{$workspace->id}/".Str::slug($targetName).'-'.Str::lower(Str::random(6)).'.docx';

        Storage::disk('local')->copy($source->file_path, $targetPath);

        return LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'scope' => TemplateScope::Workspace,
            'name' => $targetName,
            'description' => $source->description,
            'file_path' => $targetPath,
            'variables_schema' => $source->variables_schema ?? [],
            'version' => 1,
            'source_template_id' => $source->id,
            'is_deprecated' => false,
            'created_by' => $actor->id,
        ]);
    }
}
