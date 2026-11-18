<?php

namespace App\Services\Documents;

use App\Exceptions\DomainException;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use Throwable;
use ZipArchive;

class DocxRenderer
{
    public function __construct(
        private readonly KopInjector $kopInjector,
    ) {}

    /**
     * Renders a single Letter into `storage/app/private/generated/{workspace}/{letter}-v{version}.docx`
     * and returns the relative storage path.
     *
     * @throws DomainException
     */
    public function renderSingle(Letter $letter): string
    {
        $workspace = $letter->workspace()->withoutGlobalScopes()->firstOrFail();
        $template = $letter->template()->withoutGlobalScopes()->firstOrFail();

        $templateAbsPath = $this->resolveTemplatePath($template);
        $relativeOut = "generated/{$workspace->id}/letter-{$letter->id}-v{$letter->version}.docx";
        $targetAbs = Storage::disk('local')->path($relativeOut);
        $this->ensureDirectory(dirname($targetAbs));

        $workingCopy = tempnam(sys_get_temp_dir(), 'sa_docx_').'.docx';
        copy($templateAbsPath, $workingCopy);

        try {
            $this->kopInjector->prepareTemplateCopy($workingCopy, $workspace);

            $processor = new TemplateProcessor($workingCopy);
            $this->kopInjector->applyValues($processor, $workspace);

            $this->applyLetterVariables(
                $processor,
                $template->variables_schema ?? [],
                $letter->field_values ?? [],
                [
                    'nomor_surat' => (string) ($letter->formatted_number ?? ''),
                    'perihal' => (string) $letter->perihal,
                    'tanggal_surat' => $this->formatDate($letter->letter_date),
                ]
            );

            $processor->saveAs($targetAbs);
            $this->stripResidualPlaceholders($targetAbs);
        } finally {
            @unlink($workingCopy);
        }

        return $relativeOut;
    }

    /**
     * Renders N letters into a single multi-page .docx file (one page per recipient, AC-05-02).
     *
     * @param  array<int, Letter>  $letters
     * @return string Relative storage path
     */
    public function renderBulkDocument(
        Workspace $workspace,
        LetterTemplate $template,
        array $letters,
        string $jobId,
    ): string {
        $templateAbsPath = $this->resolveTemplatePath($template);
        $relativeOut = "generated/{$workspace->id}/{$jobId}.docx";
        $targetAbs = Storage::disk('local')->path($relativeOut);
        $this->ensureDirectory(dirname($targetAbs));

        $count = count($letters);
        if ($count === 0) {
            throw DomainException::bulkJobFailed('Tidak ada penerima untuk digenerate.');
        }

        // Prepare one base copy with kop injected, then wrap body content in ${BULK_PAGE}...${/BULK_PAGE}
        $workingCopy = tempnam(sys_get_temp_dir(), 'sa_bulk_').'.docx';
        copy($templateAbsPath, $workingCopy);

        try {
            $this->kopInjector->prepareTemplateCopy($workingCopy, $workspace);
            $this->wrapBodyInRepeatableBlock($workingCopy, 'BULK_PAGE');

            $processor = new TemplateProcessor($workingCopy);
            $processor->cloneBlock('BULK_PAGE', $count, true, true);

            $schema = $template->variables_schema ?? [];
            foreach (array_values($letters) as $idx => $letter) {
                $i = $idx + 1;
                $suffix = "#{$i}";
                $this->kopInjector->applyValues($processor, $workspace, $suffix);

                $this->applyLetterVariables(
                    $processor,
                    $schema,
                    $letter->field_values ?? [],
                    [
                        'nomor_surat' => (string) ($letter->formatted_number ?? ''),
                        'perihal' => (string) $letter->perihal,
                        'tanggal_surat' => $this->formatDate($letter->letter_date),
                    ],
                    $suffix
                );
            }

            $processor->saveAs($targetAbs);
            $this->stripResidualPlaceholders($targetAbs);
        } finally {
            @unlink($workingCopy);
        }

        return $relativeOut;
    }

