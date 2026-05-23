<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RestoreSeeder extends Seeder
{
    /**
     * Create a demo user and restore scraps from the latest backup zip.
     *
     * Looks for the latest zip in storage/app/backups/ or storage/app/private/backups/{userId}/.
     */
    public function run(): void
    {
        User::query()->firstOrNew(['email' => 'test@example.com'])->forceFill([
            'name' => 'Test User',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ])->save();

        $this->call(ScrapBackupSeeder::class);
    }
}
