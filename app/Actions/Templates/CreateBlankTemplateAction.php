<?php

namespace App\Actions\Templates;

use App\Enums\TemplateScope;
use App\Exceptions\DomainException;
use App\Models\LetterTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Templates\SampleDocxBuilder;
use App\Services\Templates\TemplateParser;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateBlankTemplateAction
{
    public function __construct(
        private readonly TemplateParser $parser,
    ) {}

    /**
     * Creates a new .docx master template (starter letter or blank A4) ready to edit directly in OnlyOffice.
     *
     * @param  string  $starterMode  'starter' | 'blank'
     *
     * @throws DomainException
     */
    public function execute(
        string $name,
        User $actor,
        ?Workspace $workspace = null,
        TemplateScope $scope = TemplateScope::Workspace,
        ?string $description = null,
        bool $hasEmbeddedKop = false,
        string $starterMode = 'starter',
    ): LetterTemplate {
        if ($scope === TemplateScope::Global) {
            if (! $actor->is_super_admin) {
                throw DomainException::workspaceForbidden('Hanya Super Admin yang dapat membuat template global.');
            }
        } else {
            if (! $workspace || ! $workspace->roleOf($actor)?->canWrite()) {
                throw DomainException::workspaceForbidden('Anda tidak memiliki izin membuat template pada workspace ini.');
            }
        }

        $folder = $scope === TemplateScope::Global
            ? 'templates/global'
            : "templates/workspaces/{$workspace->id}";

        $filename = Str::slug($name ?: 'template').'-'.Str::lower(Str::random(6)).'.docx';
        $relativePath = "{$folder}/{$filename}";
        $absolutePath = Storage::disk('local')->path($relativePath);

        if ($starterMode === 'blank') {
            SampleDocxBuilder::createBlank($absolutePath);
        } else {
            SampleDocxBuilder::createStarter($absolutePath);
        }

        try {
            $schema = $this->parser->parse($absolutePath);
        } catch (DomainException) {
            // Blank A4 document intentionally starts with zero variables until user inserts them in OnlyOffice
            $schema = [];
        }

        return LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $scope === TemplateScope::Global ? null : $workspace->id,
            'scope' => $scope,
            'name' => trim($name),
            'description' => $description ? trim($description) : null,
            'file_path' => $relativePath,
            'variables_schema' => $schema,
            'has_embedded_kop' => $hasEmbeddedKop,
            'version' => 1,
            'is_deprecated' => false,
            'created_by' => $actor->id,
        ]);
    }
}
