<?php

namespace App\Domain\Commerce;

use App\Jobs\SendOrderContractCopy;
use App\Mail\OrderContractCopy;
use App\Models\OrderContractNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

final class ContractNotifications
{
    public function __construct(private OrderContracts $contracts) {}

    public static function dispatch(int $id, string $messageKey): void
    {
        try {
            SendOrderContractCopy::dispatch($id, $messageKey)->onConnection('database');
        } catch (Throwable $exception) {
            // The durable pending row can be recovered without changing a completed order.
            Log::warning('contract_notice.queue_unavailable', ['notice_id' => $id, 'error_type' => get_class($exception)]);
        }
    }

    public function deliver(int $id, string $messageKey): bool
    {
        $notice = DB::transaction(function () use ($id, $messageKey) {
            $notice = OrderContractNotice::whereKey($id)->lockForUpdate()->first();
            if (! $notice || $notice->message_key !== $messageKey || $notice->state !== 'pending') {
                return null;
            }
            $notice->forceFill(['state' => 'sending', 'attempts' => $notice->attempts + 1, 'attempt_token' => bin2hex(random_bytes(16)), 'locked_at' => now(), 'last_error_code' => null])->save();

            return $notice;
        });
        if (! $notice) {
            return false;
        }
        try {
            $contract = $notice->contract;
            $original = $contract ? $this->contracts->original($contract->order) : null;
            if (! $original) {
                throw new RuntimeException('Original contract unavailable.');
            }
            $mail = new OrderContractCopy($original->snapshot['order_public_id'], $original->receipt_text, $notice->message_key, $original->snapshot['locale']);
            $sent = Mail::to($notice->recipient)->send($mail);
            if ($sent === null) {
                throw new RuntimeException('Mail transport did not confirm acceptance.');
            }

            return $this->finish($notice, true);
        } catch (Throwable $exception) {
            // SMTP may have accepted a message before a failure; never automatically resend uncertainty.
            $this->finish($notice, false);
            Log::warning('contract_notice.needs_review', ['notice_id' => $notice->id, 'error_type' => get_class($exception)]);

            return false;
        }
    }

    private function finish(OrderContractNotice $claim, bool $accepted): bool
    {
        return DB::transaction(function () use ($claim, $accepted) {
            $notice = OrderContractNotice::whereKey($claim->id)->lockForUpdate()->first();
            if (! $notice || $notice->state !== 'sending' || $notice->attempt_token !== $claim->attempt_token) {
                return false;
            }
            $notice->forceFill(['state' => $accepted ? 'sent' : 'manual_review', 'sent_at' => $accepted ? now() : null,
                'attempt_token' => null, 'locked_at' => null, 'last_error_code' => $accepted ? null : 'mail_acceptance_uncertain'])->save();

            return $accepted;
        });
    }

    public function recover(int $limit = 50): int
    {
        $limit = max(1, min(100, $limit));
        OrderContractNotice::where('state', 'sending')->where('locked_at', '<', now()->subMinutes(10))
            ->update(['state' => 'manual_review', 'attempt_token' => null, 'locked_at' => null, 'last_error_code' => 'mail_worker_interrupted']);
        $pending = OrderContractNotice::where('state', 'pending')->orderBy('id')->limit($limit)->get();
        foreach ($pending as $notice) {
            self::dispatch($notice->id, $notice->message_key);
        }

        return $pending->count();
    }
}
