<?php

namespace App\Livewire\Settings;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Support\CurrentWorkspace;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Anggota Workspace')]
class MembersIndex extends Component
{
    public bool $showInviteModal = false;

    public string $inviteEmail = '';

    public string $inviteRole = 'secretary';

    public ?string $lastInviteUrl = null;

    public function mount(): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws || ! $ws->roleOf(auth()->user())?->isAdmin()) {
            abort(403);
        }
    }

    public function invite(): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws || ! $ws->roleOf(auth()->user())?->isAdmin()) {
            abort(403);
        }

        $validated = $this->validate([
            'inviteEmail' => ['required', 'string', 'lowercase', 'email', 'max:150'],
            'inviteRole' => ['required', Rule::in(['admin', 'secretary', 'chairman'])],
        ]);

        $existingUser = User::where('email', $validated['inviteEmail'])->first();
        if ($existingUser && $ws->hasMember($existingUser)) {
            $this->addError('inviteEmail', 'Pengguna dengan email tersebut sudah menjadi anggota workspace ini.');

            return;
        }

        $roleEnum = WorkspaceRole::from($validated['inviteRole']);

        // If user already exists on the platform, add membership directly AND record accepted invitation
        if ($existingUser) {
            WorkspaceMember::create([
                'workspace_id' => $ws->id,
                'user_id' => $existingUser->id,
                'role' => $roleEnum,
                'joined_at' => now(),
            ]);
            if (! $existingUser->current_workspace_id) {
                $existingUser->forceFill(['current_workspace_id' => $ws->id])->save();
            }
            $this->reset(['inviteEmail', 'showInviteModal']);
            $this->dispatch('toast', [
                'type' => 'success',
                'title' => 'Anggota berhasil ditambahkan',
                'description' => "{$existingUser->name} bergabung sebagai {$roleEnum->label()}.",
            ]);

            return;
        }

        $token = Str::random(40);
        WorkspaceInvitation::updateOrCreate(
            [
                'workspace_id' => $ws->id,
                'email' => $validated['inviteEmail'],
                'status' => 'pending',
            ],
            [
                'invited_by' => auth()->id(),
                'role' => $roleEnum,
                'token' => $token,
                'expires_at' => now()->addDays((int) config('suratinaja.members.invitation_ttl_days', 7)),
            ]
        );

        $this->lastInviteUrl = route('invitations.accept', ['token' => $token]);
        $this->reset(['inviteEmail']);
        $this->showInviteModal = false;

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Undangan dibuat',
            'description' => 'Salin tautan undangan atau minta pengguna login dengan email tersebut.',
        ]);
    }

    public function updateRole(int $memberId, string $newRole): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws || ! $ws->roleOf(auth()->user())?->isAdmin()) {
            abort(403);
        }

        $roleEnum = WorkspaceRole::tryFrom($newRole);
        if (! $roleEnum) {
            return;
        }

        $member = WorkspaceMember::where('workspace_id', $ws->id)->findOrFail($memberId);
        if ($member->role === WorkspaceRole::Admin && $roleEnum !== WorkspaceRole::Admin) {
            $adminCount = WorkspaceMember::where('workspace_id', $ws->id)
                ->where('role', WorkspaceRole::Admin->value)
                ->count();
            $minAdmins = (int) config('suratinaja.members.min_admins', 1);
            if ($adminCount <= $minAdmins) {
                $this->dispatch('toast', [
                    'type' => 'error',
                    'title' => 'Tidak dapat menurunkan Admin terakhir',
                    'description' => "Workspace wajib memiliki minimal {$minAdmins} Admin.",
                ]);

                return;
            }
        }

        $member->forceFill(['role' => $roleEnum])->save();
        $this->dispatch('toast', ['type' => 'success', 'title' => 'Peran anggota diperbarui']);
    }

    public function revoke(int $memberId): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws || ! $ws->roleOf(auth()->user())?->isAdmin()) {
            abort(403);
        }

        $member = WorkspaceMember::where('workspace_id', $ws->id)->findOrFail($memberId);
        if ($member->role === WorkspaceRole::Admin) {
            $adminCount = WorkspaceMember::where('workspace_id', $ws->id)
                ->where('role', WorkspaceRole::Admin->value)
                ->count();
            $minAdmins = (int) config('suratinaja.members.min_admins', 1);
            if ($adminCount <= $minAdmins) {
                $this->dispatch('toast', [
                    'type' => 'error',
                    'title' => 'Aksi ditolak',
                    'description' => 'Admin terakhir tidak dapat dikeluarkan dari workspace.',
                ]);

                return;
            }
        }

        $revokedUser = $member->user;
        $member->delete();

        if ($revokedUser && (int) $revokedUser->current_workspace_id === (int) $ws->id) {
            $revokedUser->forceFill(['current_workspace_id' => null])->save();
        }

        $this->dispatch('toast', ['type' => 'success', 'title' => 'Akses anggota dicabut']);
    }

    public function revokeInvitation(int $invitationId): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        WorkspaceInvitation::where('workspace_id', $ws->id)
            ->where('id', $invitationId)
            ->update(['status' => 'revoked']);
    }

    public function render()
    {
        $ws = app(CurrentWorkspace::class)->get();
        $members = WorkspaceMember::with('user')
            ->where('workspace_id', $ws->id)
            ->orderBy('id')
            ->get();
        $invitations = WorkspaceInvitation::where('workspace_id', $ws->id)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->get();

        return view('livewire.settings.members-index', [
            'workspace' => $ws,
            'members' => $members,
            'invitations' => $invitations,
        ]);
    }
}
