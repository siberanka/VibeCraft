<?php

namespace App\Http\Controllers;

use App\Core\Install\EnvironmentFile;
use App\Core\Install\Installation;
use App\Core\Security\Permissions;
use App\Core\Settings\Settings;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PDO;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Browser installer for shared hosting: hosting checks, proof of file access
 * (a one-time token written to storage/app that only the account owner can
 * read), database connection and migrations, then the owner account. Each step
 * is bound to the session that proved the token.
 */
class InstallController extends Controller
{
    public const TOKEN_TEXT = 'app/setup-token.txt';

    private function checks(): array
    {
        return [
            // @phpstan-ignore greaterOrEqual.alwaysTrue (The installer displays the runtime requirement explicitly.)
            'PHP 8.4+' => PHP_VERSION_ID >= 80400,
            'PHP extensions' => $this->missingExtensions() === [],
            'Application key' => strlen((string) config('app.key')) >= 32,
            'Writable storage' => is_writable(storage_path('app')) && is_writable(storage_path('framework')) && is_writable(base_path('bootstrap/cache')),
            'Writable .env file' => ! config('vibecraft.installer_writes_env') || EnvironmentFile::writable(),
            'Production debug disabled' => ! app()->isProduction() || ! config('app.debug'),
        ];
    }

    /** @return list<string> */
    private function missingExtensions(): array
    {
        return array_values(array_filter((array) config('vibecraft.required_extensions'), fn (string $e) => ! extension_loaded($e)));
    }

    private function databaseReady(): bool
    {
        try {
            return Schema::hasTable('users') && Schema::hasTable('settings') && Schema::hasTable('permissions');
        } catch (Throwable) {
            return false;
        }
    }

    /** Writes a fresh one-time token the owner reads with the hosting file manager. */
    private function provisionToken(): void
    {
        $digestFile = config('vibecraft.setup_token_file');
        if (is_file($digestFile) || ! is_writable(dirname($digestFile))) {
            return;
        }
        $token = bin2hex(random_bytes(32));
        $text = storage_path(self::TOKEN_TEXT);
        if (file_put_contents($text, $token."\n", LOCK_EX) === false) {
            return;
        }
        @chmod($text, 0600);
        file_put_contents($digestFile, hash('sha256', $token), LOCK_EX);
        @chmod($digestFile, 0600);
    }

    private function verified(Request $request): bool
    {
        return $request->session()->get('install.verified') === true;
    }

    private function tokenMatches(string $token): bool
    {
        $file = config('vibecraft.setup_token_file');
        $digest = is_file($file) ? trim((string) file_get_contents($file)) : '';

        return $digest !== '' && hash_equals($digest, hash('sha256', $token));
    }

    public function index(Request $request): View
    {
        $this->provisionToken();
        $checks = $this->checks();
        $verified = $this->verified($request);
        $step = ! $verified ? 'token' : ($this->databaseReady() ? 'owner' : 'database');

        return view('theme::install.index', [
            'checks' => $checks, 'ready' => ! in_array(false, $checks, true), 'step' => $step,
            'missingExtensions' => $this->missingExtensions(), 'tokenPath' => 'storage/'.self::TOKEN_TEXT,
            'suggestedUrl' => $request->getSchemeAndHttpHost(), 'cronLine' => self::cronLine(),
        ]);
    }

    /** The web server may run PHP-FPM or LSAPI; cron needs the CLI binary next to it. */
    public static function cronLine(): string
    {
        $php = (string) preg_replace('#/(sbin/php-fpm[0-9.]*|bin/lsphp|bin/php-cgi)$#', '/bin/php', PHP_BINARY);

        return '* * * * * '.($php !== '' ? $php : 'php').' '.base_path('artisan').' schedule:run >> /dev/null 2>&1';
    }

    public function token(Request $request): RedirectResponse
    {
        $data = $request->validate(['setup_token' => ['required', 'string', 'size:64']]);
        if (! $this->tokenMatches($data['setup_token'])) {
            throw ValidationException::withMessages(['setup_token' => __('Invalid setup token.')]);
        }
        $request->session()->regenerate();
        $request->session()->put('install.verified', true);

        return redirect('/install');
    }

