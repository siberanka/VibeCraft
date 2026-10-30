<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Domain\Commerce\Checkout;
use App\Domain\Commerce\Ledger;
use App\Domain\Commerce\OrderContracts;
use App\Domain\Commerce\OrderReview;
use App\Domain\Legal\LegalPublisher;
use App\Models\OrderContract;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesLegalMarkets;
use Tests\Concerns\CreatesOrderConfirmations;
use Tests\TestCase;

class OrderContractHttpTest extends TestCase
{
    use CreatesLegalMarkets, CreatesOrderConfirmations;

    private function originalOrder(): array
    {
        app()->setLocale('tr');
        $market = $this->reviewedLegalMarket('TR', 'tr', '', ['en']);
        $buyer = $this->customer();
        $this->enableSandbox();
        app(Settings::class)->set('legal.enforce', true);
        $review = OrderReview::topup($buyer, 1000, 'sandbox');
        $confirmation = $this->orderConfirmation($buyer, $review, $market);
        $order = app(Checkout::class)->createTopup($buyer, 1000, 'sandbox', null, $confirmation);

        return [$buyer, $order, OrderContract::where('order_id', $order->id)->sole(), $market];
    }

    public function test_the_customer_can_read_and_download_only_the_stored_original_bytes_without_internal_references(): void
    {
        [$buyer, $order, $contract] = $this->originalOrder();
        $this->actingAs($buyer)->get('/orders/'.$order->public_id)->assertOk()->assertSee(route('orders.contract', $order), false);
        $response = $this->get('/orders/'.$order->public_id.'/contract')->assertOk()->assertSee('Synthetic QA Merchant')
            ->assertDontSee($contract->confirmation_sha256)->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        preg_match_all('/id="(contract-[^"]+)"/', $response->getContent(), $matches);
        $this->assertCount(6, $matches[1]);
        $this->assertCount(6, array_unique($matches[1]));
        $download = $this->get('/orders/'.$order->public_id.'/contract?download=1')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertSame($contract->receipt_text, $download->getContent());
        $this->assertSame($contract->receipt_sha256, hash('sha256', $download->getContent()));
    }

    public function test_later_profile_policy_and_language_changes_do_not_change_the_original_download(): void
    {
        [$buyer, $order, $contract, $market] = $this->originalOrder();
        $bytes = $contract->receipt_text;
        $actor = $this->customer('owner');
        $publisher = app(LegalPublisher::class);
        $policy = $market->policies()->where('kind', 'terms')->where('locale', 'tr')->firstOrFail();
        $policy = $publisher->saveDraft($market, $actor, ['kind' => 'terms', 'locale' => 'tr', 'revision' => 2, 'title' => 'Changed later terms', 'body_markdown' => 'A later unrelated document revision.']);
        $publisher->publish($policy, 3, $actor);
        $market->update(['trader' => array_replace($market->trader, ['name' => 'New Later Merchant'])]);
        $buyer->update(['locale' => 'en']);
        $response = $this->actingAs($buyer)->get('/orders/'.$order->public_id.'/contract?download=1')->assertOk();
        $this->assertSame($bytes, $response->getContent());
        $this->assertStringNotContainsString('Changed later terms', $bytes);
        $this->assertStringNotContainsString('New Later Merchant', $bytes);
        $this->assertSame(0, app(Ledger::class)->balance($buyer->id));
    }

    public function test_another_account_and_staff_do_not_get_a_customer_receipt(): void
    {
        [$buyer, $order] = $this->originalOrder();
        $this->get('/orders/'.$order->public_id.'/contract')->assertRedirect(route('login'));
        $this->actingAs($this->customer())->get('/orders/'.$order->public_id.'/contract?download=1')->assertNotFound();
        $this->actingAs($this->customer('owner'))->get('/orders/'.$order->public_id.'/contract')->assertNotFound();
    }

    public function test_the_original_remains_readable_when_later_order_processing_needs_review(): void
    {
        [$buyer, $order, $contract] = $this->originalOrder();
        $order->update(['total_minor' => 1001]);
        $this->assertFalse(app(OrderContracts::class)->validFor($order));
        $response = $this->actingAs($buyer)->get('/orders/'.$order->public_id.'/contract?download=1')->assertOk();
        $this->assertSame($contract->receipt_text, $response->getContent());
    }

    public function test_damaged_receipt_bytes_are_not_returned_as_the_original_confirmation(): void
    {
        [$buyer, $order, $contract] = $this->originalOrder();
        DB::table('order_contracts')->where('id', $contract->id)->update(['receipt_text' => $contract->receipt_text."\nDifferent bytes"]);
        $this->assertFalse(app(OrderContracts::class)->validFor($order));
        $this->actingAs($buyer)->get('/orders/'.$order->public_id.'/contract?download=1')->assertNotFound();
    }
}
