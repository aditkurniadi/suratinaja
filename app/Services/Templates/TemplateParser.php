<?php

namespace App\Services\Templates;

use App\Exceptions\DomainException;
use PhpOffice\PhpWord\TemplateProcessor;
use Throwable;
use ZipArchive;

class TemplateParser
{
    public function __construct(
        private readonly VariableTypeResolver $typeResolver,
    ) {}

    /**
     * Validates a .docx file and extracts its variable schema.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws DomainException
     */
    public function parse(string $absolutePath): array
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw DomainException::templateInvalid('File template tidak ditemukan atau tidak dapat dibaca.');
        }

        $zip = new ZipArchive;
        if ($zip->open($absolutePath) !== true) {
            throw DomainException::templateInvalid('File bukan arsip .docx yang valid.');
        }
        $hasDocumentXml = $zip->locateName('word/document.xml') !== false;
        $zip->close();

        if (! $hasDocumentXml) {
            throw DomainException::templateInvalid('Struktur internal .docx tidak valid (word/document.xml tidak ditemukan).');
        }

        // Fast direct XML inspection: only check document, headers, and footers (skips heavy media images)
        $allFoundVars = [];
        $cleanAllText = '';
        $modified = false;

        if ($zip->open($absolutePath) === true) {
            $targetXmlFiles = ['word/document.xml', 'word/footnotes.xml', 'word/endnotes.xml'];
            for ($k = 1; $k <= 10; $k++) {
                $targetXmlFiles[] = "word/header{$k}.xml";
                $targetXmlFiles[] = "word/footer{$k}.xml";
            }

            foreach ($targetXmlFiles as $name) {
                if ($zip->locateName($name) === false) {
                    continue;
                }

                $content = (string) $zip->getFromName($name);
                if (strlen($content) < 10) {
                    continue;
                }
                $origContent = $content;

                // 1. Remove proofing / grammar error tags that split words in Word
                $content = preg_replace('/<w:proofErr\b[^>]*\/>/s', '', $content) ?? $content;

                // 2. Merge split </w:t></w:r><w:r><w:t> tags
                $content = preg_replace('/<\/w:t><\/w:r>\s*<w:r(?: [^>]*)?>\s*<w:t(?: [^>]*)?>/s', '', $content) ?? $content;

                // 3. Normalize {{var}} to ${var}
                $content = preg_replace('/\{\{([a-zA-Z0-9_\.]+)\}\}/', '${$1}', $content) ?? $content;

                // 4. Normalize {var} to ${var} if not already prefixed by $
                $content = preg_replace('/(?<!\$)\{([a-zA-Z0-9_\.]+)\}/', '${$1}', $content) ?? $content;

                if ($content !== $origContent) {
                    $zip->deleteName($name);
                    $zip->addFromString($name, $content);
                    $modified = true;
                }

                // Extract variables with regex from clean text
                $cleanText = strip_tags($content);
                $cleanAllText .= "\n".$cleanText;
                if (preg_match_all('/\$\{([a-zA-Z0-9_\.]+)\}/', $cleanText, $matches)) {
                    foreach ($matches[1] as $v) {
                        $allFoundVars[] = $v;
                    }
                }
            }
            $zip->close();
        }

        try {
            $processor = new TemplateProcessor($absolutePath);
            $rawVariables = array_values(array_unique(array_merge($processor->getVariables(), $allFoundVars)));
        } catch (Throwable $e) {
            $rawVariables = array_values(array_unique($allFoundVars));
            if (empty($rawVariables)) {
                throw DomainException::templateInvalid(
                    'Parsing variabel gagal pada template — periksa struktur placeholder dokumen.',
                    ['reason' => $e->getMessage()]
                );
            }
        }

        $schema = $this->typeResolver->resolve($rawVariables);
        if (empty($schema)) {
            $diagnosticHints = [];

            // Check if user wrote variables with spaces e.g. ${nama kegiatan}
            if (preg_match_all('/\$\{([^\}]+)\}/', $cleanAllText ?? '', $mSpaces)) {
                $withSpaces = array_slice(array_unique(array_filter($mSpaces[1], fn ($v) => str_contains(trim((string) $v), ' '))), 0, 3);
                if (count($withSpaces) > 0) {
                    $diagnosticHints[] = 'Ditemukan placeholder dengan spasi: '.implode(', ', array_map(fn ($s) => "\${{$s}}", $withSpaces)).'. Gunakan underscore tanpa spasi (contoh: ${'.str_replace(' ', '_', $withSpaces[0]).'}).';
                }
            }

            // Check if user wrote brackets e.g. [tujuan]
            if (preg_match_all('/\[([a-zA-Z0-9_]{3,30})\]/', $cleanAllText ?? '', $mBrackets)) {
                $brackets = array_slice(array_unique($mBrackets[1]), 0, 3);
                if (count($brackets) > 0) {
                    $diagnosticHints[] = 'Ditemukan kurung siku: '.implode(', ', array_map(fn ($b) => "[{$b}]", $brackets)).'. Gunakan format dollar dan kurung kurawal (contoh: ${'.$brackets[0].'}).';
                }
            }

            $pCount = substr_count($cleanAllText ?? '', "\n") + 1;
            $msg = 'Template tidak memiliki placeholder variabel.';
            if (count($diagnosticHints) > 0) {
                $msg .= ' '.implode(' ', $diagnosticHints);
            } else {
                $msg .= " Sistem telah memindai {$pCount} bagian teks pada berkas Word ini. Pastikan Anda menulis variabel dengan format \${nama_variabel} (contoh: \${tujuan}, \${instansi}, \${waktu}) atau \${tabel.kolom} untuk tabel lampiran.";
            }

            throw DomainException::templateInvalid($msg);
        }

        return $schema;
    }
}
