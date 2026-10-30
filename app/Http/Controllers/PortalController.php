<?php

namespace App\Http\Controllers;

use App\Core\Settings\Settings;
use App\Models\Category;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function locale(Request $request)
    {
        $data = $request->validate(['locale' => ['required', Rule::in(config('vibecraft.locales'))]]);
        $request->session()->put('locale', $data['locale']);
        if ($request->user()) {
            $request->user()->update(['locale' => $data['locale']]);
        }

        return redirect()->route('home');
    }

    public function home(Settings $settings): View
    {
        return view('theme::home', [
            'posts' => $settings->bool('modules.news') ? Post::published()->orderByDesc('pinned')->latest('published_at')->limit(3)->get() : collect(),
            'products' => $settings->bool('modules.store') ? Product::visible()->with('category')->where('is_featured', true)->orderBy('sort')->limit(4)->get() : collect(),
            'categories' => $settings->bool('modules.store') ? Category::where('is_visible', true)->orderBy('sort')->get() : collect(),
            'recentPurchases' => $settings->bool('modules.store') && $settings->bool('store.show_recent_purchases') ? $this->recentPurchases() : [],
        ]);
    }

    /**
     * Opt-in social proof: player name, product and time of the last paid purchases.
     * Amounts, e-mail addresses and account names are never shown.
     *
     * @return list<array{player: string, product: string, at: Carbon}>
     */
    private function recentPurchases(): array
    {
        $rows = Cache::remember('vibecraft.recent_purchases.v1', 60, fn () => OrderItem::query()
            ->whereNotNull('player_name')
            ->whereHas('order', fn ($q) => $q->where('kind', 'purchase')->where('status', Order::PAID))
            ->with('order:id,paid_at')->latest('id')->limit(6)->get(['id', 'order_id', 'player_name', 'product_name'])
            ->map(fn (OrderItem $item) => ['player' => $item->player_name, 'product' => $item->product_name, 'at' => $item->order?->paid_at?->toIso8601String()])
            ->filter(fn ($row) => $row['at'] !== null)->values()->all());

        return array_map(fn ($row) => ['player' => (string) $row['player'], 'product' => (string) $row['product'], 'at' => Carbon::parse($row['at'])], $rows);
    }

    public function servers(): View
    {
        return view('theme::servers.index', ['servers' => GameServer::where('show_on_site', true)->orderBy('sort')->get()]);
    }

    public function news(Settings $settings): View
    {
        abort_unless($settings->bool('modules.news'), 404);

        return view('theme::news.index', ['posts' => Post::published()->orderByDesc('pinned')->latest('published_at')->paginate(12)]);
    }

    public function post(Post $post, Settings $settings): View
    {
        abort_unless($settings->bool('modules.news') && $post->status === 'published' && $post->published_at?->lte(now()), 404);

        return view('theme::news.show', compact('post'));
    }

    public function page(Page $page): View
    {
        abort_unless($page->status === 'published', 404);

        return view('theme::pages.show', compact('page'));
    }
}
