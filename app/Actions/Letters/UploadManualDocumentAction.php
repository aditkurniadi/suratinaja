<?php

namespace App\Actions\Letters;

use App\Enums\LetterStatus;
use App\Exceptions\DomainException;
use App\Models\Letter;
use App\Models\LetterActivity;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadManualDocumentAction
{
    public function execute(Letter $letter, User $actor, UploadedFile $file): Letter
    {
        if (! in_array($letter->status, [LetterStatus::Draft, LetterStatus::MenungguTtd], true)) {
            throw DomainException::workspaceForbidden('Tidak dapat mengunggah berkas pada surat yang sudah ditandatangani.');
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'pdf');
        $dir = "letters/workspaces/{$letter->workspace_id}/manual";
        $filename = "letter-{$letter->id}-v{$letter->version}.{$extension}";
        Storage::disk('local')->putFileAs($dir, $file, $filename);
        $path = "{$dir}/{$filename}";

        $update = [];
        if ($extension === 'pdf') {
            $update['preview_pdf_path'] = $path;
        } else {
            $update['generated_file_path'] = $path;
        }
        $letter->forceFill($update)->saveQuietly();

        LetterActivity::withoutGlobalScopes()->create([
            'letter_id' => $letter->id,
            'workspace_id' => $letter->workspace_id,
            'actor_id' => $actor->id,
            'action' => 'document_uploaded',
            'payload' => [
                'filename' => $file->getClientOriginalName(),
                'path' => $path,
                'extension' => $extension,
            ],
            'created_at' => now(),
        ]);

        return $letter->fresh();
    }
}
