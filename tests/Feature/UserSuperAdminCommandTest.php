<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSuperAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_grants_super_admin_access(): void
    {
        $user = User::factory()->create([
            'email' => 'maintainer@example.com',
            'is_super_admin' => false,
        ]);

        $this->artisan('user:super-admin', ['email' => $user->email])
            ->assertSuccessful();

        $this->assertTrue((bool) $user->fresh()->is_super_admin);
    }

    public function test_command_revokes_super_admin_access(): void
    {
        $user = User::factory()->create([
            'email' => 'maintainer@example.com',
            'is_super_admin' => true,
        ]);

        $this->artisan('user:super-admin', [
            'email' => $user->email,
            '--revoke' => true,
        ])->assertSuccessful();

        $this->assertFalse((bool) $user->fresh()->is_super_admin);
    }

    public function test_command_returns_failure_when_user_not_found(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'is_super_admin' => false,
        ]);

        $this->artisan('user:super-admin', ['email' => 'missing@example.com'])
            ->assertFailed();

        $this->assertFalse((bool) $existingUser->fresh()->is_super_admin);
    }
}