    /**
     * @param  array<int, array<string, mixed>>  $schema
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $systemValues
     */
    private function applyLetterVariables(
        TemplateProcessor $processor,
        array $schema,
        array $values,
        array $systemValues,
        string $suffix = '',
    ): void {
        foreach ($systemValues as $sysKey => $sysVal) {
            $processor->setValue($sysKey.$suffix, $sysVal);
        }

        $handledKeys = array_keys($systemValues);

        foreach ($schema as $field) {
            $type = $field['type'] ?? 'text';
            $name = $field['name'] ?? '';
            if ($name === '' || $type === 'system') {
                continue;
            }

            $handledKeys[] = $name;

            if ($type === 'repeater') {
                $this->applyRepeater($processor, $field, $values[$name] ?? [], $suffix);
                continue;
            }

            $raw = $values[$name] ?? '';
            if (is_array($raw)) {
                $raw = implode(', ', array_map('strval', $raw));
            }
            $formatted = $type === 'date' ? $this->formatDate($raw) : (string) $raw;
            $processor->setValue($name.$suffix, $formatted);
        }

        // Also apply any extra scalar keys passed in $values that were not in schema
        foreach ($values as $k => $v) {
            if (in_array($k, $handledKeys, true) || is_array($v)) {
                continue;
            }
            $processor->setValue($k.$suffix, (string) $v);
        }
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  mixed  $rows
     */
    private function applyRepeater(
        TemplateProcessor $processor,
        array $field,
        mixed $rows,
        string $suffix = '',
    ): void {
        $group = $field['name'];
        $columns = $field['columns'] ?? [];
        if (empty($columns)) {
            return;
        }

        $firstToken = ($columns[0]['token'] ?? "{$group}.{$columns[0]['name']}").$suffix;
        $rows = is_array($rows) ? array_values(array_filter($rows, fn ($r) => is_array($r) && count(array_filter($r, fn ($v) => $v !== '' && $v !== null)) > 0)) : [];

        if (empty($rows)) {
            $policy = config('suratinaja.templates.empty_repeater', 'delete_row');
            try {
                if ($policy === 'delete_row') {
                    $processor->deleteRow($firstToken);
                } else {
                    foreach ($columns as $col) {
                        $token = ($col['token'] ?? "{$group}.{$col['name']}").$suffix;
                        $processor->setValue($token, '-');
                    }
                }
            } catch (Throwable) {
                foreach ($columns as $col) {
                    $token = ($col['token'] ?? "{$group}.{$col['name']}").$suffix;
                    $processor->setValue($token, '');
                }
            }

            return;
        }

        $cloneValues = [];
        foreach ($rows as $row) {
            $mapped = [];
            foreach ($columns as $col) {
                $colName = $col['name'];
                $token = ($col['token'] ?? "{$group}.{$colName}").$suffix;
                $mapped[$token] = (string) ($row[$colName] ?? '');
            }
            $cloneValues[] = $mapped;
        }

        try {
            $processor->cloneRowAndSetValues($firstToken, $cloneValues);
        } catch (Throwable) {
            // Fallback if template put repeater outside a table row: fill first row
            $first = $cloneValues[0] ?? [];
            foreach ($first as $token => $val) {
                $processor->setValue($token, $val);
            }
        }
    }

    private function wrapBodyInRepeatableBlock(string $docxPath, string $blockName): void
    {
        $zip = new ZipArchive;
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            $zip->close();

            return;
        }

        $openTag = '<w:p><w:r><w:t>${'.$blockName.'}</w:t></w:r></w:p>';
        $pageBreakAndClose = '<w:p><w:r><w:br w:type="page"/></w:r></w:p>'
            .'<w:p><w:r><w:t>${/'.$blockName.'}</w:t></w:r></w:p>';

        $xml = preg_replace('/(<w:body[^>]*>)/', '$1'.$openTag, $xml, 1);
        if (str_contains((string) $xml, '<w:sectPr')) {
            $xml = preg_replace('/(<w:sectPr\b)/', $pageBreakAndClose.'$1', (string) $xml, 1);
        } else {
            $xml = str_replace('</w:body>', $pageBreakAndClose.'</w:body>', (string) $xml);
        }

        $zip->addFromString('word/document.xml', (string) $xml);
        $zip->close();
    }

    /**
     * Guarantees AC-03-04: any unreferenced ${...} tokens remaining in document.xml are cleared.
     */
    private function stripResidualPlaceholders(string $docxPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($docxPath) !== true) {
            return;
        }
        $xml = $zip->getFromName('word/document.xml');
        if ($xml !== false) {
            // Remove trailing page break right before </w:body> or <w:sectPr> on bulk docs and any stray ${...}
            $cleaned = preg_replace('/\$\{[^}]+\}/', '', $xml);
            if ($cleaned !== null) {
                $zip->addFromString('word/document.xml', $cleaned);
            }
        }
        $zip->close();
    }

    private function resolveTemplatePath(LetterTemplate $template): string
    {
        if (! Storage::disk('local')->exists($template->file_path)) {
            throw DomainException::templateInvalid('File master template .docx tidak ditemukan di storage.');
        }

        return Storage::disk('local')->path($template->file_path);
    }

    private function formatDate(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }
        try {
            $carbon = $value instanceof Carbon ? $value : Carbon::parse((string) $value);

            return $carbon->locale('id')->translatedFormat(config('suratinaja.templates.date_format', 'd F Y'));
        } catch (Throwable) {
            return (string) $value;
        }
    }

    private function ensureDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }
}
