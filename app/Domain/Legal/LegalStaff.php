<?php

namespace App\Domain\Legal;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

final class LegalStaff
{
    /** Staff and consumer writes take the user row before the market row. */
    public static function lock(User $actor): User
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Legal staff writes require a transaction.');
        }
        $current = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        abort_unless(! $current->isBanned() && $current->can('settings.manage'), 403);

        return $current;
    }
}
