<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Domain\Commerce\Checkout;
use App\Domain\Commerce\ContractNotifications;
use App\Domain\Commerce\OrderReview;
use App\Jobs\SendOrderContractCopy;
use App\Models\OrderContract;
use App\Models\OrderContractNotice;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesLegalMarkets;
use Tests\Concerns\CreatesOrderConfirmations;
use Tests\TestCase;

class ContractNotificationsTest extends TestCase
{
    use CreatesLegalMarkets, CreatesOrderConfirmations;

    private function fixture(): array
    {
        app()->setLocale('tr');
        config(['mail.default' => 'contract_fixture', 'mail.mailers.contract_fixture' => ['transport' => 'array']]);
        $market = $this->reviewedLegalMarket();
        $buyer = $this->customer();
        $this->enableSandbox();
        app(Settings::class)->set('legal.enforce', true);
        $review = OrderReview::topup($buyer, 1000, 'sandbox');
        $order = app(Checkout::class)->createTopup($buyer, 1000, 'sandbox', null, $this->orderConfirmation($buyer, $review, $market));
        $contract = OrderContract::where('order_id', $order->id)->sole();
        $notice = OrderContractNotice::where('order_contract_id', $contract->id)->sole();
        $transport = app('mail.manager')->mailer()->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);

        return [$buyer, $contract, $notice, $transport];
    }

    public function test_original_recipient_is_encrypted_and_exact_copy_is_submitted_once_even_after_profile_changes(): void
    {
        [$buyer, $contract, $notice, $transport] = $this->fixture();
        $recipient = $buyer->email;
        $this->assertNotSame($recipient, DB::table('order_contract_notices')->where('id', $notice->id)->value('recipient'));
        $this->assertArrayNotHasKey('recipient', $notice->toArray());
        $buyer->update(['email' => 'changed-after-order@fixture.example.test', 'locale' => 'en']);
        $service = app(ContractNotifications::class);
        $this->assertTrue($service->deliver($notice->id, $notice->message_key));
        $this->assertFalse($service->deliver($notice->id, $notice->message_key));
        $this->assertCount(1, $transport->messages());
        $message = $transport->messages()->sole()->getOriginalMessage();
        $this->assertSame($recipient, $message->getTo()[0]->getAddress());
        $this->assertSame($contract->receipt_text, $message->getAttachments()[0]->getBody());
        $this->assertStringContainsString($notice->message_key, $message->getHeaders()->get('Message-ID')->getBodyAsString());
        $this->assertSame('sent', $notice->fresh()->state);
        $this->assertSame(1, $notice->fresh()->attempts);
    }

    public function test_an_old_job_reference_does_not_send_a_reused_or_different_notice_row(): void
    {
        [$buyer, $contract, $notice, $transport] = $this->fixture();
        $this->assertFalse(app(ContractNotifications::class)->deliver($notice->id, str_repeat('0', 64)));
        $this->assertSame('pending', $notice->fresh()->state);
        $this->assertSame(0, $notice->fresh()->attempts);
        $this->assertCount(0, $transport->messages());
    }

    public function test_unconfirmed_mail_acceptance_is_not_marked_sent_or_automatically_retried(): void
    {
        [$buyer, $contract, $notice, $transport] = $this->fixture();
        Event::listen(MessageSending::class, fn () => false);
        $service = app(ContractNotifications::class);
        $this->assertFalse($service->deliver($notice->id, $notice->message_key));
        $this->assertSame('manual_review', $notice->fresh()->state);
        $this->assertFalse($service->deliver($notice->id, $notice->message_key));
        $this->assertSame(1, $notice->fresh()->attempts);
        $this->assertCount(0, $transport->messages());
        $this->assertSame($contract->receipt_text, $contract->fresh()->receipt_text);
    }

    public function test_interrupted_sending_is_reconciled_for_review_without_resending_uncertainty(): void
    {
        [$buyer, $contract, $notice, $transport] = $this->fixture();
        $notice->forceFill(['state' => 'sending', 'locked_at' => now()->subMinutes(11), 'attempt_token' => str_repeat('a', 32)])->save();
        $this->assertSame(0, app(ContractNotifications::class)->recover());
        $this->assertSame('manual_review', $notice->fresh()->state);
        $this->assertCount(0, $transport->messages());
    }

    public function test_recovery_command_queues_pending_reference_on_database_without_private_message_data(): void
    {
        [$buyer, $contract, $notice, $transport] = $this->fixture();
        Queue::fake();
        $this->artisan('vibecraft:contract-notices', ['--limit' => '1'])->assertSuccessful();
        Queue::assertPushed(SendOrderContractCopy::class, function ($job) use ($notice, $contract, $buyer) {
            $encoded = serialize($job);
            $this->assertStringNotContainsString($buyer->email, $encoded);
            $this->assertStringNotContainsString($contract->receipt_text, $encoded);

            return $job->noticeId === $notice->id && $job->messageKey === $notice->message_key && $job->connection === 'database';
        });
        $this->assertCount(0, $transport->messages());
        $this->assertSame('pending', $notice->fresh()->state);
    }

    public function test_invalid_recovery_limit_does_not_queue_or_modify_a_notice(): void
    {
        [$buyer, $contract, $notice, $transport] = $this->fixture();
        Queue::fake();
        $this->artisan('vibecraft:contract-notices', ['--limit' => '101'])->assertExitCode(2);
        Queue::assertNothingPushed();
        $this->assertSame('pending', $notice->fresh()->state);
    }

    public function test_repeated_recovery_coalesces_pending_jobs_and_recovers_after_a_lost_job_lock_expires(): void
    {
        [$buyer, $contract, $notice, $transport] = $this->fixture();
        Queue::fake();
        $service = app(ContractNotifications::class);
        $this->assertSame(1, $service->recover());
        $this->assertSame(1, $service->recover());
        Queue::assertPushed(SendOrderContractCopy::class, 1);
        $this->travel(11)->minutes();
        $this->assertSame(1, $service->recover());
        Queue::assertPushed(SendOrderContractCopy::class, 2);
        $this->assertSame('pending', $notice->fresh()->state);
        $this->assertCount(0, $transport->messages());
    }
}
