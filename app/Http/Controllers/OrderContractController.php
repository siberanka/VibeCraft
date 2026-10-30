<?php

namespace App\Http\Controllers;

use App\Domain\Commerce\OrderContracts;
use App\Domain\Content\WikiRenderer;
use App\Domain\Legal\CountryCatalog;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class OrderContractController extends Controller
{
    public function show(Request $request, Order $order, OrderContracts $contracts): View|Response
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);
        $contract = $contracts->original($order);
        abort_unless($contract !== null, 404);
        $data = $request->validate(['download' => ['nullable', 'boolean']]);
        if (! empty($data['download'])) {
            $identifier = preg_replace('/[^A-Za-z0-9]/', '', $order->public_id);

            return response($contract->receipt_text, 200, ['Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="order-'.$identifier.'.txt"', 'Cache-Control' => 'no-store, private, max-age=0']);
        }
        $snapshot = $contract->snapshot;
        $rendered = [];
        foreach ($snapshot['documents'] as $document) {
            $rendered[$document['kind']] = WikiRenderer::render($document['body_markdown'], $document['locale'], 'contract-'.$document['kind'].'-');
        }

        return view('theme::orders.contract', ['order' => $order, 'contract' => $contract, 'snapshot' => $snapshot, 'rendered' => $rendered,
            'countryName' => CountryCatalog::options()[$snapshot['country']] ?? $snapshot['country']]);
    }
}
