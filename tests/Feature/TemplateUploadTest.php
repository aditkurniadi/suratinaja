<?php

namespace Tests\Feature;

use App\Actions\Templates\DuplicateTemplateAction;
use App\Actions\Templates\UploadTemplateAction;
use App\Enums\TemplateScope;
use App\Enums\WorkspaceRole;
use App\Exceptions\DomainException;
use App\Livewire\Templates\TemplatesIndex;
use App\Models\LetterTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Templates\SampleDocxBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class TemplateUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_docx_upload_extracts_variables_schema_ac_03_01(): void
    {
        $ws = Workspace::create(['name' => 'HIMATRIS', 'number_code' => 'HIMATRIS']);
        $sec = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $sec->id,
            'role' => WorkspaceRole::Secretary,
        ]);

        $tmpDocx = tempnam(sys_get_temp_dir(), 'tpl_').'.docx';
        SampleDocxBuilder::create($tmpDocx, ['include_repeater' => true]);

        $uploaded = new UploadedFile($tmpDocx, 'undangan.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
        $template = app(UploadTemplateAction::class)->execute($uploaded, 'Undangan Rapat', $sec, $ws);

        $varNames = array_column($template->variables_schema, 'name');
        $this->assertContains('nama_kegiatan', $varNames);
        $this->assertContains('tanggal_kegiatan', $varNames);
        $this->assertContains('lampiran', $varNames);

        @unlink($tmpDocx);
    }

    public function test_docx_without_variables_is_rejected_with_template_invalid_task_04(): void
    {
        $ws = Workspace::create(['name' => 'HIMATRIS', 'number_code' => 'HIMATRIS']);
        $sec = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $sec->id,
            'role' => WorkspaceRole::Secretary,
        ]);

        $tmpDocx = tempnam(sys_get_temp_dir(), 'empty_tpl_').'.docx';
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('Dokumen statis tanpa placeholder sama sekali.');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmpDocx);

        $uploaded = new UploadedFile($tmpDocx, 'statis.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

        try {
            app(UploadTemplateAction::class)->execute($uploaded, 'Dokumen Statis', $sec, $ws);
            $this->fail('Expected TEMPLATE_INVALID DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertSame('TEMPLATE_INVALID', $e->errorCode);
        } finally {
            @unlink($tmpDocx);
        }
    }

    public function test_global_template_is_read_only_and_can_be_duplicated_to_workspace_ac_03_03(): void
    {
        $ws = Workspace::create(['name' => 'HIMATRIS', 'number_code' => 'HIMATRIS']);
        $sec = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $sec->id,
            'role' => WorkspaceRole::Secretary,
        ]);

        $rel = 'templates/global/master.docx';
        SampleDocxBuilder::create(Storage::disk('local')->path($rel));

        $global = LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => null,
            'scope' => TemplateScope::Global,
            'name' => 'Master Global',
            'file_path' => $rel,
            'variables_schema' => [['name' => 'nama_kegiatan', 'type' => 'text', 'label' => 'Nama Kegiatan']],
            'version' => 1,
        ]);

        // Policy denies direct update on global template
        $this->assertFalse($sec->can('update', $global));

        // Duplicating creates a writable workspace-scoped copy
        $copy = app(DuplicateTemplateAction::class)->execute($global, $ws, $sec);
        $this->assertSame(TemplateScope::Workspace, $copy->scope);
        $this->assertSame($ws->id, $copy->workspace_id);
        $this->assertSame($global->id, $copy->source_template_id);
        $this->assertTrue($sec->can('update', $copy));
    }

    public function test_custom_template_with_embedded_kop_and_4_column_table_renders_cleanly(): void
    {
        $ws = Workspace::create([
            'name' => 'HIMATRIS',
            'number_code' => 'HIMATRIS',
            'kop_header_text' => "KOP WORKSPACE RESMI\nPOLITEKNIK NEGERI CILACAP",
            'kop_address_text' => 'Jl. Dr. Soetomo No. 1',
        ]);
        $sec = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $sec->id,
            'role' => WorkspaceRole::Secretary,
        ]);

        // Create a custom Word template that ALREADY has its own Kop, custom scalar vars, 4-column table, and ${signature_block}
        $tmpDocx = tempnam(sys_get_temp_dir(), 'custom_kop_').'.docx';
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('KOP MANDIRI PANITIA KHUSUS DIDALAM WORD');
        $section->addText('Nomor: ${nomor_surat} | Perihal: ${perihal}');
        $section->addText('Keperluan: ${keperluan_dispensasi} pada tanggal ${tanggal_mulai}');

        $table = $section->addTable();
        $table->addRow();
        $table->addCell(1000)->addText('No');
        $table->addCell(3000)->addText('Nama');
        $table->addCell(2000)->addText('NIM');
        $table->addCell(3000)->addText('Prodi');

        $table->addRow();
        $table->addCell(1000)->addText('${peserta.no}');
        $table->addCell(3000)->addText('${peserta.nama}');
        $table->addCell(2000)->addText('${peserta.nim}');
        $table->addCell(3000)->addText('${peserta.prodi}');

        $section->addText('${signature_block}');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmpDocx);

        $uploaded = new UploadedFile($tmpDocx, 'surat-dispensasi.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
        $template = app(UploadTemplateAction::class)->execute(
            $uploaded,
            'Surat Dispensasi Panitia',
            $sec,
            $ws,
            TemplateScope::Workspace,
            'Template dengan kop mandiri dan tabel 4 kolom',
            true, // has_embedded_kop = true
        );

        $this->assertTrue($template->has_embedded_kop);

        $pesertaField = collect($template->variables_schema)->firstWhere('name', 'peserta');
        $this->assertNotNull($pesertaField);
        $this->assertSame('repeater', $pesertaField['type']);
        $this->assertCount(4, $pesertaField['columns']);
        $this->assertSame(['no', 'nama', 'nim', 'prodi'], array_column($pesertaField['columns'], 'name'));

        // Render a letter using this template
        $letter = app(\App\Actions\Letters\CreateLetterAction::class)->execute($ws, $sec, [
            'template_id' => $template->id,
            'perihal' => 'Dispensasi Lomba Nasional',
            'letter_date' => '2026-11-18',
            'variables' => [
                'keperluan_dispensasi' => 'Mengikuti Final Gemastik',
                'tanggal_mulai' => '2026-11-20',
                'peserta' => [
                    ['no' => '', 'nama' => 'Budi Santoso', 'nim' => '2401001', 'prodi' => 'TRPL'],
                    ['no' => '', 'nama' => 'Siti Aminah', 'nim' => '2401002', 'prodi' => 'TI'],
                ],
                '_signers' => [
                    ['prefix' => '', 'position' => 'Ketua Panitia,', 'name' => 'Andi Wijaya', 'id_label' => 'NIM.', 'id_number' => '24001', 'layout' => 'auto'],
                    ['prefix' => '', 'position' => 'Sekretaris,', 'name' => 'Rina Putri', 'id_label' => 'NIM.', 'id_number' => '24002', 'layout' => 'auto'],
                    ['prefix' => 'Mengetahui,', 'position' => 'Kaprodi TRPL,', 'name' => 'Lutfi Syafirullah', 'id_label' => 'NIP.', 'id_number' => '198411', 'layout' => 'auto'],
                ],
            ],
        ]);

        $generatedAbs = Storage::disk('local')->path($letter->generated_file_path);
        $this->assertFileExists($generatedAbs);

        $zip = new \ZipArchive;
        $zip->open($generatedAbs);
        $docXml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        // Embedded kop is preserved, and workspace kop is NOT injected
        $this->assertStringContainsString('KOP MANDIRI PANITIA KHUSUS DIDALAM WORD', $docXml);
        $this->assertStringNotContainsString('KOP WORKSPACE RESMI', $docXml);

        // Custom variables and 4-column table rows are populated
        $this->assertStringContainsString('Mengikuti Final Gemastik', $docXml);
        $this->assertStringContainsString('Budi Santoso', $docXml);
        $this->assertStringContainsString('2401001', $docXml);
        $this->assertStringContainsString('Siti Aminah', $docXml);
        $this->assertStringContainsString('2401002', $docXml);

        // ${signature_block} was replaced with the 3-signer dynamic table
        $this->assertStringNotContainsString('signature_block', $docXml);
        $this->assertStringContainsString('Andi Wijaya', $docXml);
        $this->assertStringContainsString('Rina Putri', $docXml);
        $this->assertStringContainsString('Lutfi Syafirullah', $docXml);

        $preview = app(\App\Services\Documents\PdfPreviewService::class)->ensurePreviewPdf($letter);
        $this->assertTrue($preview['ok']);
        $this->assertFileExists(Storage::disk('local')->path($preview['path']));

        @unlink($tmpDocx);
    }

    public function test_custom_template_with_tujuan_and_instansi_renders_cleanly_without_empty_dash_or_duplicated_body(): void
    {
        $ws = Workspace::create([
            'name' => 'HIMATRIS',
            'number_code' => 'HIMATRIS',
            'kop_header_text' => "PANITIA MAKRAB\nHIMATRIS",
            'kop_address_text' => 'Cilacap',
        ]);
        $sec = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $sec->id,
            'role' => WorkspaceRole::Secretary,
        ]);

        $tmpDocx = tempnam(sys_get_temp_dir(), 'custom_room_').'.docx';
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('Kepada Yth.');
        $section->addText('${tujuan}');
        $section->addText('${instansi}');
        $section->addText('Di - Tempat');
        $section->addText('Sehubungan dengan penyelenggaraan MALTRAP, mohon peminjaman tempat:');
        $section->addText('Hari, Tanggal: ${tanggal_keiatan}');
        $section->addText('Waktu: Pukul ${waktu} WIB s.d. selesai');
        $section->addText('Tempat: Ruang Kelas ${ruang_kelas}');
        $section->addText('${signature_block}');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmpDocx);

        $uploaded = new UploadedFile($tmpDocx, 'peminjaman-ruang.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
        $template = app(UploadTemplateAction::class)->execute($uploaded, 'Peminjaman Ruang Custom', $sec, $ws);

        $letter = app(\App\Actions\Letters\CreateLetterAction::class)->execute($ws, $sec, [
            'template_id' => $template->id,
            'perihal' => 'Peminjaman Ruang Kelas',
            'letter_date' => '2026-11-18',
            'variables' => [
                'tujuan' => 'Adit Kurniadi',
                'instansi' => 'PNC',
                'tanggal_keiatan' => '2026-11-18',
                'waktu' => '12.00',
                'ruang_kelas' => '3.5',
                '_signers' => [
                    ['prefix' => '', 'position' => 'Ketua HIMATRIS,', 'name' => 'Dimas Surya Pratama', 'id_label' => 'NIM.', 'id_number' => '25.01.09.044', 'layout' => 'auto'],
                    ['prefix' => '', 'position' => 'Ketua Pelaksana,', 'name' => 'Karisma Putri Isabela', 'id_label' => 'NIM.', 'id_number' => '25.03.15.020', 'layout' => 'auto'],
                ],
            ],
        ]);

        // DOCX verification
        $docxAbs = Storage::disk('local')->path($letter->generated_file_path);
        $this->assertFileExists($docxAbs);
        $zip = new \ZipArchive;
        $zip->open($docxAbs);
        $docXml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertStringContainsString('Adit Kurniadi', $docXml);
        $this->assertStringContainsString('PNC', $docXml);
        $this->assertStringContainsString('12.00', $docXml);
        $this->assertStringContainsString('Ruang Kelas 3.5', $docXml);
        $this->assertStringContainsString('Dimas Surya Pratama', $docXml);
        $this->assertStringContainsString('Karisma Putri Isabela', $docXml);

        // PDF Preview verification
        $preview = app(\App\Services\Documents\PdfPreviewService::class)->ensurePreviewPdf($letter);
        $this->assertTrue($preview['ok']);
        $pdfAbs = Storage::disk('local')->path($preview['path']);
        $this->assertFileExists($pdfAbs);
        $this->assertGreaterThan(1000, filesize($pdfAbs));

        @unlink($tmpDocx);
    }

    public function test_custom_template_with_embedded_word_header_kop_renders_in_pdf_preview(): void
    {
        $ws = Workspace::create([
            'name' => 'HIMATRIS',
            'number_code' => 'HIMATRIS',
            'kop_header_text' => "WORKSPACE KOP DEFAULT\nHIMATRIS",
            'kop_address_text' => 'Cilacap',
        ]);
        $sec = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $sec->id,
            'role' => WorkspaceRole::Secretary,
        ]);

        $tmpDocx = tempnam(sys_get_temp_dir(), 'header_kop_').'.docx';
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $header = $section->addHeader();
        $header->addText('KOP EMBEDDED WORD HEADER KHUSUS');
        $header->addText('Jalan Dr. Soetomo No. 1, Cilacap');

        $section->addText('Nomor: ${nomor_surat}');
        $section->addText('Hal: ${perihal}');
        $section->addText('Kepada Yth. ${tujuan}');
        $section->addText('${signature_block}');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmpDocx);

        $uploaded = new UploadedFile($tmpDocx, 'embedded-header.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

        $template = app(UploadTemplateAction::class)->execute(
            $uploaded,
            'Template With Embedded Word Header',
            $sec,
            $ws,
            TemplateScope::Workspace,
            'Description',
            true // hasEmbeddedKop
        );

        $this->assertTrue($template->hasEmbeddedKop());

        $letter = app(\App\Actions\Letters\CreateLetterAction::class)->execute($ws, $sec, [
            'template_id' => $template->id,
            'perihal' => 'Pengujian Kop Word',
            'letter_date' => '2026-11-18',
            'variables' => [
                'tujuan' => 'Rektor Politeknik',
                '_signers' => [
                    ['prefix' => '', 'position' => 'Ketua,', 'name' => 'Dimas Pratama', 'id_label' => 'NIM.', 'id_number' => '25001', 'layout' => 'auto'],
                ],
            ],
        ]);

        $preview = app(\App\Services\Documents\PdfPreviewService::class)->ensurePreviewPdf($letter);
        $this->assertTrue($preview['ok']);
        $pdfAbs = Storage::disk('local')->path($preview['path']);
        $this->assertFileExists($pdfAbs);

        // Verify PDF was generated and contains content
        $this->assertGreaterThan(1000, filesize($pdfAbs));

        @unlink($tmpDocx);
    }

    public function test_docx_with_alternative_syntax_or_split_runs_is_auto_healed_and_detected(): void
    {
        $ws = Workspace::create(['name' => 'AutoHeal WS', 'number_code' => 'AH']);
        $sec = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $sec->id, 'role' => WorkspaceRole::Secretary]);

        $tmpDocx = tempnam(sys_get_temp_dir(), 'autoheal_').'.docx';
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        // Uses single curly braces {nama_penerima} and double curly braces {{ruang_ujian}}
        $section->addText('Kepada {nama_penerima}, ruangan Anda adalah {{ruang_ujian}}.');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmpDocx);

        $uploaded = new UploadedFile($tmpDocx, 'autoheal.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

        $template = app(UploadTemplateAction::class)->execute(
            $uploaded,
            'Template Auto Heal',
            $sec,
            $ws
        );

        $schema = $template->variables_schema ?? [];
        $varNames = array_column($schema, 'name');

        $this->assertContains('nama_penerima', $varNames);
        $this->assertContains('ruang_ujian', $varNames);

        @unlink($tmpDocx);
    }

    public function test_template_larger_than_5mb_is_accepted_up_to_50mb(): void
    {
        $ws = Workspace::create(['name' => 'Large WS', 'number_code' => 'LWS']);
        $sec = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $sec->id, 'role' => WorkspaceRole::Secretary]);

        $tmpDocx = tempnam(sys_get_temp_dir(), 'large_tpl_').'.docx';
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('Dokumen besar dengan variabel: ${tujuan}');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmpDocx);

        // Add a 5.5MB dummy entry into the zip archive so it's a valid 5.5MB docx
        $zip = new \ZipArchive();
        $zip->open($tmpDocx);
        $zip->addFromString('word/media/heavy_image.png', random_bytes(5600 * 1024));
        $zip->close();

        $this->assertGreaterThan(5500 * 1024, filesize($tmpDocx));

        $uploaded = new UploadedFile($tmpDocx, 'large-template-5.5mb.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

        $template = app(UploadTemplateAction::class)->execute(
            $uploaded,
            'Large Template 5.5MB',
            $sec,
            $ws
        );

        $this->assertNotNull($template->id);
        $schema = $template->variables_schema ?? [];
        $varNames = array_column($schema, 'name');
        $this->assertContains('tujuan', $varNames);

        @unlink($tmpDocx);
    }

    public function test_custom_template_with_tablegrid_rundown_and_page_break_renders_bordered_table_in_preview(): void
    {
        $ws = Workspace::create(['name' => 'HIMATRIS', 'number_code' => 'HIMATRIS']);
        $sec = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $sec->id, 'role' => WorkspaceRole::Secretary]);

        $tmpDocx = tempnam(sys_get_temp_dir(), 'rundown_tpl_').'.docx';
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('Kepada Yth. ${tujuan}');
        $section->addPageBreak();
        $section->addText('Lampiran I');

        $table = $section->addTable(['borderSize' => 6, 'borderColor' => '000000']);
        $table->addRow();
        $table->addCell(1500)->addText('Tanggal');
        $table->addCell(1700)->addText('Waktu');
        $table->addCell(3600)->addText('Kegiatan');
        $table->addCell(2800)->addText('Pelaksana');

        $table->addRow();
        $table->addCell(1500)->addText('Sabtu, 5 Desember 2026');
        $table->addCell(1700)->addText('06:00 - 07:00');
        $table->addCell(3600)->addText('Persiapan Panitia');
        $table->addCell(2800)->addText('Panitia');

        IOFactory::createWriter($phpWord, 'Word2007')->save($tmpDocx);

        // Simulate Word's TableGrid style without inline w:val="single"
        $zip = new \ZipArchive();
        $zip->open($tmpDocx);
        $xml = (string) $zip->getFromName('word/document.xml');
        $xml = preg_replace('/<w:tblBorders>.*?<\/w:tblBorders>/s', '<w:tblStyle w:val="TableGrid"/>', $xml) ?? $xml;
        $zip->addFromString('word/document.xml', $xml);
        $zip->close();

        $uploaded = new UploadedFile($tmpDocx, 'rundown.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
        $template = app(UploadTemplateAction::class)->execute($uploaded, 'Undangan Rundown', $sec, $ws);

        $letter = app(\App\Actions\Letters\CreateLetterAction::class)->execute($ws, $sec, [
            'template_id' => $template->id,
            'perihal' => 'Undangan Kegiatan',
            'letter_date' => '2026-11-18',
            'variables' => ['tujuan' => 'Dosen Pembina'],
        ]);

        $previewService = app(\App\Services\Documents\PdfPreviewService::class);
        $reflector = new \ReflectionClass($previewService);
        $method = $reflector->getMethod('renderCustomDocxBodyHtml');
        $html = $method->invoke($previewService, $letter, false, '<div class="sig">TTD</div>', '', false);

        $this->assertStringContainsString('page-break-before: always', $html);
        $this->assertStringContainsString('<table class="lampiran-table"', $html);
        $this->assertStringContainsString('<th style="', $html);
        $this->assertStringContainsString('border: 0.75pt solid #000000;', $html);
        $this->assertStringContainsString('Persiapan Panitia', $html);

        $preview = $previewService->ensurePreviewPdf($letter);
        $this->assertTrue($preview['ok']);

        @unlink($tmpDocx);
    }

    public function test_docx_without_variables_can_be_uploaded_when_allow_empty_variables_is_true(): void
    {
        $ws = Workspace::create(['name' => 'HIMATRIS', 'number_code' => 'HIMATRIS']);
        $sec = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $sec->id,
            'role' => WorkspaceRole::Secretary,
        ]);

        $tmpDocx = tempnam(sys_get_temp_dir(), 'empty_allowed_').'.docx';
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('Dokumen Word tanpa variabel sama sekali.');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmpDocx);

        $uploaded = new UploadedFile($tmpDocx, 'tanpa_variabel.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

        $template = app(UploadTemplateAction::class)->execute(
            $uploaded,
            'Template Word Polos',
            $sec,
            $ws,
            allowEmptyVariables: true
        );

        $this->assertInstanceOf(LetterTemplate::class, $template);
        $this->assertSame([], $template->variables_schema);
        $this->assertSame('Template Word Polos', $template->name);

        @unlink($tmpDocx);
    }

    public function test_livewire_upload_empty_variables_redirects_to_editor(): void
    {
        $ws = Workspace::create(['name' => 'HIMATRIS', 'number_code' => 'HIMATRIS']);
        $sec = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $sec->id,
            'role' => WorkspaceRole::Secretary,
        ]);
        app(\App\Support\CurrentWorkspace::class)->set($ws);

        $tmpDocx = tempnam(sys_get_temp_dir(), 'lw_empty_').'.docx';
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('Dokumen Word polos untuk pengujian Livewire upload.');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmpDocx);

        $uploaded = UploadedFile::fake()->createWithContent('surat_polos.docx', (string) file_get_contents($tmpDocx));

        $component = Livewire::actingAs($sec)
            ->test(TemplatesIndex::class)
            ->set('templateFile', $uploaded)
            ->assertSet('uploadError', null)
            ->assertSet('detectedVariables', [])
            ->set('templateName', 'Template Polos Livewire')
            ->call('saveTemplate');

        $created = LetterTemplate::where('name', 'Template Polos Livewire')->first();
        $this->assertNotNull($created);
        $this->assertSame([], $created->variables_schema);

        $component->assertRedirect(route('templates.edit', $created->id));

        @unlink($tmpDocx);
    }

    public function test_create_template_modal_with_uploaded_docx_redirects_to_editor(): void
    {
        $ws = Workspace::create(['name' => 'HIMATRIS', 'number_code' => 'HIMATRIS']);
        $sec = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $sec->id,
            'role' => WorkspaceRole::Secretary,
        ]);
        app(\App\Support\CurrentWorkspace::class)->set($ws);

        $tmpDocx = tempnam(sys_get_temp_dir(), 'modal_upload_').'.docx';
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('Dokumen dari modal buat template baru.');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmpDocx);

        $uploaded = UploadedFile::fake()->createWithContent('modal_doc.docx', (string) file_get_contents($tmpDocx));

        $component = Livewire::actingAs($sec)
            ->test(TemplatesIndex::class)
            ->set('newStarterMode', 'upload')
            ->set('newTemplateName', 'Template Dari Modal')
            ->set('newTemplateFile', $uploaded)
            ->call('createInOnlyOffice');

        $created = LetterTemplate::where('name', 'Template Dari Modal')->first();
        $this->assertNotNull($created);
        $this->assertSame([], $created->variables_schema);

        $component->assertRedirect(route('templates.edit', $created->id));

        @unlink($tmpDocx);
    }
}
