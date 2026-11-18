<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetPasswordCommand extends Command
{
    protected $signature = 'app:reset-password 
                            {email? : Alamat email pengguna}
                            {--password= : Password baru}';

    protected $description = 'Reset password akun pengguna di server hosting production';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Masukkan alamat email pengguna');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("Pengguna dengan email [{$email}] tidak ditemukan.");

            return self::FAILURE;
        }

        $password = $this->option('password') ?: $this->secret('Masukkan password baru');
        if (empty($password)) {
            $this->error('Password tidak boleh kosong.');

            return self::FAILURE;
        }

        $user->forceFill([
            'password' => Hash::make($password),
        ])->saveQuietly();

        $this->info("✓ Password untuk [{$user->name}] ({$user->email}) berhasil diperbarui.");

        return self::SUCCESS;
    }
}
