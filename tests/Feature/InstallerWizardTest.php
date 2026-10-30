<?php

namespace Tests\Feature;

use App\Core\Install\EnvironmentFile;
use App\Core\Install\Installation;
use App\Http\Controllers\InstallController;
use App\Models\User;
use Dotenv\Dotenv;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InstallerWizardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        // The isolated test runtime has no image library; production keeps GD required.
        config(['vibecraft.required_extensions' => array_values(array_filter((array) config('vibecraft.required_extensions'), fn ($e) => extension_loaded($e)))]);
    }

    private function fresh(): void
    {
        unlink(config('vibecraft.install_lock'));
        Installation::forget();
        foreach ([config('vibecraft.setup_token_file'), storage_path(InstallController::TOKEN_TEXT)] as $file) {
            @unlink($file);
        }
    }

    private function writtenToken(): string
    {
        return trim((string) file_get_contents(storage_path(InstallController::TOKEN_TEXT)));
    }

    public function test_installer_writes_a_file_token_and_only_that_token_unlocks_the_next_steps(): void
    {
        $this->fresh();
        $this->get('/install')->assertOk()->assertSee('storage/app/setup-token.txt');
        $token = $this->writtenToken();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertSame(hash('sha256', $token), trim((string) file_get_contents(config('vibecraft.setup_token_file'))));

        $this->post('/install/database', ['app_url' => 'https://vibecraft.example'])->assertForbidden();
        $this->post('/install/token', ['setup_token' => str_repeat('0', 64)])->assertSessionHasErrors('setup_token');
        $this->post('/install/token', ['setup_token' => $token])->assertRedirect('/install');
        $this->get('/install')->assertOk()->assertSee(__('Complete setup'));
    }

    public function test_verified_session_creates_the_owner_and_removes_the_token_files(): void
    {
        $this->fresh();
        $this->get('/install');
        $this->post('/install/token', ['setup_token' => $this->writtenToken()]);
        $this->post('/install', ['site_name' => 'Emberfall', 'name' => 'Founder', 'email' => 'founder@example.test',
            'password' => 'GoodPassword123', 'password_confirmation' => 'GoodPassword123'])->assertRedirect(route('login'));

        $this->assertTrue(User::query()->where('email', 'founder@example.test')->sole()->hasRole('owner'));
        $this->assertFileExists(config('vibecraft.install_lock'));
        $this->assertFileDoesNotExist(storage_path(InstallController::TOKEN_TEXT));
        $this->assertFileDoesNotExist(config('vibecraft.setup_token_file'));
        $this->get('/install')->assertNotFound();
    }

    public function test_database_step_rejects_unreachable_servers_and_unsafe_values(): void
    {
        $this->fresh();
        $this->get('/install');
        $this->post('/install/token', ['setup_token' => $this->writtenToken()]);
        $this->post('/install/database', ['app_url' => 'https://vibecraft.example', 'db_host' => "bad host\n", 'db_port' => 3306,
            'db_database' => 'vibecraft', 'db_username' => 'root'])->assertSessionHasErrors('db_host');
        $this->post('/install/database', ['app_url' => 'https://vibecraft.example/path?x=1', 'db_host' => '127.0.0.1', 'db_port' => 1,
            'db_database' => 'vibecraft', 'db_username' => 'root'])->assertSessionHasErrors('app_url');
        $this->post('/install/database', ['app_url' => 'https://vibecraft.example', 'db_host' => '127.0.0.1', 'db_port' => 1,
            'db_database' => 'vibecraft', 'db_username' => 'root', 'db_password' => 'x'])->assertSessionHasErrors('db_host');
    }

    public function test_environment_values_are_quoted_literally(): void
    {
        $password = "pa\$\$w'o\"rd\\x #1 \${APP_KEY}";
        $content = EnvironmentFile::apply("DB_PASSWORD=\nAPP_URL=https://example.com\n", ['DB_PASSWORD' => $password, 'APP_URL' => 'https://play.example.test']);
        $parsed = Dotenv::parse($content);
        $this->assertSame($password, $parsed['DB_PASSWORD']);
        $this->assertSame('https://play.example.test', $parsed['APP_URL']);

        $this->expectException(\RuntimeException::class);
        EnvironmentFile::apply('', ['DB_PASSWORD' => "a\nAPP_DEBUG=true"]);
    }

    public function test_first_request_creates_an_env_file_with_a_fresh_key(): void
    {
        $base = storage_path('framework/testing-env-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($base.'/storage/app');
        file_put_contents($base.'/.env.example', "APP_KEY=\nSESSION_DRIVER=database\nCACHE_STORE=database\n");
        EnvironmentFile::prepare($base);
        $parsed = Dotenv::parse((string) file_get_contents($base.'/.env'));
        $this->assertStringStartsWith('base64:', $parsed['APP_KEY']);
        $this->assertSame('file', $parsed['SESSION_DRIVER']);

        $key = $parsed['APP_KEY'];
        EnvironmentFile::prepare($base);
        $this->assertSame($key, Dotenv::parse((string) file_get_contents($base.'/.env'))['APP_KEY']);
        File::deleteDirectory($base);
    }

    public function test_public_folder_refuses_backups_and_archives(): void
    {
        $rules = (string) file_get_contents(public_path('.htaccess'));
        foreach (['zip', 'sql', 'bak', 'env', 'log'] as $extension) {
            $this->assertStringContainsString($extension, $rules);
        }
        if (is_file(base_path('.htaccess'))) {
            $this->assertStringContainsString('RewriteRule ^(.*)$ public/$1', (string) file_get_contents(base_path('.htaccess')));
        }
    }
}
