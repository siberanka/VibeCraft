<?php

namespace App\Core\Theme;

use App\Core\Settings\Settings;

/**
 * Order and visibility of the home sections below the hero, per theme. A theme
 * declares its default order in layout.home; staff may reorder or hide sections.
 */
final class HomeLayout
{
    public const SECTIONS = ['strip', 'worlds', 'news', 'purchases', 'store'];

    public const NAV_KEYS = ['servers', 'store', 'news', 'support', 'topup', 'community'];

    public function __construct(private Settings $settings, private ThemeManager $themes) {}

    /** @return list<string> */
    public function defaults(string $theme): array
    {
        $declared = $this->themes->manifest($theme)['layout']['home'] ?? null;
        $order = is_array($declared) ? array_values(array_intersect($declared, self::SECTIONS)) : [];

        return array_values(array_unique([...$order, ...self::SECTIONS]));
    }

    /** @return list<array{key: string, visible: bool}> */
    public function rows(string $theme): array
    {
        $saved = $this->settings->get("appearance.home.$theme");
        $rows = [];
        foreach (is_array($saved) ? $saved : [] as $row) {
            if (is_array($row) && in_array($row['key'] ?? null, self::SECTIONS, true) && ! isset($rows[$row['key']])) {
                $rows[$row['key']] = ['key' => $row['key'], 'visible' => (bool) ($row['visible'] ?? true)];
            }
        }
        foreach ($this->defaults($theme) as $key) {
            $rows[$key] ??= ['key' => $key, 'visible' => true];
        }

        return array_values($rows);
    }

    /** @return list<string> */
    public function visible(string $theme): array
    {
        return array_values(array_map(fn ($row) => $row['key'], array_filter($this->rows($theme), fn ($row) => $row['visible'])));
    }

    /** @return list<string> */
    public function hiddenNavigation(): array
    {
        $hidden = $this->settings->get('appearance.nav_hidden');

        return array_values(array_intersect(self::NAV_KEYS, is_array($hidden) ? $hidden : []));
    }
}
