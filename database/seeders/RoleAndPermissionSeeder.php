<?php

namespace Database\Seeders;

use App\Services\Authorization\WorkspacePermissionRegistrar;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Menyinkronkan seluruh global permission dan role preset ke semua workspace...');

        /** @var WorkspacePermissionRegistrar $registrar */
        $registrar = app(WorkspacePermissionRegistrar::class);
        $registrar->migrateExistingMembers();

        $this->command?->info('Sinkronisasi permission dan role selesai successfully.');
    }
}