    public function database(Request $request): RedirectResponse
    {
        abort_unless($this->verified($request), 403);
        $data = $request->validate([
            'app_url' => ['required', 'url:http,https', 'max:255', 'regex:/^https?:\/\/[A-Za-z0-9.-]+(:\d{1,5})?$/'],
            'db_host' => ['required', 'string', 'max:253', 'regex:/^[A-Za-z0-9.-]+$/'],
            'db_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'db_database' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'db_username' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_.@-]+$/'],
            'db_password' => ['nullable', 'string', 'max:200', 'not_regex:/[\r\n\x00]/'],
        ]);
        abort_if(in_array(false, $this->checks(), true), 503, 'Setup prerequisites are incomplete.');
        $password = (string) ($data['db_password'] ?? '');
        try {
            new PDO('mysql:host='.$data['db_host'].';port='.(int) $data['db_port'].';dbname='.$data['db_database'].';charset=utf8mb4',
                $data['db_username'], $password, [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (Throwable) {
            throw ValidationException::withMessages(['db_host' => __('Could not connect to the database. Check the host, name, user and password.')]);
        }

        if (config('vibecraft.installer_writes_env')) {
            EnvironmentFile::write([
                'APP_URL' => rtrim($data['app_url'], '/'), 'SESSION_SECURE_COOKIE' => str_starts_with($data['app_url'], 'https://') ? 'true' : 'false',
                'DB_CONNECTION' => 'mysql', 'DB_HOST' => $data['db_host'], 'DB_PORT' => (string) $data['db_port'],
                'DB_DATABASE' => $data['db_database'], 'DB_USERNAME' => $data['db_username'], 'DB_PASSWORD' => $password,
            ]);
        }
        config(['database.default' => 'mysql', 'database.connections.mysql.host' => $data['db_host'], 'database.connections.mysql.port' => (int) $data['db_port'],
            'database.connections.mysql.database' => $data['db_database'], 'database.connections.mysql.username' => $data['db_username'], 'database.connections.mysql.password' => $password]);
        DB::purge('mysql');
        @set_time_limit(300);
        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            Log::error('install.migration_failed', ['error_type' => get_class($e)]);
            throw ValidationException::withMessages(['db_host' => __('The database tables could not be created. Use an empty database and check the user permissions.')]);
        }

        return redirect('/install');
    }

    public function store(Request $request, Settings $settings): RedirectResponse
    {
        $data = $request->validate([
            'setup_token' => [$this->verified($request) ? 'nullable' : 'required', 'string', 'size:64'],
            'site_name' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'min:3', 'max:24', 'regex:/^[A-Za-z0-9_]+$/'],
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        if (! $this->verified($request) && ! $this->tokenMatches((string) $data['setup_token'])) {
            throw ValidationException::withMessages(['setup_token' => __('Invalid setup token.')]);
        }
        abort_if(in_array(false, $this->checks(), true) || ! $this->databaseReady(), 503, 'Setup prerequisites are incomplete.');

        $handle = fopen(storage_path('app/install.mutex'), 'c');
        abort_if($handle === false, 503);
        try {
            abort_unless(flock($handle, LOCK_EX | LOCK_NB), 409);
            Installation::forget();
            abort_if(Installation::isInstalled(), 404);
            // An interrupted installation must be recovered offline, never claimed by another visitor.
            abort_if(User::exists(), 409, 'An account already exists; recover the installation from the console.');
            DB::transaction(function () use ($data, $settings) {
                foreach (Permissions::ALL as $name => $label) {
                    Permission::findOrCreate($name, 'web');
                }
                foreach (Permissions::ROLES as $name => $grants) {
                    $role = Role::findOrCreate($name, 'web');
                    $role->syncPermissions($grants === '*' ? array_keys(Permissions::ALL) : $grants);
                }
                $user = User::create(['name' => $data['name'], 'email' => mb_strtolower($data['email']), 'password' => $data['password'], 'locale' => app()->getLocale()]);
                $user->forceFill(['email_verified_at' => now()])->save();
                $user->assignRole('owner');
                $settings->setMany(['site.name' => $data['site_name'], 'site.default_locale' => app()->getLocale() === 'en' ? 'en' : 'tr']);
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            });
            Installation::markInstalled();
            foreach ([config('vibecraft.setup_token_file'), storage_path(self::TOKEN_TEXT)] as $secret) {
                if (is_file($secret)) {
                    unlink($secret);
                }
            }
            if (config('vibecraft.installer_writes_env') && EnvironmentFile::writable() && is_file(EnvironmentFile::path())) {
                // Install-time file drivers are replaced by the production database drivers.
                EnvironmentFile::write(['SESSION_DRIVER' => 'database', 'CACHE_STORE' => 'database', 'QUEUE_CONNECTION' => 'database']);
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        return redirect()->route('login')->with('status', __('Installation complete. Sign in and enable two-factor authentication.'));
    }
}
