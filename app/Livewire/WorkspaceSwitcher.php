<?php

namespace App\Livewire;

use App\Enums\WorkspaceRole;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\CurrentWorkspace;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class WorkspaceSwitcher extends Component
{
    public bool $showCreateModal = false;

    public string $newWorkspaceName = '';

    public string $newWorkspaceCode = '';

    public function switchTo(int $workspaceId): void
    {
        $user = auth()->user();
        $workspace = Workspace::find($workspaceId);

        if (! $user || ! $workspace || ! $workspace->hasMember($user)) {
            abort(403);
        }

        $user->switchWorkspace($workspace);
        $this->redirect(route('dashboard'), navigate: false);
    }

    public function createWorkspace(): void
    {
        $this->validate([
            'newWorkspaceName' => ['required', 'string', 'min:2', 'max:120'],
            'newWorkspaceCode' => ['nullable', 'string', 'max:40'],
        ]);

        $user = auth()->user();

        $workspace = DB::transaction(function () use ($user) {
            $ws = Workspace::create([
                'name' => trim($this->newWorkspaceName),
                'number_code' => trim($this->newWorkspaceCode) ?: null,
                'number_pattern' => config('suratinaja.numbering.default_pattern'),
                'seq_padding' => (int) config('suratinaja.numbering.default_padding', 3),
            ]);

            WorkspaceMember::create([
                'workspace_id' => $ws->id,
                'user_id' => $user->id,
                'role' => WorkspaceRole::Admin,
                'joined_at' => now(),
            ]);

            $user->forceFill(['current_workspace_id' => $ws->id])->save();
            app(CurrentWorkspace::class)->set($ws);

            return $ws;
        });

        $this->showCreateModal = false;
        $this->reset(['newWorkspaceName', 'newWorkspaceCode']);
        $this->redirect(route('dashboard'), navigate: false);
    }

    public function render()
    {
        $user = auth()->user();
        $active = app(CurrentWorkspace::class)->get() ?? $user?->activeWorkspace();
        $workspaces = $user ? $user->workspaces()->orderBy('name')->get() : collect();
        $role = $active && $user ? $active->roleOf($user) : null;

        return view('livewire.workspace-switcher', [
            'activeWorkspace' => $active,
            'workspaces' => $workspaces,
            'activeRole' => $role,
        ]);
    }
}
