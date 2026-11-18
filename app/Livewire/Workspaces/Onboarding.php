<?php

namespace App\Livewire\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Support\CurrentWorkspace;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Setup Workspace')]
class Onboarding extends Component
{
    public string $name = '';

    public string $code = '';

    public function create(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'code' => ['nullable', 'string', 'max:40'],
        ]);

        $user = auth()->user();

        DB::transaction(function () use ($user) {
            $ws = Workspace::create([
                'name' => trim($this->name),
                'number_code' => trim($this->code) ?: null,
            ]);

            WorkspaceMember::create([
                'workspace_id' => $ws->id,
                'user_id' => $user->id,
                'role' => WorkspaceRole::Admin,
                'joined_at' => now(),
            ]);

            $user->forceFill(['current_workspace_id' => $ws->id])->save();
            app(CurrentWorkspace::class)->set($ws);
        });

        $this->redirect(route('dashboard'), navigate: false);
    }

    public function acceptPending(int $invitationId): void
    {
        $user = auth()->user();
        $invitation = WorkspaceInvitation::where('id', $invitationId)
            ->where('email', $user->email)
            ->where('status', 'pending')
            ->firstOrFail();

        DB::transaction(function () use ($user, $invitation) {
            WorkspaceMember::firstOrCreate(
                ['workspace_id' => $invitation->workspace_id, 'user_id' => $user->id],
                ['role' => $invitation->role, 'joined_at' => now()]
            );

            $invitation->forceFill(['status' => 'accepted', 'accepted_at' => now()])->save();
            $user->forceFill(['current_workspace_id' => $invitation->workspace_id])->save();
        });

        $this->redirect(route('dashboard'), navigate: false);
    }

    public function render()
    {
        $user = auth()->user();
        $invitations = WorkspaceInvitation::with('workspace')
            ->where('email', $user->email)
            ->where('status', 'pending')
            ->get();

        return view('livewire.workspaces.onboarding', [
            'invitations' => $invitations,
        ]);
    }
}
