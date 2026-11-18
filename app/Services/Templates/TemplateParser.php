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

        try {
            $processor = new TemplateProcessor($absolutePath);
            $rawVariables = $processor->getVariables();
        } catch (Throwable $e) {
            throw DomainException::templateInvalid(
                'Parsing variabel ${...} gagal pada template — periksa struktur placeholder dokumen.',
                ['reason' => $e->getMessage()]
            );
        }

        $schema = $this->typeResolver->resolve($rawVariables);
        if (empty($schema)) {
            throw DomainException::templateInvalid(
                'Template tidak memiliki placeholder variabel ${...}. Tambahkan minimal satu variabel pada dokumen .docx.'
            );
        }

        return $schema;
    }
}
