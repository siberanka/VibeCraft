<?php

namespace App\Http\Controllers;

use App\Domain\Minecraft\AccountLinking;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function show(Request $request)
    {
        return view('theme::account.show', ['user' => $request->user()->load(['identities', 'wallet']),
            'topupGateways' => app(\App\Domain\Payments\Gateways::class)->available(),
            'creditPackages' => \App\Models\CreditPackage::offered(),
            'entries' => \App\Models\WalletEntry::where('user_id', $request->user()->id)->latest('id')->paginate(15)]);
    }

    public function security(Request $request)
    {
        $user = $request->user();
        return view('theme::account.security', ['user' => $user,
            'qrCode' => $user->two_factor_secret ? $user->twoFactorQrCodeSvg() : null,
            'recoveryCodes' => $user->two_factor_secret ? $user->recoveryCodes() : [],
        ]);
    }

    public function link(Request $request, AccountLinking $linking)
    {
        return back()->with('link_code', $linking->issueCode($request->user()));
    }

    public function unlink(Request $request, string $edition, AccountLinking $linking)
    {
        abort_unless(in_array($edition, ['java', 'bedrock'], true), 404);
        $linking->unlink($request->user(), $edition);
        return back()->with('status', __('Minecraft account unlinked.'));
    }
}
