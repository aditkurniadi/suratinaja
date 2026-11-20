<?php

namespace Tests\Feature;

use App\Enums\LetterStatus;
use App\Enums\WorkspaceRole;
use App\Models\Letter;
use App\Models\LetterActivity;
use App\Models\LetterTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Documents\OnlyOfficeService;
use App\Services\Templates\SampleDocxBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OnlyOfficeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function createTestLetter(LetterStatus $status = LetterStatus::Draft): array
    {
        Storage::fake('local');

        $workspace = Workspace::create([
            'name' => 'Organisasi Uji',
            'slug' => 'organisasi-uji',
            'number_pattern' => '{seq}/{kode}/{bulan_romawi}/{tahun}',
            'number_code' => 'ORG',
            'seq_padding' => 3,
        ]);

        $user = User::factory()->create(['current_workspace_id' => $workspace->id]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Secretary,
        ]);

        $tplRel = "templates/{$workspace->id}/test.docx";
        SampleDocxBuilder::create(Storage::disk('local')->path($tplRel));

        $template = LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'scope' => \App\Enums\TemplateScope::Workspace,
            'name' => 'Template Uji',
            'file_path' => $tplRel,
            'variables_schema' => [],
            'version' => 1,
        ]);

        $docxRel = "generated/{$workspace->id}/letter-1.docx";
        Storage::disk('local')->put($docxRel, 'dummy docx content');

        $letter = Letter::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'template_id' => $template->id,
            'created_by' => $user->id,
            'status' => $status,
            'reference_number' => '001/ORG/XI/2026',
            'perihal' => 'Surat Pengujian OnlyOffice',
            'letter_date' => now(),
            'field_values' => [],
            'generated_file_path' => $docxRel,
            'version' => 1,
        ]);

        return [$workspace, $user, $letter];
    }

    public function test_onlyoffice_config_generation_for_draft(): void
    {
        [$workspace, $user, $letter] = $this->createTestLetter(LetterStatus::Draft);

        /** @var OnlyOfficeService $service */
        $service = app(OnlyOfficeService::class);
        $config = $service->buildEditorConfig($letter, $user);

        $this->assertEquals('word', $config['documentType']);
        $this->assertEquals('docx', $config['document']['fileType']);
        $this->assertTrue($config['document']['permissions']['edit']);
        $this->assertEquals('edit', $config['editorConfig']['mode']);
        $this->assertNotEmpty($config['document']['url']);
        $this->assertNotEmpty($config['editorConfig']['callbackUrl']);
        $this->assertNotEmpty($config['token']);

        // Verify that JWT token in config is valid and decodable
        $jwtPayload = $service->validateJwtToken($config['token']);
        $this->assertNotNull($jwtPayload);
        $this->assertEquals('word', $jwtPayload['documentType']);
    }

    public function test_onlyoffice_config_locks_view_mode_on_completed_or_cancelled_letter(): void
    {
        [$workspace, $user, $letter] = $this->createTestLetter(LetterStatus::Selesai);

        /** @var OnlyOfficeService $service */
        $service = app(OnlyOfficeService::class);
        $config = $service->buildEditorConfig($letter, $user);

        $this->assertFalse($config['document']['permissions']['edit']);
        $this->assertEquals('view', $config['editorConfig']['mode']);
    }

    public function test_onlyoffice_download_endpoint_requires_valid_token(): void
    {
        [$workspace, $user, $letter] = $this->createTestLetter();

        // Download without token -> 403
        $response = $this->getJson(route('onlyoffice.download', ['letter' => $letter->id]));
        $response->assertStatus(403);

        // Download with invalid token -> 403
        $response = $this->getJson(route('onlyoffice.download', ['letter' => $letter->id, 'token' => 'invalid-token']));
        $response->assertStatus(403);

        // Download with valid signed token -> 200 with file
        $service = app(OnlyOfficeService::class);
        $validToken = $service->generateAccessToken($letter, 'download');

        $response = $this->get(route('onlyoffice.download', ['letter' => $letter->id, 'token' => $validToken]));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    public function test_onlyoffice_callback_endpoint_processes_save(): void
    {
        [$workspace, $user, $letter] = $this->createTestLetter();

        $service = app(OnlyOfficeService::class);
        $callbackToken = $service->generateAccessToken($letter, 'callback');

        $newDocxContent = 'updated docx content from onlyoffice';
        $downloadUrl = 'http://onlyoffice/files/download-test.docx';

        $pdfDownloadUrl = 'http://onlyoffice/cache/test-converted.pdf';
        Http::fake([
            $downloadUrl => Http::response($newDocxContent, 200),
            '*/ConvertService.ashx' => Http::response([
                'endConvert' => true,
                'fileUrl' => $pdfDownloadUrl,
                'percent' => 100,
            ], 200),
            $pdfDownloadUrl => Http::response('%PDF-1.4 fake pdf body', 200),
            'http://localhost:3000/*' => Http::response('fake pdf body', 200),
        ]);

        $payload = [
            'status' => 2, // Ready for save
            'url' => $downloadUrl,
            'key' => 'letter_test_key',
            'users' => [$user->id],
        ];

        $response = $this->postJson(
            route('onlyoffice.callback', ['letter' => $letter->id, 'token' => $callbackToken]),
            $payload
        );

        $response->assertStatus(200);
        $response->assertJson(['error' => 0]);

        // Verify letter file is updated
        $letter->refresh();
        $this->assertEquals(2, $letter->version);
        $savedContent = Storage::disk('local')->get($letter->generated_file_path);
        $this->assertEquals($newDocxContent, $savedContent);

        // Verify PDF was generated via OnlyOffice ConvertService
        $this->assertNotNull($letter->preview_pdf_path);
        $this->assertTrue(Storage::disk('local')->exists($letter->preview_pdf_path));
        $this->assertEquals('%PDF-1.4 fake pdf body', Storage::disk('local')->get($letter->preview_pdf_path));

        // Verify activity log recorded
        $activity = LetterActivity::where('letter_id', $letter->id)
            ->where('action', 'edited')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('onlyoffice', $activity->payload['source']);
    }

    public function test_onlyoffice_callback_handles_status_6_forcesave(): void
    {
        [$workspace, $user, $letter] = $this->createTestLetter();

        $service = app(OnlyOfficeService::class);
        $callbackToken = $service->generateAccessToken($letter, 'callback');

        $newDocxContent = 'forcesaved docx content';
        $downloadUrl = 'http://onlyoffice/files/download-forcesave.docx';
        $pdfDownloadUrl = 'http://onlyoffice/cache/forcesave.pdf';

        Http::fake([
            $downloadUrl => Http::response($newDocxContent, 200),
            '*/ConvertService.ashx' => Http::response([
                'endConvert' => true,
                'fileUrl' => $pdfDownloadUrl,
                'percent' => 100,
            ], 200),
            $pdfDownloadUrl => Http::response('%PDF-forcesave-content', 200),
        ]);

        $payload = [
            'status' => 6, // Forcesave
            'url' => $downloadUrl,
            'key' => 'letter_forcesave_key',
        ];

        $response = $this->postJson(
            route('onlyoffice.callback', ['letter' => $letter->id, 'token' => $callbackToken]),
            $payload
        );

        $response->assertStatus(200);
        $response->assertJson(['error' => 0]);

        $letter->refresh();
        $this->assertEquals(2, $letter->version);
        $this->assertEquals($newDocxContent, Storage::disk('local')->get($letter->generated_file_path));
        $this->assertEquals('%PDF-forcesave-content', Storage::disk('local')->get($letter->preview_pdf_path));
    }

    public function test_onlyoffice_convert_service_generates_pdf(): void
    {
        [$workspace, $user, $letter] = $this->createTestLetter();

        $pdfDownloadUrl = 'http://onlyoffice/cache/output.pdf';
        Http::fake([
            '*/ConvertService.ashx' => Http::response([
                'endConvert' => true,
                'fileUrl' => $pdfDownloadUrl,
                'percent' => 100,
            ], 200),
            $pdfDownloadUrl => Http::response('%PDF-2.0 generated from onlyoffice', 200),
        ]);

        /** @var OnlyOfficeService $service */
        $service = app(OnlyOfficeService::class);
        $result = $service->convertToPdf($letter, force: true);

        $this->assertTrue($result['ok']);
        $this->assertNotEmpty($result['path']);
        $this->assertTrue(Storage::disk('local')->exists($result['path']));
        $this->assertEquals('%PDF-2.0 generated from onlyoffice', Storage::disk('local')->get($result['path']));

        $letter->refresh();
        $this->assertEquals($result['path'], $letter->preview_pdf_path);
    }

    public function test_onlyoffice_forcesave_command_dispatches_correctly(): void
    {
        [$workspace, $user, $letter] = $this->createTestLetter();

        Http::fakeSequence()
            ->push(['error' => 0], 200)
            ->push(['error' => 4], 200);

        /** @var OnlyOfficeService $service */
        $service = app(OnlyOfficeService::class);

        $result0 = $service->forceSave($letter);
        $this->assertTrue($result0['ok']);
        $this->assertEquals(0, $result0['error']);

        // Second call returns error 4 (not in memory, which is also considered ok since disk is up to date)
        $result4 = $service->forceSave($letter);
        $this->assertTrue($result4['ok']);
        $this->assertEquals(4, $result4['error']);
    }

    public function test_preview_pdf_serves_onlyoffice_generated_pdf_with_no_cache_headers(): void
    {
        [$workspace, $user, $letter] = $this->createTestLetter();

        $pdfDownloadUrl = 'http://onlyoffice/cache/output.pdf';
        Http::fake([
            '*/coauthoring/CommandService.ashx' => Http::response(['error' => 4], 200),
            '*/ConvertService.ashx' => Http::response([
                'endConvert' => true,
                'fileUrl' => $pdfDownloadUrl,
                'percent' => 100,
            ], 200),
            $pdfDownloadUrl => Http::response('%PDF-2.0 stream content', 200),
        ]);

        $this->actingAs($user);

        $response = $this->get(route('letters.preview-pdf', ['letter' => $letter->id]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('must-revalidate', $response->headers->get('Cache-Control'));
    }

    public function test_docx_header_and_margins_sanitization(): void
    {
        [$workspace, $user, $letter] = $this->createTestLetter();

        /** @var \App\Services\Documents\DocxRenderer $renderer */
        $renderer = app(\App\Services\Documents\DocxRenderer::class);

        // 1. Table-based header without leading <w:br/> -> strips tblpPr, converts anchor to inline, raises w:top, keeps w:bottom
        $tmpDocx = sys_get_temp_dir().'/test_header_margins_'.uniqid().'.docx';
        $zip = new \ZipArchive;
        $zip->open($tmpDocx, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Hello</w:t></w:r></w:p><w:sectPr><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="708"/></w:sectPr></w:body></w:document>');
        $zip->addFromString('word/header1.xml', '<?xml version="1.0" encoding="UTF-8"?><w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"><w:tbl><w:tblPr><w:tblpPr w:leftFromText="180" w:rightFromText="180"/></w:tblPr><w:tr><w:tc><w:p><w:r><w:drawing><wp:anchor behindDoc="1"><wp:extent cx="100" cy="100"/><a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData/></a:graphic></wp:anchor></w:drawing></w:r></w:p></w:tc></w:tr></w:tbl><w:p><w:r><w:t>Header Text</w:t></w:r></w:p></w:hdr>');
        $zip->close();

        $this->assertTrue($renderer->hasWordHeader($tmpDocx));
        $this->assertTrue($renderer->sanitizeHeaderAndMarginsInDocx($tmpDocx, true));

        $zipCheck = new \ZipArchive;
        $zipCheck->open($tmpDocx);
        $docXml = $zipCheck->getFromName('word/document.xml');
        $headerXml = $zipCheck->getFromName('word/header1.xml');
        $zipCheck->close();
        @unlink($tmpDocx);

        $this->assertStringContainsString('w:top="2550"', $docXml);
        $this->assertStringContainsString('w:header="567"', $docXml);
        $this->assertStringContainsString('w:bottom="1440"', $docXml);
        $this->assertStringNotContainsString('w:tblpPr', $headerXml);
        $this->assertStringNotContainsString('wp:anchor', $headerXml);
        $this->assertStringContainsString('wp:inline', $headerXml);
        $this->assertStringContainsString('w:after="200"', $headerXml);

        // 2. Floating header without <w:tbl> and without leading <w:br/> -> preserves <wp:anchor>, leaves header XML intact,
        //    raises w:top to 3304, AND preserves empty <w:p> before <w:tbl>
        $tmpFloating = sys_get_temp_dir().'/test_floating_header_'.uniqid().'.docx';
        $floatingHeaderXml = '<?xml version="1.0" encoding="UTF-8"?><w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"><w:p><w:r><w:drawing><wp:anchor behindDoc="0"><wp:positionH relativeFrom="column"><wp:posOffset>5188585</wp:posOffset></wp:positionH><wp:positionV relativeFrom="page"><wp:posOffset>1996440</wp:posOffset></wp:positionV><wp:extent cx="100" cy="100"/></wp:anchor></w:drawing></w:r></w:p></w:hdr>';
        $emptyParaBeforeTbl = '<w:p><w:pPr><w:spacing w:line="360"/></w:pPr></w:p>';

        $zip2 = new \ZipArchive;
        $zip2->open($tmpFloating, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip2->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>');
        $zip2->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:pPr><w:spacing w:line="360"/></w:pPr><w:r><w:t>Cilacap, 20 November 2026</w:t></w:r></w:p><w:p><w:pPr><w:spacing w:line="360"/></w:pPr><w:r><w:t>Isi Surat Penting</w:t></w:r></w:p>'.$emptyParaBeforeTbl.'<w:tbl><w:tr><w:tc><w:p><w:r><w:t>TTD</w:t></w:r></w:p></w:tc></w:tr></w:tbl><w:sectPr><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="708"/></w:sectPr></w:body></w:document>');
        $zip2->addFromString('word/header1.xml', $floatingHeaderXml);
        $zip2->close();

        $this->assertTrue($renderer->sanitizeHeaderAndMarginsInDocx($tmpFloating, true));

        $zipCheck2 = new \ZipArchive;
        $zipCheck2->open($tmpFloating);
        $docXml2 = $zipCheck2->getFromName('word/document.xml');
        $headerXml2 = $zipCheck2->getFromName('word/header1.xml');
        $zipCheck2->close();
        @unlink($tmpFloating);

        $this->assertStringContainsString('Cilacap, 20 November 2026', $docXml2);
        $this->assertStringContainsString('Isi Surat Penting', $docXml2);
        $this->assertStringContainsString($emptyParaBeforeTbl.'<w:tbl>', $docXml2);
        $this->assertStringContainsString('w:top="3304"', $docXml2);
        $this->assertStringContainsString('w:bottom="1440"', $docXml2);
        $this->assertSame($floatingHeaderXml, $headerXml2);

        // 3. Floating header where P[0] already has manual leading <w:br/><w:br/> -> skipped unless force=true
        $tmpWithBreaks = sys_get_temp_dir().'/test_floating_breaks_'.uniqid().'.docx';
        $zip3 = new \ZipArchive;
        $zip3->open($tmpWithBreaks, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip3->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>');
        $zip3->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:br/><w:br/></w:r><w:r><w:t>Cilacap, 20 November 2026</w:t></w:r></w:p><w:sectPr><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="708"/></w:sectPr></w:body></w:document>');
        $zip3->addFromString('word/header1.xml', $floatingHeaderXml);
        $zip3->close();

        $this->assertFalse($renderer->sanitizeHeaderAndMarginsInDocx($tmpWithBreaks, true));
        $zipCheck3 = new \ZipArchive;
        $zipCheck3->open($tmpWithBreaks);
        $docXml3 = $zipCheck3->getFromName('word/document.xml');
        $zipCheck3->close();
        $this->assertStringContainsString('w:top="1440"', $docXml3);

        // Opt-in force=true explicitly aligns w:top
        $this->assertTrue($renderer->sanitizeHeaderAndMarginsInDocx($tmpWithBreaks, true, force: true));
        $zipCheck3b = new \ZipArchive;
        $zipCheck3b->open($tmpWithBreaks);
        $docXml3b = $zipCheck3b->getFromName('word/document.xml');
        $zipCheck3b->close();
        @unlink($tmpWithBreaks);
        $this->assertStringContainsString('w:top="3304"', $docXml3b);

        // 4. Workspace-Kop template without word/header*.xml -> untouched
        $tmpNoHeader = sys_get_temp_dir().'/test_no_header_'.uniqid().'.docx';
        $zip4 = new \ZipArchive;
        $zip4->open($tmpNoHeader, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip4->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>');
        $zip4->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Halo</w:t></w:r></w:p><w:sectPr><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="708"/></w:sectPr></w:body></w:document>');
        $zip4->close();

        $this->assertFalse($renderer->hasWordHeader($tmpNoHeader));
        $this->assertFalse($renderer->sanitizeHeaderAndMarginsInDocx($tmpNoHeader, true));

        $zipCheck4 = new \ZipArchive;
        $zipCheck4->open($tmpNoHeader);
        $docXml4 = $zipCheck4->getFromName('word/document.xml');
        $zipCheck4->close();
        @unlink($tmpNoHeader);

        $this->assertStringContainsString('w:top="1440"', $docXml4);
        $this->assertStringContainsString('w:header="708"', $docXml4);
    }

    public function test_lampiran_page_break_uses_br_page_without_page_break_before(): void
    {
        // 1. Legacy docx with Lampiran paragraph that has pageBreakBefore and extra breaks -> normalized to one clean break
        $tmpDocx = sys_get_temp_dir().'/test_lampiran_'.uniqid().'.docx';
        $zip = new \ZipArchive;
        $zip->open($tmpDocx, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl><w:tr><w:tc><w:p><w:r><w:t>Signature</w:t></w:r></w:p></w:tc></w:tr></w:tbl><w:p><w:r><w:br w:type="page"/></w:r></w:p><w:p><w:pPr><w:spacing/></w:pPr></w:p><w:p><w:pPr><w:pageBreakBefore/></w:pPr><w:r><w:br/><w:br/><w:t>Lampiran I</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        /** @var \App\Services\Documents\DocxRenderer $renderer */
        $renderer = app(\App\Services\Documents\DocxRenderer::class);
        $reflection = new \ReflectionMethod($renderer, 'ensureLampiranPageBreakInXml');
        $reflection->invoke($renderer, $tmpDocx);

        $zipCheck = new \ZipArchive;
        $zipCheck->open($tmpDocx);
        $docXml = $zipCheck->getFromName('word/document.xml');
        $zipCheck->close();
        @unlink($tmpDocx);

        $this->assertStringNotContainsString('w:pageBreakBefore', $docXml);
        $this->assertStringNotContainsString('<w:br/><w:br/><w:t>Lampiran I</w:t>', $docXml);

        $expectedBreak = '<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="240" w:lineRule="auto"/></w:pPr><w:r><w:br w:type="page"/></w:r></w:p>';
        $this->assertStringContainsString($expectedBreak, $docXml);

        // 2. Clean docx WITHOUT pageBreakBefore that already has <w:br w:type="page"/> + intentional user Enter before Lampiran I -> preserved verbatim
        $tmpClean = sys_get_temp_dir().'/test_lampiran_clean_'.uniqid().'.docx';
        $cleanDocXml = '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl><w:tr><w:tc><w:p><w:r><w:t>Signature</w:t></w:r></w:p></w:tc></w:tr></w:tbl><w:p><w:r><w:br w:type="page"/></w:r></w:p><w:p><w:pPr><w:spacing w:line="360"/></w:pPr></w:p><w:p><w:r><w:t>Lampiran I</w:t></w:r></w:p></w:body></w:document>';
        $zip2 = new \ZipArchive;
        $zip2->open($tmpClean, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip2->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>');
        $zip2->addFromString('word/document.xml', $cleanDocXml);
        $zip2->close();

        $reflection->invoke($renderer, $tmpClean);

        $zipCheck2 = new \ZipArchive;
        $zipCheck2->open($tmpClean);
        $docXml2 = $zipCheck2->getFromName('word/document.xml');
        $zipCheck2->close();
        @unlink($tmpClean);

        $this->assertSame($cleanDocXml, $docXml2);
    }

    public function test_generated_letter_preserves_exact_template_margins_and_enters(): void
    {
        Storage::fake('local');

        $workspace = Workspace::create([
            'name' => 'HIMATRIS PNC',
            'slug' => 'himatris-pnc',
            'number_pattern' => '{seq}/{kode}/{bulan_romawi}/{tahun}',
            'number_code' => 'HIMATRIS',
            'seq_padding' => 3,
            'kop_header_text' => "KEMENTERIAN PENDIDIKAN\nHIMPUNAN MAHASISWA\nPOLITEKNIK NEGERI CILACAP",
            'kop_address' => 'Jalan Dr. Soetomo No.1, Cilacap',
        ]);

        $user = User::factory()->create(['current_workspace_id' => $workspace->id]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Secretary,
        ]);

        /** @var \App\Services\Documents\DocxRenderer $renderer */
        $renderer = app(\App\Services\Documents\DocxRenderer::class);

        // --- Case A: Embedded Kop template (with word/header1.xml, w:top="1440", leading <w:br/><w:br/>, and Enters before signature & table) ---
        $embeddedRel = "templates/workspaces/{$workspace->id}/embedded-parity.docx";
        $embeddedAbs = Storage::disk('local')->path($embeddedRel);
        SampleDocxBuilder::create($embeddedAbs);

        $headerXmlOrig = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing">'
            .'<w:p><w:r><w:drawing><wp:anchor behindDoc="0"><wp:positionV relativeFrom="page"><wp:posOffset>1909445</wp:posOffset></wp:positionV><wp:extent cx="100" cy="100"/></wp:anchor></w:drawing></w:r></w:p>'
            .'</w:hdr>';

        $embeddedDocXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'
            .'<w:p><w:r><w:br/><w:br/></w:r><w:r><w:t>Cilacap, ${tanggal_surat}</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>Nomor: ${nomor_surat}</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>Penutup surat kepada ${tujuan_surat}.</w:t></w:r></w:p>'
            .'<w:p><w:r><w:br/></w:r></w:p>'
            .'<w:p><w:r><w:t>${signature_block}</w:t></w:r></w:p>'
            .'<w:p><w:r><w:br w:type="page"/></w:r></w:p>'
            .'<w:p><w:r><w:t>Lampiran I</w:t></w:r></w:p>'
            .'<w:p><w:pPr><w:spacing w:line="360"/></w:pPr></w:p>'
            .'<w:tbl><w:tr><w:tc><w:p><w:r><w:t>Item</w:t></w:r></w:p></w:tc></w:tr><w:tr><w:tc><w:p><w:r><w:t>${item.nama}</w:t></w:r></w:p></w:tc></w:tr></w:tbl>'
            .'<w:sectPr><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr>'
            .'</w:body></w:document>';

        $zipA = new \ZipArchive;
        $zipA->open($embeddedAbs);
        $zipA->addFromString('word/document.xml', $embeddedDocXml);
        $zipA->addFromString('word/header1.xml', $headerXmlOrig);
        $zipA->close();

        $tplEmbedded = LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'scope' => \App\Enums\TemplateScope::Workspace,
            'name' => 'Template Kop Mandiri Parity',
            'file_path' => $embeddedRel,
            'variables_schema' => [
                ['name' => 'tujuan_surat', 'type' => 'text', 'label' => 'Tujuan Surat'],
                ['name' => 'item', 'type' => 'repeater', 'columns' => [['name' => 'nama', 'token' => 'item.nama']]],
            ],
            'has_embedded_kop' => true,
            'version' => 1,
        ]);

        $letterA = Letter::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'template_id' => $tplEmbedded->id,
            'created_by' => $user->id,
            'status' => LetterStatus::Draft,
            'reference_number' => '001/HIMATRIS/XI/2026',
            'perihal' => 'Uji Paritas Kop Mandiri',
            'letter_date' => now(),
            'field_values' => [
                'tujuan_surat' => 'Dekan',
                'item' => [['nama' => 'Proyektor']],
                '_signers' => [
                    ['position' => 'Ketua,', 'name' => 'Dimas', 'id_label' => 'NIM.', 'id_number' => '12345'],
                ],
            ],
            'version' => 1,
        ]);

        $outRelA = $renderer->renderSingle($letterA);
        $outZipA = new \ZipArchive;
        $outZipA->open(Storage::disk('local')->path($outRelA));
        $outDocXmlA = $outZipA->getFromName('word/document.xml');
        $outHeaderXmlA = $outZipA->getFromName('word/header1.xml');
        $outZipA->close();

        // Exact pgMar & header1.xml preservation
        $this->assertStringContainsString('w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="708"', $outDocXmlA);
        $this->assertSame($headerXmlOrig, $outHeaderXmlA);
        $this->assertStringContainsString('<w:br/><w:br/>', $outDocXmlA);

        // Exact Enter paragraph before signature table and before repeater table preserved
        $domA = new \DOMDocument;
        $domA->loadXML($outDocXmlA);
        $bodyChildrenA = [];
        foreach ($domA->getElementsByTagName('body')->item(0)->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $bodyChildrenA[] = $child;
            }
        }
        // [0]=Cilacap, [1]=Nomor, [2]=Penutup, [3]=<w:p><w:r><w:br/></w:r></w:p>, [4]=signature <w:tbl>, [5]=pageBreak, [6]=Lampiran I, [7]=empty spacing <w:p>, [8]=repeater <w:tbl>, [9]=sectPr
        $this->assertSame('p', $bodyChildrenA[3]->localName);
        $this->assertStringContainsString('<w:br/>', $domA->saveXML($bodyChildrenA[3]));
        $this->assertSame('tbl', $bodyChildrenA[4]->localName);
        $this->assertStringContainsString('Dimas', $bodyChildrenA[4]->textContent);
        $this->assertSame('p', $bodyChildrenA[7]->localName);
        $this->assertSame('', trim($bodyChildrenA[7]->textContent));
        $this->assertSame('tbl', $bodyChildrenA[8]->localName);
        $this->assertStringContainsString('Proyektor', $bodyChildrenA[8]->textContent);

        // --- Case B: Workspace Kop template (no word/header*.xml) with Lampiran I ---
        $wsRel = "templates/workspaces/{$workspace->id}/ws-kop-parity.docx";
        $wsAbs = Storage::disk('local')->path($wsRel);
        SampleDocxBuilder::create($wsAbs);

        $wsDocXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'
            .'<w:p><w:r><w:t>Cilacap, ${tanggal_surat}</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>${signature_block}</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>Lampiran I</w:t></w:r></w:p>'
            .'<w:tbl><w:tr><w:tc><w:p><w:r><w:t>Nama</w:t></w:r></w:p></w:tc></w:tr><w:tr><w:tc><w:p><w:r><w:t>${item.nama}</w:t></w:r></w:p></w:tc></w:tr></w:tbl>'
            .'<w:sectPr><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr>'
            .'</w:body></w:document>';

        $zipB = new \ZipArchive;
        $zipB->open($wsAbs);
        $zipB->addFromString('word/document.xml', $wsDocXml);
        $zipB->close();

        $tplWs = LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'scope' => \App\Enums\TemplateScope::Workspace,
            'name' => 'Template Kop Workspace Parity',
            'file_path' => $wsRel,
            'variables_schema' => [
                ['name' => 'item', 'type' => 'repeater', 'columns' => [['name' => 'nama', 'token' => 'item.nama']]],
            ],
            'has_embedded_kop' => false,
            'version' => 1,
        ]);

        $letterB = Letter::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'template_id' => $tplWs->id,
            'created_by' => $user->id,
            'status' => LetterStatus::Draft,
            'reference_number' => '002/HIMATRIS/XI/2026',
            'perihal' => 'Uji Paritas Kop Workspace',
            'letter_date' => now(),
            'field_values' => [
                'item' => [['nama' => 'Sound System']],
                '_signers' => [
                    ['position' => 'Ketua,', 'name' => 'Dimas', 'id_label' => 'NIM.', 'id_number' => '12345'],
                ],
            ],
            'version' => 1,
        ]);

        $outRelB = $renderer->renderSingle($letterB);
        $outZipB = new \ZipArchive;
        $outZipB->open(Storage::disk('local')->path($outRelB));
        $outDocXmlB = $outZipB->getFromName('word/document.xml');
        $outZipB->close();

        // Top margin must remain 1440 (NOT inflated to 2550!)
        $this->assertStringContainsString('w:top="1440"', $outDocXmlB);

        // Verify order: Signature <w:tbl> -> Page Break -> Kop Page 2 <w:tbl> -> Spacer -> Lampiran I (and only 1 page break total)
        $domB = new \DOMDocument;
        $domB->loadXML($outDocXmlB);
        $sigIdx = null;
        $pbIndices = [];
        $kop2Idx = null;
        $lampiranIdx = null;
        $idx = 0;
        foreach ($domB->getElementsByTagName('body')->item(0)->childNodes as $child) {
            if (! $child instanceof \DOMElement) {
                continue;
            }
            $nodeXml = $domB->saveXML($child);
            if ($child->localName === 'tbl' && str_contains($child->textContent, 'Dimas')) {
                $sigIdx = $idx;
            }
            if ($child->localName === 'p' && str_contains($nodeXml, 'w:type="page"')) {
                $pbIndices[] = $idx;
            }
            if ($idx > 0 && $child->localName === 'tbl' && str_contains($nodeXml, 'w:val="double"')) {
                $kop2Idx = $idx;
            }
            if ($child->localName === 'p' && str_contains($child->textContent, 'Lampiran I')) {
                $lampiranIdx = $idx;
            }
            $idx++;
        }

        $this->assertNotNull($sigIdx);
        $this->assertCount(1, $pbIndices, 'Harus ada tepat 1 page break sebelum Kop Halaman 2');
        $this->assertNotNull($kop2Idx);
        $this->assertNotNull($lampiranIdx);
        $this->assertLessThan($pbIndices[0], $sigIdx);
        $this->assertLessThan($kop2Idx, $pbIndices[0], 'Kop Halaman 2 harus berada SESUDAH page break (di Halaman 2)');
        $this->assertLessThan($lampiranIdx, $kop2Idx, 'Kop Halaman 2 harus berada tepat di atas Lampiran I');
    }

    public function test_signature_block_inherits_font_and_size_from_template(): void
    {
        /** @var \App\Services\Documents\DocxRenderer $renderer */
        $renderer = app(\App\Services\Documents\DocxRenderer::class);
        $reflection = new \ReflectionMethod($renderer, 'injectDynamicSignatureTableToXml');

        $signers = [
            ['prefix' => 'Mengetahui,', 'position' => 'Ketua HIMATRIS,', 'name' => 'Dimas Surya Pratama', 'id_label' => 'NIM.', 'id_number' => '25.01.09.044'],
            ['position' => 'Ketua Pelaksana,', 'name' => 'Karisma Putri Isabela', 'id_label' => 'NIM.', 'id_number' => '25.03.15.020'],
        ];

        $calibriNormalStyles = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:asciiTheme="minorHAnsi" w:hAnsiTheme="minorHAnsi"/><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr></w:rPrDefault></w:docDefaults>'
            .'<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr></w:style>'
            .'<w:style w:type="paragraph" w:styleId="BodyText"><w:name w:val="Body Text"/><w:basedOn w:val="Normal"/><w:rPr><w:rFonts w:ascii="Times New Roman" w:eastAsia="Times New Roman" w:hAnsi="Times New Roman" w:cs="Times New Roman"/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="id"/></w:rPr></w:style>'
            .'</w:styles>';

        // 1. Direct Run Formatting (e.g., Template 10: Times New Roman 12pt over Normal Calibri 11pt)
        $tmpDirect = sys_get_temp_dir().'/test_sig_direct_'.uniqid().'.docx';
        $zip1 = new \ZipArchive;
        $zip1->open($tmpDirect, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip1->addFromString('word/styles.xml', $calibriNormalStyles);
        $zip1->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml"><w:body><w:p w14:paraId="30B529F1"><w:pPr><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:cs="Times New Roman"/><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr></w:pPr><w:r><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:cs="Times New Roman"/><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr><w:t>${signature_block}</w:t></w:r></w:p></w:body></w:document>');
        $zip1->close();

        $reflection->invoke($renderer, $tmpDirect, $signers);

        $check1 = new \ZipArchive;
        $check1->open($tmpDirect);
        $xml1 = $check1->getFromName('word/document.xml');
        $check1->close();
        @unlink($tmpDirect);

        $this->assertStringContainsString('w:ascii="Times New Roman"', $xml1);
        $this->assertStringContainsString('w:sz w:val="24"', $xml1);
        $this->assertStringContainsString('w:szCs w:val="24"', $xml1);
        $this->assertStringNotContainsString('Calibri', $xml1);
        $this->assertStringNotContainsString('w:sz w:val="22"', $xml1);
        $this->assertStringContainsString('Ketua HIMATRIS,', $xml1);
        $this->assertStringContainsString('<w:b/><w:bCs/>', $xml1);
        $this->assertStringContainsString('<w:u w:val="single"/>', $xml1);

        // Verify each signer cell has 4 explicit blank signature spacer paragraphs carrying template font,
        // and that both paired cells have equal paragraph counts when only left signer has a prefix
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $xml1, $cells1);
        $this->assertCount(2, $cells1[0]);
        $expectedBlankP = '<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="240" w:lineRule="auto"/><w:jc w:val="center"/><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:cs="Times New Roman" w:eastAsia="Times New Roman"/><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr></w:pPr></w:p>';
        $this->assertSame(4, substr_count($cells1[0][0], $expectedBlankP));
        $this->assertSame(4, substr_count($cells1[0][1], $expectedBlankP));
        $this->assertSame(substr_count($cells1[0][0], '<w:p>'), substr_count($cells1[0][1], '<w:p>'));
        $this->assertStringNotContainsString('<w:noWrap/>', $xml1);

        // 2. Paragraph Style Inheritance (e.g., Templates 3, 5, 11: pStyle=BodyText Times New Roman 12pt over Normal Calibri 11pt)
        $tmpStyle = sys_get_temp_dir().'/test_sig_style_'.uniqid().'.docx';
        $zip2 = new \ZipArchive;
        $zip2->open($tmpStyle, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip2->addFromString('word/styles.xml', $calibriNormalStyles);
        $zip2->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:pPr><w:pStyle w:val="BodyText"/></w:pPr><w:r><w:t>${signature_block}</w:t></w:r></w:p></w:body></w:document>');
        $zip2->close();

        $reflection->invoke($renderer, $tmpStyle, $signers);

        $check2 = new \ZipArchive;
        $check2->open($tmpStyle);
        $xml2 = $check2->getFromName('word/document.xml');
        $check2->close();
        @unlink($tmpStyle);

        $this->assertStringContainsString('w:ascii="Times New Roman"', $xml2);
        $this->assertStringContainsString('w:sz w:val="24"', $xml2);
        $this->assertStringNotContainsString('Calibri', $xml2);

        // 3. Document Defaults Fallback (e.g., Template 2: Arial 10pt in docDefaults)
        $arialDefaultsStyles = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/><w:color w:val="111111"/><w:sz w:val="20"/><w:szCs w:val="20"/></w:rPr></w:rPrDefault></w:docDefaults>'
            .'<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
            .'</w:styles>';

        $tmpDefaults = sys_get_temp_dir().'/test_sig_defaults_'.uniqid().'.docx';
        $zip3 = new \ZipArchive;
        $zip3->open($tmpDefaults, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip3->addFromString('word/styles.xml', $arialDefaultsStyles);
        $zip3->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:pPr/><w:r><w:rPr/><w:t>${signature_block}</w:t></w:r></w:p></w:body></w:document>');
        $zip3->close();

        $reflection->invoke($renderer, $tmpDefaults, [$signers[0]]);

        $check3 = new \ZipArchive;
        $check3->open($tmpDefaults);
        $xml3 = $check3->getFromName('word/document.xml');
        $check3->close();
        @unlink($tmpDefaults);

        $this->assertStringContainsString('w:ascii="Arial"', $xml3);
        $this->assertStringContainsString('w:sz w:val="20"', $xml3);
        $this->assertStringContainsString('w:color w:val="111111"', $xml3);

        // 4. Static ttd_kiri table replacement inherits font & size from ttd_kiri cell
        $tmpTable = sys_get_temp_dir().'/test_sig_tbl_'.uniqid().'.docx';
        $zip4 = new \ZipArchive;
        $zip4->open($tmpTable, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip4->addFromString('word/styles.xml', $calibriNormalStyles);
        $zip4->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl><w:tr><w:tc><w:p><w:r><w:rPr><w:rFonts w:ascii="Georgia" w:hAnsi="Georgia"/><w:sz w:val="26"/></w:rPr><w:t>${ttd_kiri_jabatan}</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:body></w:document>');
        $zip4->close();

        $reflection->invoke($renderer, $tmpTable, $signers);

        $check4 = new \ZipArchive;
        $check4->open($tmpTable);
        $xml4 = $check4->getFromName('word/document.xml');
        $check4->close();
        @unlink($tmpTable);

        $this->assertStringContainsString('w:ascii="Georgia"', $xml4);
        $this->assertStringContainsString('w:sz w:val="26"', $xml4);
        $this->assertStringContainsString('w:szCs w:val="26"', $xml4);
        $this->assertStringNotContainsString('ttd_kiri_jabatan', $xml4);
    }

    public function test_onlyoffice_callback_rejects_unauthorized(): void
    {
        [$workspace, $user, $letter] = $this->createTestLetter();

        $response = $this->postJson(
            route('onlyoffice.callback', ['letter' => $letter->id, 'token' => 'invalid-token']),
            ['status' => 2, 'url' => 'http://example.com/file.docx']
        );

        $response->assertStatus(403);
    }
}
