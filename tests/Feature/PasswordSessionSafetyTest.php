<?php

namespace Tests\Feature;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordSessionSafetyTest extends TestCase
{
    public function test_password_change_rotates_remember_token_and_rejects_an_old_password_hash_session(): void
    {
        $user = $this->customer();
        $oldHash = $user->password;
        $oldRemember = $user->remember_token;
        $this->actingAs($user);
        app(UpdateUserPassword::class)->update($user, ['current_password' => 'password', 'password' => 'ChangedPassword123', 'password_confirmation' => 'ChangedPassword123']);
        $this->assertNotSame($oldRemember, $user->fresh()->remember_token);
        $this->assertTrue(Hash::check('ChangedPassword123', $user->fresh()->password));
        $this->actingAs($user->fresh())->withSession(['password_hash_web' => $oldHash])->get('/account')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_password_reset_deletes_only_the_target_users_database_sessions(): void
    {
        $user = $this->customer();
        $other = $this->customer();
        $oldRemember = $user->remember_token;
        config(['session.driver' => 'database', 'session.connection' => null, 'session.table' => 'sessions']);
        foreach ([$user, $other] as $account) {
            DB::table('sessions')->insert(['id' => 'fixture-session-'.$account->id, 'user_id' => $account->id,
                'payload' => base64_encode(serialize([])), 'last_activity' => time()]);
        }
        app(ResetUserPassword::class)->reset($user, ['password' => 'ResetPassword123', 'password_confirmation' => 'ResetPassword123']);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());
        $this->assertNotSame($oldRemember, $user->fresh()->remember_token);
        $this->assertTrue(Hash::check('ResetPassword123', $user->fresh()->password));
    }
}
