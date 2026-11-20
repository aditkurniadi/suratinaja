<?php

namespace App\Livewire\Settings;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Services\Authorization\WorkspacePermissionRegistrar;
use App\Support\CurrentWorkspace;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

#[Title('Anggota & Hak Akses Workspace')]
class MembersIndex extends Component
{
    public string $activeTab = 'members'; // 'members' | 'roles'

    public bool $showInviteModal = false;

    public string $inviteEmail = '';

    public string $inviteRole = 'Sekretaris';

    public ?string $lastInviteUrl = null;

    public function mount(): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws) {
            abort(403);
        }

        $user = auth()->user();
        if (! $user->is_super_admin && ! $user->can('member.view') && ! $ws->roleOf($user)?->isAdmin()) {
            abort(403, 'Anda tidak memiliki hak akses melihat anggota.');
        }

        if (request()->query('tab') === 'roles') {
            $this->activeTab = 'roles';
        }
    }

    public function invite(): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        $user = auth()->user();
        if (! $user->is_super_admin && ! $user->can('member.invite') && ! $ws->roleOf($user)?->isAdmin()) {
            abort(403);
        }

        setPermissionsTeamId($ws->id);
        $availableRoleNames = Role::where('workspace_id', $ws->id)->pluck('name')->all();
        $legacyEnumValues = array_map(fn ($r) => $r->value, WorkspaceRole::cases());
        $allowedRoles = array_unique(array_merge($availableRoleNames, $legacyEnumValues, [
            WorkspacePermissionRegistrar::ROLE_ADMIN,
            WorkspacePermissionRegistrar::ROLE_SECRETARY,
            WorkspacePermissionRegistrar::ROLE_CHAIRMAN,
            WorkspacePermissionRegistrar::ROLE_MEMBER,
        ]));

        $validated = $this->validate([
            'inviteEmail' => ['required', 'string', 'lowercase', 'email', 'max:150'],
            'inviteRole' => ['required', Rule::in($allowedRoles)],
        ]);

        $existingUser = User::where('email', $validated['inviteEmail'])->first();
        if ($existingUser && $ws->hasMember($existingUser)) {
            $this->addError('inviteEmail', 'Pengguna dengan email tersebut sudah menjadi anggota aktif workspace ini.');

            return;
        }

        $targetRoleName = $validated['inviteRole'];
        $token = Str::random(40);
        $ttl = (int) config('suratinaja.members.invitation_ttl_days', 7);

        $invitation = WorkspaceInvitation::updateOrCreate(
            [
                'workspace_id' => $ws->id,
                'email' => $validated['inviteEmail'],
                'status' => 'pending',
            ],
            [
                'invited_by' => auth()->id(),
                'role' => $targetRoleName,
                'token' => $token,
                'expires_at' => now()->addDays($ttl),
            ]
        );

        $this->lastInviteUrl = route('invitations.accept', ['token' => $token]);

        try {
            \Illuminate\Support\Facades\Mail::to($invitation->email)
                ->send(new \App\Mail\WorkspaceInvitationMail($invitation));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to send invitation email: {$e->getMessage()}");
        }

        $this->reset(['inviteEmail']);
        $this->showInviteModal = false;

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Undangan berhasil dikirim',
            'description' => "Email undangan dikirim ke {$invitation->email}. Anda juga dapat menyalin tautan undangan secara langsung.",
        ]);
    }

    public function resendInvitation(int $invitationId): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        $user = auth()->user();
        if (! $user->is_super_admin && ! $user->can('member.invite') && ! $ws->roleOf($user)?->isAdmin()) {
            abort(403);
        }

        $invitation = WorkspaceInvitation::where('workspace_id', $ws->id)
            ->where('status', 'pending')
            ->findOrFail($invitationId);

        $invitation->forceFill([
            'expires_at' => now()->addDays((int) config('suratinaja.members.invitation_ttl_days', 7)),
        ])->save();

        try {
            \Illuminate\Support\Facades\Mail::to($invitation->email)
                ->send(new \App\Mail\WorkspaceInvitationMail($invitation));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to resend invitation email: {$e->getMessage()}");
        }

        $this->lastInviteUrl = route('invitations.accept', ['token' => $invitation->token]);

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Email undangan dikirim ulang',
            'description' => "Masa aktif diperpanjang 7 hari ke depan untuk {$invitation->email}.",
        ]);
    }

    public function revokeInvitation(int $invitationId): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        $user = auth()->user();
        if (! $user->is_super_admin && ! $user->can('member.invite') && ! $ws->roleOf($user)?->isAdmin()) {
            abort(403);
        }

        $invitation = WorkspaceInvitation::where('workspace_id', $ws->id)->findOrFail($invitationId);
        $email = $invitation->email;
        $invitation->delete();

        $this->dispatch('toast', [
            'type' => 'info',
            'title' => 'Undangan dibatalkan',
            'description' => "Undangan untuk {$email} telah dihapus.",
        ]);
    }

    public function updateRole(int $memberId, string $newRoleName): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        $user = auth()->user();
        if (! $user->is_super_admin && ! $user->can('member.assign_role') && ! $ws->roleOf($user)?->isAdmin()) {
            abort(403);
        }

        setPermissionsTeamId($ws->id);
        $roleObj = Role::where('workspace_id', $ws->id)->where('name', $newRoleName)->firstOrFail();

        $member = WorkspaceMember::where('workspace_id', $ws->id)->findOrFail($memberId);
        $memberUser = $member->user;

        // Prevent demoting the last Admin Workspace
        $isAdminRole = ($newRoleName === WorkspacePermissionRegistrar::ROLE_ADMIN);
        $wasAdmin = ($member->role === WorkspaceRole::Admin || ($memberUser && $memberUser->hasRole(WorkspacePermissionRegistrar::ROLE_ADMIN)));

        if ($wasAdmin && ! $isAdminRole) {
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

        $legacyEnum = match ($newRoleName) {
            WorkspacePermissionRegistrar::ROLE_ADMIN => WorkspaceRole::Admin,
            WorkspacePermissionRegistrar::ROLE_CHAIRMAN => WorkspaceRole::Chairman,
            default => WorkspaceRole::Secretary,
        };

        $member->forceFill(['role' => $legacyEnum])->save();

        if ($memberUser) {
            setPermissionsTeamId($ws->id);
            $memberUser->syncRoles([$roleObj]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Peran anggota diperbarui',
            'description' => "Role telah diubah menjadi {$newRoleName}.",
        ]);
    }

    public function revoke(int $memberId): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        $user = auth()->user();
        if (! $user->is_super_admin && ! $user->can('member.remove') && ! $ws->roleOf($user)?->isAdmin()) {
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

        if ($revokedUser) {
            setPermissionsTeamId($ws->id);
            $revokedUser->roles()->detach();
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            if ((int) $revokedUser->current_workspace_id === (int) $ws->id) {
                $revokedUser->forceFill(['current_workspace_id' => null])->save();
            }
        }

        $this->dispatch('toast', ['type' => 'success', 'title' => 'Akses anggota dicabut']);
    }

    public function render()
    {
        $ws = app(CurrentWorkspace::class)->get();
        setPermissionsTeamId($ws->id);

        $members = WorkspaceMember::with(['user.roles' => function ($q) use ($ws) {
            $q->where('roles.workspace_id', $ws->id);
        }])
            ->where('workspace_id', $ws->id)
            ->orderBy('id')
            ->get();

        $invitations = WorkspaceInvitation::where('workspace_id', $ws->id)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->get();

        $workspaceRoles = Role::where('workspace_id', $ws->id)->orderBy('id')->get();

        return view('livewire.settings.members-index', [
            'workspace' => $ws,
            'members' => $members,
            'invitations' => $invitations,
            'workspaceRoles' => $workspaceRoles,
        ])->layout('components.layouts.app', [
            'title' => 'Anggota & Akses',
            'breadcrumbs' => [
                ['label' => 'Konfigurasi', 'url' => route('settings.numbering')],
                ['label' => 'Anggota & Akses'],
            ],
        ]);
    }
}
