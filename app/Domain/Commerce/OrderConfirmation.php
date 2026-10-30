<?php

namespace App\Domain\Commerce;

use App\Domain\Legal\LegalPresentation;
use App\Domain\Legal\LegalSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LogicException;

/** Created only after the actual customer's session-bound review was submitted. */
final readonly class OrderConfirmation
{
    private function __construct(
        public LegalSubmission $submission,
        public int $buyerId,
        public string $context,
        public string $referenceSha256,
    ) {}

    public static function fromRequest(Request $request, string $context): self
    {
        if (! in_array($context, ['checkout', 'topup'], true)) {
            throw new InvalidArgumentException('Unknown order confirmation context.');
        }
        $buyer = $request->user();
        abort_unless($buyer instanceof User && $buyer->exists, 403);
        $submission = LegalSubmission::fromInput($request->all(), app()->getLocale(), $context);
        app(LegalPresentation::class)->check($request, $submission, $context);
        $reference = hash('sha256', 'order-confirmation-v1|'.$buyer->id.'|'.$context.'|'.(string) $request->input('legal_review_token'));

        return new self($submission, (int) $buyer->id, $context, $reference);
    }

    public function assertCustomer(User $buyer, string $context): void
    {
        if ((int) $buyer->id !== $this->buyerId || $context !== $this->context) {
            throw new LogicException('The order confirmation belongs to a different customer or purchase context.');
        }
    }
}
