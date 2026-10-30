<?php

namespace App\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Models\MenuItem;
use App\Support\SiteLink;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminMenuController extends Controller
{
    private const PER_LOCATION = 20;

    public function __construct(private AuditLogger $audit) {}

    public function index(): View
    {
        $items = MenuItem::query()->orderBy('sort')->orderBy('id')->get()->groupBy('location');

        return view('theme::admin.menus', ['header' => $items->get('header', collect()), 'footer' => $items->get('footer', collect())]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        if (MenuItem::query()->where('location', $data['location'])->count() >= self::PER_LOCATION) {
            throw ValidationException::withMessages(['label' => __('This menu is full.')]);
        }
        $item = MenuItem::query()->create($data);
        $this->changed('menu.created', $item, $request);

        return redirect()->route('admin.menus.index')->with('status', __('Saved.'));
    }

    public function update(Request $request, MenuItem $item): RedirectResponse
    {
        $item->update($this->validated($request, false));
        $this->changed('menu.updated', $item, $request);

        return redirect()->route('admin.menus.index')->with('status', __('Saved.'));
    }

    public function destroy(Request $request, MenuItem $item): RedirectResponse
    {
        $item->delete();
        $this->changed('menu.deleted', $item, $request);

        return redirect()->route('admin.menus.index')->with('status', __('Saved.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'location' => [$creating ? 'required' : 'prohibited', Rule::in(MenuItem::LOCATIONS)],
            'label' => ['required', 'string', 'max:40'],
            'url' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) {
                if (! SiteLink::valid($value)) {
                    $fail(__('Use a site path starting with / or an https:// address.'));
                }
            }],
            'sort' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);
        $data['sort'] = (int) ($data['sort'] ?? 0);
        $data['new_tab'] = $request->boolean('new_tab');
        $data['is_visible'] = $creating || $request->boolean('is_visible');

        return $data;
    }

    private function changed(string $action, MenuItem $item, Request $request): void
    {
        Cache::forget(MenuItem::CACHE_KEY);
        $this->audit->log($action, $item, ['location' => $item->location], $request->user()->id);
    }
}
