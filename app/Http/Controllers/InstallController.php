<?php

namespace App\Http\Controllers;

use App\Core\Install\Installation;
use App\Core\Security\Permissions;
use App\Core\Settings\Settings;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class InstallController extends Controller
{
    private function checks(): array
    {
        try { $schema = Schema::hasTable('users') && Schema::hasTable('settings') && Schema::hasTable('permissions'); }
        catch (Throwable) { $schema = false; }
        return [
            // @phpstan-ignore greaterOrEqual.alwaysTrue (The installer displays the runtime requirement explicitly.)
            'PHP 8.4+' => PHP_VERSION_ID >= 80400,
            'DOM / OpenSSL / mbstring / fileinfo / GD' => extension_loaded('dom') && extension_loaded('openssl') && extension_loaded('mbstring') && extension_loaded('fileinfo') && extension_loaded('gd'),
            'Application key' => strlen((string) config('app.key')) >= 32,
            'Database migrations' => $schema,
            'Writable storage' => is_writable(storage_path('app')) && is_writable(storage_path('framework')),
            'Setup token provisioned' => is_file(config('vibecraft.setup_token_file')),
            'Production debug disabled' => ! app()->isProduction() || ! config('app.debug'),
        ];
    }

    public function index()
    {
        $checks = $this->checks();
        return view('theme::install.index', ['checks' => $checks, 'ready' => ! in_array(false, $checks, true)]);
    }

    public function store(Request $request, Settings $settings)
    {
        $data = $request->validate([
            'setup_token' => ['required', 'string', 'size:64'],
            'site_name' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'min:3', 'max:24', 'regex:/^[A-Za-z0-9_]+$/'],
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $file = config('vibecraft.setup_token_file');
        $digest = is_file($file) ? trim((string) file_get_contents($file)) : '';
        if ($digest === '' || ! hash_equals($digest, hash('sha256', $data['setup_token']))) {
            throw ValidationException::withMessages(['setup_token' => __('Invalid setup token.')]);
        }
        abort_if(in_array(false, $this->checks(), true), 503, 'Setup prerequisites are incomplete.');

        $handle = fopen(storage_path('app/install.mutex'), 'c');
        abort_if($handle === false, 503);
        try {
            abort_unless(flock($handle, LOCK_EX | LOCK_NB), 409);
            Installation::forget();
            abort_if(Installation::isInstalled(), 404);
            // An interrupted installation must be recovered offline, never claimed by another visitor.
            abort_if(User::exists(), 409, 'An account already exists; recover the installation from the console.');
            DB::transaction(function () use ($data, $settings) {
                foreach (Permissions::ALL as $name => $label) { Permission::findOrCreate($name, 'web'); }
                foreach (Permissions::ROLES as $name => $grants) {
                    $role = Role::findOrCreate($name, 'web');
                    $role->syncPermissions($grants === '*' ? array_keys(Permissions::ALL) : $grants);
                }
                $user = User::create(['name' => $data['name'], 'email' => mb_strtolower($data['email']), 'password' => $data['password'], 'locale' => 'tr']);
                $user->forceFill(['email_verified_at' => now()])->save();
                $user->assignRole('owner');
                $settings->setMany(['site.name' => $data['site_name'], 'site.default_locale' => 'tr']);
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            });
            Installation::markInstalled();
            // @phpstan-ignore if.alwaysTrue (Another process may remove the token file before cleanup.)
            if (is_file($file)) { unlink($file); }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
        return redirect()->route('login')->with('status', __('Installation complete. Sign in and enable two-factor authentication.'));
    }
}
