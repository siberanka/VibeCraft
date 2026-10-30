<?php

namespace Tests\Feature;

use App\Core\Install\Installation;
use App\Models\User;
use Tests\TestCase;

class InstallationBoundaryTest extends TestCase
{
    public function test_installed_site_does_not_expose_installer(): void
    {
        $this->get('/install')->assertNotFound();
        $this->postJson('/install', [])->assertNotFound();
    }

    public function test_initial_setup_cannot_be_claimed_without_the_offline_token(): void
    {
        unlink(config('vibecraft.install_lock'));
        Installation::forget();
        $this->postJson('/install', [
            'site_name' => 'Fixture', 'name' => 'FixtureOwner', 'email' => 'owner@example.test',
            'password' => 'GoodPassword123', 'password_confirmation' => 'GoodPassword123',
        ])->assertUnprocessable()->assertJsonValidationErrors('setup_token');
        $this->assertSame(0, User::count());
    }

    public function test_wrong_token_does_not_create_an_owner_or_installation_lock(): void
    {
        unlink(config('vibecraft.install_lock'));
        Installation::forget();
        file_put_contents(config('vibecraft.setup_token_file'), hash('sha256', str_repeat('a', 64)));
        $this->postJson('/install', [
            'setup_token' => str_repeat('b', 64), 'site_name' => 'Fixture', 'name' => 'FixtureOwner', 'email' => 'owner@example.test',
            'password' => 'GoodPassword123', 'password_confirmation' => 'GoodPassword123',
        ])->assertUnprocessable()->assertJsonValidationErrors('setup_token');
        $this->assertSame(0, User::count());
        $this->assertFileDoesNotExist(config('vibecraft.install_lock'));
    }
}
