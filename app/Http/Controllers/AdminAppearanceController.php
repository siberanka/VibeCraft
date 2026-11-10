<?php

namespace App\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\Settings;
use App\Core\Theme\CustomCss;
use App\Core\Theme\HomeLayout;
use App\Core\Theme\ThemeManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminAppearanceController extends Controller
{
    public function __construct(private Settings $settings, private ThemeManager $themes, private HomeLayout $layout) {}

    public function edit(Request $request): View
    {
        $theme = in_array($request->query('theme'), $this->themes->available(), true) ? (string) $request->query('theme') : $this->themes->active();

        return view('theme::admin.appearance', [
            'theme' => $theme,
            'themeNames' => collect($this->themes->available())->mapWithKeys(fn ($id) => [$id => $this->themes->manifest($id)['name'] ?? $id])->all(),
            'rows' => $this->layout->rows($theme),
            'navHidden' => $this->layout->hiddenNavigation(),
            'art' => $this->themes->art($theme),
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'theme' => ['required', Rule::in($this->themes->available())],
            'sections' => ['required', 'array'],
            'sections.*.position' => ['required', 'integer', 'min:1', 'max:20'],
            'sections.*.visible' => ['nullable', 'boolean'],
            'nav_hidden' => ['nullable', 'array'],
            'nav_hidden.*' => ['string', Rule::in(HomeLayout::NAV_KEYS)],
            'art_mode' => ['nullable', Rule::in(['default', 'own', 'none'])],
            'art_media_id' => ['nullable', 'integer', 'exists:media,id', 'required_if:art_mode,own'],
        ]);

        $defaults = $this->layout->defaults($data['theme']);
        $rows = [];
        foreach ($defaults as $index => $key) {
            $input = $data['sections'][$key] ?? null;
            $rows[] = ['key' => $key, 'visible' => is_array($input) && (bool) ($input['visible'] ?? false), 'rank' => [is_array($input) ? (int) $input['position'] : 99, $index]];
        }
        usort($rows, fn ($a, $b) => $a['rank'] <=> $b['rank']);
        $rows = array_map(fn ($row) => ['key' => $row['key'], 'visible' => $row['visible']], $rows);

        $values = ["appearance.home.{$data['theme']}" => $rows, 'appearance.nav_hidden' => array_values(array_unique($data['nav_hidden'] ?? []))];
        if (isset($data['art_mode'])) {
            // Bundled picture (null), the owner's own image (media id) or no picture at all.
            $values["appearance.art.{$data['theme']}"] = match ($data['art_mode']) {
                'own' => (int) $data['art_media_id'],
                'none' => 'none',
                default => null,
            };
        }
        $this->settings->setMany($values);
        $audit->log('appearance.layout_updated', null, ['theme' => $data['theme']], $request->user()->id);

        return redirect()->route('admin.appearance', ['theme' => $data['theme']])->with('status', __('Saved.'));
    }

    public function css(): View
    {
        return view('theme::admin.custom-css', ['source' => (string) $this->settings->get('appearance.custom_css_source')]);
    }

    public function saveCss(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['css' => ['nullable', 'string', 'max:'.CustomCss::MAX_BYTES]]);
        $source = (string) ($data['css'] ?? '');
        $compiled = CustomCss::compile($source);
        if ($compiled['errors'] !== []) {
            return back()->withErrors(['css' => $compiled['errors']])->withInput();
        }

        $this->settings->setMany(['appearance.custom_css_source' => $source, 'appearance.custom_css' => $compiled['css']]);
        $audit->log('appearance.css_updated', null, ['bytes' => strlen($compiled['css'])], $request->user()->id);

        return redirect()->route('admin.custom-css')->with('status', __('Saved.'));
    }

    /** Public stylesheet; served from this origin so the style-src policy stays 'self'. */
    public function stylesheet(Request $request): Response
    {
        $css = (string) $this->settings->get('appearance.custom_css');
        abort_if($css === '', 404);
        $version = substr(hash('sha256', $css), 0, 12);

        return response($css, 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $request->query('v') === $version ? 'public, max-age=31536000, immutable' : 'public, max-age=300',
        ]);
    }
}
