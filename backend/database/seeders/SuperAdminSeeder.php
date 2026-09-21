<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPER_ADMIN_EMAIL', 'admin@iqplatform.test');
        $password = env('SUPER_ADMIN_PASSWORD');

        if (! $password) {
            // The well-known development default must never reach a real host.
            if (app()->environment('production')) {
                $this->command->error('SUPER_ADMIN_PASSWORD must be set in production; admin account not created.');

                return;
            }

            $password = 'ChangeMe123!';
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($password),
                'role' => 'super_admin',
                'auth_provider' => 'password',
            ]
        );
    }
}
