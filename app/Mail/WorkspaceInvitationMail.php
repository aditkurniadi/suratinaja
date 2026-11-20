<?php

namespace App\Mail;

use App\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkspaceInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $inviteUrl;

    public function __construct(
        public readonly WorkspaceInvitation $invitation,
    ) {
        $this->inviteUrl = route('invitations.accept', ['token' => $invitation->token]);
    }

    public function envelope(): Envelope
    {
        $wsName = $this->invitation->workspace?->name ?? 'SuratinAja';

        return new Envelope(
            subject: "Undangan Bergabung ke {$wsName} di SuratinAja",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.workspace-invitation',
            with: [
                'invitation' => $this->invitation,
                'workspace' => $this->invitation->workspace,
                'inviter' => $this->invitation->inviter,
                'inviteUrl' => $this->inviteUrl,
                'roleName' => $this->invitation->getRoleDisplayName(),
            ],
        );
    }
}
