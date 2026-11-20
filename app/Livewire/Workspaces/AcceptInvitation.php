<?php

namespace App\Livewire\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Services\Authorization\WorkspacePermissionRegistrar;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

#[Layout('components.layouts.guest')]
#[Title('Undangan Workspace')]
class AcceptInvitation extends Component
{
    public ?WorkspaceInvitation $invitation = null;

    public string $token = '';

    // Registration fields for guest without an account
    public string $name = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $isRegisteredUser = false;

    public bool $isLoggedIn = false;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->invitation = WorkspaceInvitation::with(['workspace', 'inviter'])
            ->where('token', $token)
            ->first();

        if (! $this->invitation || ! $this->invitation->isPending()) {
            return;
        }

        $this->isLoggedIn = Auth::check();
        $this->isRegisteredUser = User::where('email', $this->invitation->email)->exists();
    }

    public function accept(): void
    {
        if (! $this->invitation || ! $this->invitation->isPending()) {
            $this->addError('token', 'Undangan ini sudah tidak aktif atau kedaluwarsa.');

            return;
        }

        $user = Auth::user();
        if (! $user) {
            $this->redirect(route('login', ['redirect' => route('invitations.accept', ['token' => $this->token])]));

            return;
        }

        $this->joinWorkspaceAndAssignRole($user);
    }

    public function registerAndAccept(): void
    {
        if (! $this->invitation || ! $this->invitation->isPending()) {
            $this->addError('token', 'Undangan ini sudah tidak aktif atau kedaluwarsa.');

            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $newUser = DB::transaction(function () {
            return User::create([
                'name' => trim($this->name),
                'email' => $this->invitation->email,
                'password' => Hash::make($this->password),
                'current_workspace_id' => $this->invitation->workspace_id,
            ]);
        });

        Auth::login($newUser);

        $this->joinWorkspaceAndAssignRole($newUser);
    }

    private function joinWorkspaceAndAssignRole(User $user): void
    {
        $ws = $this->invitation->workspace;
        $roleName = (string) $this->invitation->role;

        $legacyEnum = match ($roleName) {
            WorkspacePermissionRegistrar::ROLE_ADMIN, 'admin', WorkspaceRole::Admin->value => WorkspaceRole::Admin,
            WorkspacePermissionRegistrar::ROLE_CHAIRMAN, 'chairman', WorkspaceRole::Chairman->value => WorkspaceRole::Chairman,
            default => WorkspaceRole::Secretary,
        };

        DB::transaction(function () use ($user, $ws, $roleName, $legacyEnum) {
            WorkspaceMember::firstOrCreate(
                [
                    'workspace_id' => $ws->id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => $legacyEnum,
                    'joined_at' => now(),
                ]
            );

            setPermissionsTeamId($ws->id);
            $roleObj = Role::where('workspace_id', $ws->id)->where('name', $roleName)->first();
            if ($roleObj) {
                $user->assignRole($roleObj);
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->invitation->forceFill([
                'status' => 'accepted',
                'accepted_at' => now(),
            ])->save();

            $user->forceFill(['current_workspace_id' => $ws->id])->save();
        });

        session()->flash('status', "Selamat datang di {$ws->name}! Anda telah resmi bergabung sebagai {$this->invitation->getRoleDisplayName()}.");

        $this->redirect(route('dashboard'), navigate: false);
    }

    public function render()
    {
        return view('livewire.workspaces.accept-invitation');
    }
}
