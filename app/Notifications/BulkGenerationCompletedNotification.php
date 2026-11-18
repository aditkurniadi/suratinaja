<?php

namespace App\Notifications;

use App\Models\BulkJob;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BulkGenerationCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly BulkJob $bulkJob,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'bulk_job_id' => $this->bulkJob->id,
            'workspace_id' => $this->bulkJob->workspace_id,
            'perihal' => $this->bulkJob->perihal,
            'status' => $this->bulkJob->status->value,
            'recipient_count' => $this->bulkJob->recipient_count,
            'message' => "Bulk generation '{$this->bulkJob->perihal}' ({$this->bulkJob->recipient_count} penerima) telah selesai.",
        ];
    }
}
