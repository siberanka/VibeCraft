<?php

namespace App\Console\Commands;

use App\Core\Install\Installation;
use Illuminate\Console\Command;

class SetupToken extends Command
{
    protected $signature = 'vibecraft:setup-token';
    protected $description = 'Provision a one-time token for the web installer';

    public function handle(): int
    {
        if (Installation::isInstalled()) { $this->error('The application is already installed.'); return self::FAILURE; }
        $token = bin2hex(random_bytes(32));
        $file = config('vibecraft.setup_token_file');
        if (file_put_contents($file, hash('sha256', $token), LOCK_EX) === false) { $this->error('Cannot write setup token.'); return self::FAILURE; }
        chmod($file, 0600);
        $this->info('One-time setup token (keep private):');
        $this->line($token);
        return self::SUCCESS;
    }
}
