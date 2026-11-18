<?php

namespace App\Console\Commands;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateUserCommand extends Command
{
    protected $signature = 'app:create-user 
                            {email? : Alamat email pengguna}
                            {--name= : Nama lengkap pengguna}
                            {--password= : Password akun}
                            {--role=admin : Peran dalam workspace (admin, secretary, chairman)}
                            {--workspace= : Slug atau nama workspace}
                            {--super-admin : Jadikan super admin sistem}';

    protected $description = 'Buat akun pengguna baru atau super admin untuk hosting production';

    public function handle(): int
    {
        $this->info('=== Pembuatan Pengguna SuratinAja ===');

        $email = $this->argument('email') ?: $this->ask('Masukkan alamat email login');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Alamat email tidak valid.');

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();
        if ($existing) {
            $this->error("Pengguna dengan email [{$email}] sudah terdaftar.");
            if ($this->confirm('Apakah Anda ingin mereset password akun ini?', true)) {
                $password = $this->secret('Masukkan password baru');
                $existing->update(['password' => Hash::make($password)]);
                $this->info("Password untuk [{$email}] berhasil diperbarui.");

                return self::SUCCESS;
            }

            return self::FAILURE;
        }

        $name = $this->option('name') ?: $this->ask('Masukkan nama lengkap', 'Admin Persuratan');
        $password = $this->option('password') ?: $this->secret('Masukkan password akun');

        if (empty($password)) {
            $this->error('Password tidak boleh kosong.');

            return self::FAILURE;
        }

        $isSuperAdmin = $this->option('super-admin');
        if (! $isSuperAdmin && ! $this->option('role')) {
            $isSuperAdmin = $this->confirm('Jadikan akun ini Super Admin sistem?', false);
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'is_super_admin' => (bool) $isSuperAdmin,
        ]);

        // Hubungkan ke Workspace
        $workspaceQuery = $this->option('workspace');
        $workspace = null;

        if ($workspaceQuery) {
            $workspace = Workspace::where('slug', $workspaceQuery)
                ->orWhere('name', $workspaceQuery)
                ->first();
        }

        if (! $workspace) {
            $workspaces = Workspace::all();
            if ($workspaces->isNotEmpty()) {
                $wsChoices = $workspaces->pluck('name', 'id')->toArray();
                $wsChoices['new'] = '+ Buat Workspace Baru';
                $choice = $this->choice('Pilih workspace untuk pengguna ini', $wsChoices, $workspaces->first()->id);

                if ($choice === '+ Buat Workspace Baru' || $choice === 'new') {
                    $wsName = $this->ask('Nama Organisasi / Workspace Baru', 'Organisasi Saya');
                    $workspace = Workspace::create([
                        'name' => $wsName,
                        'slug' => Str::slug($wsName),
                        'number_code' => strtoupper(Str::limit(preg_replace('/[^a-zA-Z]/', '', $wsName), 8, '')),
                    ]);
                } else {
                    $workspace = Workspace::find(array_search($choice, $wsChoices, true)) ?: $workspaces->first();
                }
            } else {
                $wsName = $this->ask('Nama Organisasi / Workspace Pertama', 'HIMATRIS');
                $workspace = Workspace::create([
                    'name' => $wsName,
                    'slug' => Str::slug($wsName),
                    'number_code' => 'ORG',
                ]);
            }
        }

        $roleInput = strtolower((string) $this->option('role'));
        $role = match ($roleInput) {
            'secretary', 'sekretaris' => WorkspaceRole::Secretary,
            'chairman', 'ketua' => WorkspaceRole::Chairman,
            default => WorkspaceRole::Admin,
        };

        WorkspaceMember::firstOrCreate(
            ['workspace_id' => $workspace->id, 'user_id' => $user->id],
            ['role' => $role, 'joined_at' => now()]
        );

        $user->forceFill(['current_workspace_id' => $workspace->id])->saveQuietly();

        $this->newLine();
        $this->info('✓ Akun pengguna berhasil dibuat!');
        $this->table(
            ['Parameter', 'Nilai'],
            [
                ['Nama', $user->name],
                ['Email', $user->email],
                ['Super Admin', $user->is_super_admin ? 'Ya' : 'Tidak'],
                ['Workspace', $workspace->name . ' (' . $workspace->slug . ')'],
                ['Peran', $role->label()],
            ]
        );

        return self::SUCCESS;
    }
}
