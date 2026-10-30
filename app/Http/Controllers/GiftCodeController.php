<?php

namespace App\Http\Controllers;

use App\Domain\Captcha\CaptchaVerifier;
use App\Domain\Commerce\CommerceException;
use App\Domain\Commerce\GiftCodes;
use App\Models\GiftCode;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class GiftCodeController extends Controller
{
    public function __construct(private GiftCodes $codes) {}

    public function redeem(Request $request, CaptchaVerifier $captcha): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $captcha->verifyOrFail($request, 'gift_code');

        try {
            $redemption = $this->codes->redeem($request->user(), $data['code']);
        } catch (CommerceException $e) {
            return redirect()->to(route('account.show').'#gift-code')->withErrors(['code' => $e->getMessage()]);
        }

        return redirect()->to(route('account.show').'#gift-code')
            ->with('success', __(':amount was added to your balance.', ['amount' => Money::format($redemption->amount_minor)]));
    }

    public function index(): View
    {
        return view('theme::admin.gift-codes', ['codes' => GiftCode::query()->latest('id')->paginate(30)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:200'],
            'amount' => ['required', 'string', 'max:15'],
            'max_redemptions' => ['required', 'integer', 'min:1', 'max:10000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'note' => ['nullable', 'string', 'max:120'],
        ]);
        $amount = Money::parse($data['amount']);
        if ($amount === null || $amount < 1) {
            return back()->withErrors(['amount' => __('Enter a valid amount.')])->withInput();
        }

        try {
            $codes = $this->codes->generate((int) $data['count'], $amount, (int) $data['max_redemptions'],
                isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null, $data['note'] ?? null, (int) $request->user()->id);
        } catch (CommerceException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        // Shown once on the next page view only; never stored in clear text.
        return redirect()->route('admin.gift-codes.index')->with('generated_codes', $codes);
    }

    public function deactivate(Request $request, GiftCode $giftCode): RedirectResponse
    {
        $this->codes->deactivate($giftCode, (int) $request->user()->id);

        return back()->with('status', __('Saved.'));
    }
}
