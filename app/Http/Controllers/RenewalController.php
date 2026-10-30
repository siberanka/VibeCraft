<?php

namespace App\Http\Controllers;

use App\Domain\Commerce\CommerceException;
use App\Domain\Commerce\Renewals;
use App\Models\Entitlement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RenewalController extends Controller
{
    public function update(Request $request, Entitlement $entitlement, Renewals $renewals): RedirectResponse
    {
        abort_unless($entitlement->user_id === $request->user()->id, 404);
        $data = $request->validate(['auto_renew' => ['required', 'boolean']]);

        try {
            $data['auto_renew'] ? $renewals->enable($request->user(), $entitlement) : $renewals->disable($request->user(), $entitlement);
        } catch (CommerceException $e) {
            return redirect()->to(route('account.show').'#ranks')->withErrors(['renewal' => $e->getMessage()]);
        }

        return redirect()->to(route('account.show').'#ranks')->with('success', __('Saved.'));
    }
}
