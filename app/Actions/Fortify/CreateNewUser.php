<?php

namespace App\Actions\Fortify;

use App\Core\Settings\Settings;
use App\Domain\Captcha\CaptchaVerifier;
use App\Domain\Legal\LegalConsumer;
use App\Domain\Legal\LegalPresentation;
use App\Domain\Legal\LegalSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        if (is_string($input['email'] ?? null)) {
            $input['email'] = mb_strtolower(trim($input['email']));
        }
        if (! app(Settings::class)->bool('auth.registration')) {
            throw ValidationException::withMessages(['email' => __('Registration is currently closed.')]);
        }

        app(CaptchaVerifier::class)->verifyOrFail(request(), 'register');

        $legal = app(LegalConsumer::class);
        $submission = $legal->enforced() ? LegalSubmission::fromInput($input, app()->getLocale(), 'registration') : null;
        if ($submission) {
            app(LegalPresentation::class)->check(request(), $submission, 'registration');
        }

        Validator::make($input, [
            'name' => ['required', 'string', 'min:3', 'max:24', 'regex:/^[A-Za-z0-9_]+$/', Rule::unique(User::class)],
            'email' => ['required', 'string', 'email:rfc', 'max:191', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
        ], [
            'name.regex' => __('Username may contain letters, numbers and underscores only.'),
        ])->validate();

        $user = DB::transaction(function () use ($input, $legal, $submission) {
            $user = User::query()->create([
                'name' => $input['name'],
                'email' => mb_strtolower($input['email']),
                'password' => $input['password'],
                'locale' => app()->getLocale(),
            ]);
            $user->assignRole('member');
            if ($submission) {
                $legal->record($submission, $user, 'registration');
            }

            return $user;
        });
        if ($submission) {
            app(LegalPresentation::class)->forget(request(), 'registration');
        }

        return $user;
    }
}
