<?php

namespace App\Domain\Content;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final readonly class WikiAudience
{
    /** @param list<string> $roles */
    private function __construct(private bool $verified, private array $roles) {}

    public static function guest(): self
    {
        return new self(false, []);
    }

    public static function forUser(?User $user): self
    {
        if ($user === null || $user->isBanned() || ! $user->hasVerifiedEmail()) {
            return self::guest();
        }

        return new self(true, array_values($user->roles()->where('guard_name', 'web')->pluck('name')->all()));
    }

    /** Reading privileges are explicit; management permissions add no public bypass. */
    public function constrain(Builder $query): void
    {
        $access = $query->qualifyColumn('reader_access');
        $roles = $query->qualifyColumn('reader_roles');
        $query->where(function (Builder $policy) use ($access, $roles) {
            $policy->where($access, 'public');
            if (! $this->verified) {
                return;
            }
            $policy->orWhere($access, 'members');
            if ($this->roles !== []) {
                $policy->orWhere(function (Builder $selected) use ($access, $roles) {
                    $selected->where($access, 'roles')->where(function (Builder $matching) use ($roles) {
                        foreach ($this->roles as $role) {
                            $matching->orWhereJsonContains($roles, $role);
                        }
                    });
                });
            }
        });
    }
}
