<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Enums\WorkspaceRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SyncSuperAdminCommand extends Command
{
    protected $signature = 'app:sync-superadmin';

    protected $description = 'Sinkronkan akun Super Admin dari environment SUPERADMIN_EMAIL dan SUPERADMIN_PASSWORD';

    public function handle(): int
    {
        $email = trim((string) config('suratinaja.super_admin.email'));
        $password = (string) config('suratinaja.super_admin.password');

        if (empty($email) || empty($password)) {
            $this->info('SUPERADMIN_EMAIL atau SUPERADMIN_PASSWORD tidak diatur di environment. Sinkronisasi dilewati.');
            return self::SUCCESS;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name' => 'Super Admin SuratinAja',
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'status' => 'active',
                'is_super_admin' => true,
                'is_demo' => false,
            ]);

            // Jika ada workspace, hubungkan sebagai Admin ke workspace pertama
            $firstWorkspace = Workspace::first();
            if ($firstWorkspace) {
                WorkspaceMember::firstOrCreate(
                    ['workspace_id' => $firstWorkspace->id, 'user_id' => $user->id],
                    ['role' => WorkspaceRole::Admin, 'joined_at' => now()]
                );
                $user->forceFill(['current_workspace_id' => $firstWorkspace->id])->saveQuietly();
            }

            $this->info("Akun Super Admin baru berhasil dibuat untuk [{$email}].");
        } else {
            // Perbarui password dan pastikan flag superadmin aktif
            $user->forceFill([
                'password' => Hash::make($password),
                'is_super_admin' => true,
                'status' => 'active',
                'is_demo' => false,
            ])->saveQuietly();

            $this->info("Akun Super Admin [{$email}] berhasil disinkronkan dan kata sandi diperbarui.");
        }

        return self::SUCCESS;
    }
}
