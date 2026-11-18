<?php

namespace App\Livewire\Workspaces;

use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Undangan Workspace')]
class AcceptInvitation extends Component
{
    public WorkspaceInvitation $invitation;

    public function mount(string $token): void
    {
        $this->invitation = WorkspaceInvitation::with('workspace')
            ->where('token', $token)
            ->firstOrFail();
    }

    public function accept(): void
    {
        if (! $this->invitation->isPending()) {
            $this->addError('token', 'Undangan ini sudah kedaluwarsa atau tidak berlaku lagi.');

            return;
        }

        $user = auth()->user();

        DB::transaction(function () use ($user) {
            WorkspaceMember::firstOrCreate(
                [
                    'workspace_id' => $this->invitation->workspace_id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => $this->invitation->role,
                    'joined_at' => now(),
                ]
            );

            $this->invitation->forceFill([
                'status' => 'accepted',
                'accepted_at' => now(),
            ])->save();

            $user->forceFill(['current_workspace_id' => $this->invitation->workspace_id])->save();
        });

        $this->redirect(route('dashboard'), navigate: false);
    }

    public function render()
    {
        return view('livewire.workspaces.accept-invitation');
    }
}
