<?php

namespace App\Livewire\Auth;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Daftar Akun')]
class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $workspace_name = '';

    public string $workspace_code = '';

    public function mount(): void
    {
        if (! config('suratinaja.allow_public_registration')) {
            session()->flash('info', 'Pendaftaran publik sedang ditutup. Akses akun hanya melalui undangan atau dibuatkan oleh Super Admin.');
            $this->redirect(route('login'), navigate: false);
        }
    }

    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'workspace_name' => ['nullable', 'string', 'max:120'],
            'workspace_code' => ['nullable', 'string', 'max:40'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'email_verified_at' => now(),
            ]);

            if (! empty(trim($validated['workspace_name'] ?? ''))) {
                $ws = Workspace::create([
                    'name' => trim($validated['workspace_name']),
                    'number_code' => trim($validated['workspace_code'] ?? '') ?: null,
                ]);

                WorkspaceMember::create([
                    'workspace_id' => $ws->id,
                    'user_id' => $user->id,
                    'role' => WorkspaceRole::Admin,
                    'joined_at' => now(),
                ]);

                $user->forceFill(['current_workspace_id' => $ws->id])->save();
            }

            return $user;
        });

        Auth::login($user);

        $this->redirect(route('dashboard'), navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
