<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Only e-mail and locale are editable; the username is a stable public handle.
     *
     * @param  array<string, string>  $input
     */
    public function update(User $user, array $input): void
    {
        if (is_string($input['email'] ?? null)) {
            $input['email'] = mb_strtolower(trim($input['email']));
        }
        Validator::make($input, [
            'email' => ['required', 'string', 'email:rfc', 'max:191', Rule::unique('users')->ignore($user->id)],
            'locale' => ['nullable', Rule::in(config('vibecraft.locales'))],
            'current_password' => ['required', 'current_password:web'],
        ])->validateWithBag('updateProfileInformation');

        $email = mb_strtolower($input['email']);
        // @phpstan-ignore instanceof.alwaysTrue (Preserve the Fortify runtime interface boundary.)
        if ($email !== $user->email && $user instanceof MustVerifyEmail) {
            $user->forceFill(['email' => $email, 'email_verified_at' => null, 'locale' => $input['locale'] ?? $user->locale])->save();
            $user->sendEmailVerificationNotification();
        } else {
            $user->forceFill(['locale' => $input['locale'] ?? $user->locale])->save();
        }
    }
}
