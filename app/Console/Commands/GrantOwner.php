<?php

namespace App\Console\Commands;

use App\Core\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Console\Command;

class GrantOwner extends Command
{
    protected $signature = 'vibecraft:grant-owner {email}';

    protected $description = 'Give the owner role to an existing account (recovery tool)';

    public function handle(AuditLogger $audit): int
    {
        $user = User::query()->where('email', mb_strtolower((string) $this->argument('email')))->first();
        if ($user === null) {
            $this->error('No such user.');

            return self::FAILURE;
        }
        $user->assignRole('owner');
        $audit->log('role.owner_granted_cli', $user);
        $this->info("{$user->name} is now an owner.");

        return self::SUCCESS;
    }
}
