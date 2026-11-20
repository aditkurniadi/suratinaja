<?php

use App\Http\Controllers\Api\V1\BulkLetterController;
use App\Http\Controllers\Api\V1\LetterController;
use App\Http\Controllers\LetterDocumentController;
use App\Livewire\Admin\GlobalTemplatesIndex;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Bulk\BulkGenerator;
use App\Livewire\Dashboard;
use App\Livewire\Letters\LetterComposer;
use App\Livewire\Letters\LetterShow;
use App\Livewire\Letters\LettersIndex;
use App\Livewire\Settings\KopBuilder;
use App\Livewire\Settings\MembersIndex;
use App\Livewire\Settings\NumberingSettings;
use App\Livewire\Templates\TemplateEditor;
use App\Livewire\Templates\TemplatesIndex;
use App\Livewire\Workspaces\AcceptInvitation;
use App\Livewire\Workspaces\Onboarding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
});

Route::post('/logout', function (Request $request) {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::get('/invitations/{token}', AcceptInvitation::class)->name('invitations.accept');

Route::middleware('auth')->group(function () {
    Route::get('/onboarding', Onboarding::class)->name('workspaces.onboarding');
});

Route::middleware(['auth', 'workspace.active'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::get('/letters', LettersIndex::class)->name('letters.index');
    Route::get('/calendar', \App\Livewire\Calendar\CalendarIndex::class)->name('calendar.index');
    Route::get('/letters/create', LetterComposer::class)->name('letters.create');
    Route::get('/letters/{letter}/edit', LetterComposer::class)->name('letters.edit');
    Route::get('/letters/{letter}', LetterShow::class)->name('letters.show');
    Route::get('/letters/{letter}/download', [LetterDocumentController::class, 'downloadLetter'])->name('letters.download');
    Route::get('/letters/{letter}/preview.pdf', [LetterDocumentController::class, 'previewPdf'])->name('letters.preview-pdf');
    Route::get('/letters/{letter}/envelope/preview', [\App\Http\Controllers\EnvelopeDocumentController::class, 'preview'])->name('letters.envelope.preview');
    Route::get('/letters/{letter}/envelope/download', [\App\Http\Controllers\EnvelopeDocumentController::class, 'download'])->name('letters.envelope.download');

    Route::get('/bulk', BulkGenerator::class)->name('bulk.index');
    Route::get('/bulk/{bulkJob}/download', [LetterDocumentController::class, 'downloadBulk'])->name('bulk.download');
    Route::get('/bulk/export-pdf/{bulkJobId}', [\App\Http\Controllers\BulkExportController::class, 'exportBatchPdf'])->name('bulk.export.pdf');
    Route::get('/bulk/export-docx/{bulkJobId}', [\App\Http\Controllers\BulkExportController::class, 'downloadBatchDocx'])->name('bulk.export.docx');
    Route::get('/bulk/envelopes/{bulkJobId}', [\App\Http\Controllers\EnvelopeDocumentController::class, 'downloadBulk'])->name('bulk.envelopes.download');

    Route::get('/surat-masuk', \App\Livewire\SuratMasuk\SuratMasukIndex::class)->name('surat-masuk.index');
    Route::get('/surat-masuk/create', \App\Livewire\SuratMasuk\SuratMasukForm::class)->name('surat-masuk.create');
    Route::get('/surat-masuk/export', [\App\Http\Controllers\SuratMasukDocumentController::class, 'exportAgenda'])->name('surat-masuk.export');
    Route::get('/surat-masuk/{suratMasuk}', \App\Livewire\SuratMasuk\SuratMasukShow::class)->name('surat-masuk.show');
    Route::get('/surat-masuk/{suratMasuk}/edit', \App\Livewire\SuratMasuk\SuratMasukForm::class)->name('surat-masuk.edit');
    Route::get('/surat-masuk/{suratMasuk}/download', [\App\Http\Controllers\SuratMasukDocumentController::class, 'downloadFile'])->name('surat-masuk.download');
    Route::get('/surat-masuk/{suratMasuk}/preview', [\App\Http\Controllers\SuratMasukDocumentController::class, 'previewFile'])->name('surat-masuk.preview');

    Route::get('/templates', TemplatesIndex::class)->name('templates.index');
    Route::get('/templates/{template}/edit', TemplateEditor::class)->name('templates.edit');
    Route::get('/templates/{template}/download', [LetterDocumentController::class, 'downloadTemplate'])->name('templates.download');

    Route::get('/settings/kop', KopBuilder::class)->name('settings.kop');
    Route::get('/settings/envelope-templates', \App\Livewire\Settings\EnvelopeTemplatesIndex::class)->name('settings.envelopes');
    Route::get('/settings/envelope-templates/{envelopeTemplate}/edit', \App\Livewire\Templates\EnvelopeTemplateEditor::class)->name('settings.envelopes.edit');
    Route::get('/settings/numbering', NumberingSettings::class)->name('settings.numbering');
    Route::get('/settings/letter-types', \App\Livewire\Settings\LetterTypesSettings::class)->name('settings.letter-types');
    Route::get('/settings/members', MembersIndex::class)->name('settings.members');

    Route::get('/admin/global-templates', GlobalTemplatesIndex::class)->name('admin.global-templates');
});

/*
|--------------------------------------------------------------------------
| JSON API v1 (Session-based auth + CSRF per docs/API.md §1–§3)
|--------------------------------------------------------------------------
*/
Route::prefix('api/v1')
    ->middleware(['auth'])
    ->group(function () {
        Route::middleware('workspace.member')->group(function () {
            Route::get('/workspaces/{workspace}/letters', [LetterController::class, 'index'])
                ->name('api.v1.letters.index');
        });

        Route::middleware('workspace.member:write')->group(function () {
            Route::post('/workspaces/{workspace}/letters', [LetterController::class, 'store'])
                ->name('api.v1.letters.store');
            Route::patch('/workspaces/{workspace}/letters/{letter}/status', [LetterController::class, 'updateStatus'])
                ->name('api.v1.letters.status');
            Route::post('/workspaces/{workspace}/letters/{letter}/revise', [LetterController::class, 'revise'])
                ->name('api.v1.letters.revise');
            Route::post('/workspaces/{workspace}/letters/bulk', [BulkLetterController::class, 'store'])
                ->name('api.v1.letters.bulk');
        });

        Route::get('/jobs/{bulkJob}', [BulkLetterController::class, 'showJob'])
            ->name('api.v1.jobs.show');
    });

/*
|--------------------------------------------------------------------------
| OnlyOffice Document Server Endpoints
|--------------------------------------------------------------------------
*/
Route::get('/api/onlyoffice/download/{letter}', [\App\Http\Controllers\Api\OnlyOfficeController::class, 'download'])
    ->name('onlyoffice.download');
Route::match(['get', 'post'], '/api/onlyoffice/callback/{letter}', [\App\Http\Controllers\Api\OnlyOfficeController::class, 'callback'])
    ->name('onlyoffice.callback');

Route::get('/api/onlyoffice/templates/download/{templateId}', [\App\Http\Controllers\Api\OnlyOfficeController::class, 'downloadTemplate'])
    ->name('onlyoffice.templates.download');
Route::match(['get', 'post'], '/api/onlyoffice/templates/callback/{templateId}', [\App\Http\Controllers\Api\OnlyOfficeController::class, 'callbackTemplate'])
    ->name('onlyoffice.templates.callback');

Route::get('/api/onlyoffice/envelopes/download/{templateId}', [\App\Http\Controllers\Api\OnlyOfficeController::class, 'downloadEnvelopeTemplate'])
    ->name('onlyoffice.envelopes.download');
Route::match(['get', 'post'], '/api/onlyoffice/envelopes/callback/{templateId}', [\App\Http\Controllers\Api\OnlyOfficeController::class, 'callbackEnvelopeTemplate'])
    ->name('onlyoffice.envelopes.callback');

Route::get('/api/onlyoffice/kop-master/download/{workspaceId}', [\App\Http\Controllers\Api\OnlyOfficeController::class, 'downloadKopMaster'])
    ->name('onlyoffice.kop-master.download');
Route::match(['get', 'post'], '/api/onlyoffice/kop-master/callback/{workspaceId}', [\App\Http\Controllers\Api\OnlyOfficeController::class, 'callbackKopMaster'])
    ->name('onlyoffice.kop-master.callback');

Route::get('/api/onlyoffice/raw-download', [\App\Http\Controllers\Api\OnlyOfficeController::class, 'rawDownload'])
    ->name('onlyoffice.raw-download');

Route::get('/api/onlyoffice/plugins/suratinaja-variables/config.json', [\App\Http\Controllers\Api\OnlyOfficeController::class, 'pluginConfig'])
    ->name('onlyoffice.plugins.variables.config');
Route::get('/api/onlyoffice/plugins/suratinaja-variables/index.html', [\App\Http\Controllers\Api\OnlyOfficeController::class, 'pluginIndex'])
    ->name('onlyoffice.plugins.variables.index');
Route::get('/api/onlyoffice/plugins/suratinaja-variables/icon.svg', [\App\Http\Controllers\Api\OnlyOfficeController::class, 'pluginIcon'])
    ->name('onlyoffice.plugins.variables.icon');
