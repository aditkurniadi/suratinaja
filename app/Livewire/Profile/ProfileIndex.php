<?php

namespace App\Livewire\Profile;

use App\Actions\Users\DeleteUserAccountAction;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Profil & Akun Pengguna')]
class ProfileIndex extends Component
{
    // Profile information
    public string $name = '';

    public string $email = '';

    // Update password
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    // Delete account
    public bool $showDeleteModal = false;

    public string $delete_password = '';

    public ?string $profileSuccessMessage = null;

    public ?string $passwordSuccessMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(403);
        }

        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function updateProfile(): void
    {
        $user = auth()->user();
        $this->profileSuccessMessage = null;
        $this->errorMessage = null;

        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email,' . $user->id],
        ]);

        $user->forceFill([
            'name' => trim($this->name),
            'email' => strtolower(trim($this->email)),
        ])->save();

        $this->profileSuccessMessage = 'Informasi profil berhasil diperbarui.';
    }

    public function updatePassword(): void
    {
        $user = auth()->user();
        $this->passwordSuccessMessage = null;
        $this->errorMessage = null;

        $this->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        if (! Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'Kata sandi saat ini tidak sesuai.');
            return;
        }

        $user->forceFill([
            'password' => Hash::make($this->password),
        ])->save();

        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->passwordSuccessMessage = 'Kata sandi akun Anda berhasil diperbarui.';
    }

    public function openDeleteModal(): void
    {
        $this->delete_password = '';
        $this->errorMessage = null;
        $this->showDeleteModal = true;
    }

    public function deleteAccount(DeleteUserAccountAction $deleteAction): void
    {
        $user = auth()->user();
        $this->errorMessage = null;

        if (empty($this->delete_password)) {
            $this->addError('delete_password', 'Silakan masukkan kata sandi Anda untuk konfirmasi.');
            return;
        }

        if (! Hash::check($this->delete_password, $user->password)) {
            $this->addError('delete_password', 'Kata sandi yang Anda masukkan salah.');
            return;
        }

        try {
            $deleteAction->execute($user);
            session()->flash('status', 'Akun Anda berhasil dihapus.');
            $this->redirect(route('login'), navigate: false);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.profile.profile-index')
            ->layout('components.layouts.app', [
                'title' => 'Profil & Akun Pengguna',
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => route('dashboard')],
                    ['label' => 'Profil Pengguna'],
                ],
            ]);
    }
}
