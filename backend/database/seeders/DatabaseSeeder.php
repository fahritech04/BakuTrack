<?php

namespace Database\Seeders;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => 'demo-umkm'],
            [
                'name' => 'Demo UMKM',
                'whatsapp_phone' => '6281234567890',
                'timezone' => 'Asia/Jakarta',
                'plan' => 'trial',
                'is_active' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'owner@bakutrack.local'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Owner Demo',
                'phone' => '6281234567890',
                'role' => 'owner',
                'password' => 'password',
            ]
        );

        Subscription::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan_name' => 'trial',
                'status' => 'trial',
                'monthly_price' => 0,
                'currency' => 'IDR',
                'started_at' => now(),
                'ends_at' => now()->addDays(30),
            ]
        );
    }
}
