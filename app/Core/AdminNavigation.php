<?php

namespace App\Core;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Administration menu in two levels: a short row of areas and the pages of the
 * current area. Entries the signed-in staff member may not open are left out,
 * and an area without any visible page disappears.
 */
final class AdminNavigation
{
    /**
     * Area => [label, pages]. Page: [label, path, icon, permission, request patterns].
     *
     * @var array<string, array{0: string, 1: list<array{0: string, 1: string, 2: string, 3: ?string, 4: list<string>}>}>
     */
    private const AREAS = [
        'overview' => ['Overview', [
            ['Overview', 'admin', 'settings', null, ['admin']],
        ]],
        'store' => ['Store', [
            ['Products', 'admin/products', 'chest', 'store.manage', ['admin/products', 'admin/products/*']],
            ['Categories', 'admin/categories', 'chest', 'store.manage', ['admin/categories', 'admin/categories/*']],
            ['Product commands', 'admin/commands', 'server', 'store.manage', ['admin/commands', 'admin/commands/*']],
            ['Orders', 'admin/orders', 'receipt', 'orders.view', ['admin/orders', 'admin/orders/*']],
            ['Deliveries', 'admin/deliveries', 'chest', 'deliveries.manage', ['admin/deliveries', 'admin/deliveries/*']],
            ['Coupons', 'admin/coupons', 'gem', 'coupons.manage', ['admin/coupons', 'admin/coupons/*']],
            ['Credit packages', 'admin/credit-packages', 'gem', 'store.manage', ['admin/credit-packages', 'admin/credit-packages/*']],
            ['Credit codes', 'admin/gift-codes', 'gem', 'finance.wallet_adjust', ['admin/gift-codes', 'admin/gift-codes/*']],
        ]],
        'content' => ['Content', [
            ['News', 'admin/posts', 'news', 'content.manage', ['admin/posts', 'admin/posts/*']],
            ['Pages', 'admin/pages', 'news', 'content.manage', ['admin/pages', 'admin/pages/*']],
            ['Wiki articles', 'admin/wiki/articles', 'news', 'content.manage', ['admin/wiki/articles', 'admin/wiki/articles/*']],
            ['Wiki categories', 'admin/wiki/categories', 'compass', 'content.manage', ['admin/wiki/categories', 'admin/wiki/categories/*']],
            ['Media library', 'admin/media', 'news', 'content.manage', ['admin/media']],
            ['Legal documents', 'admin/legal', 'receipt', 'settings.manage', ['admin/legal', 'admin/legal/*']],
        ]],
        'community' => ['Community', [
            ['Users', 'admin/users', 'account', 'users.view', ['admin/users', 'admin/users/*']],
            ['Support', 'admin/tickets', 'support', 'tickets.view', ['admin/tickets', 'admin/tickets/*']],
            ['Forum categories', 'admin/forum-categories', 'news', 'forum.moderate', ['admin/forum-categories', 'admin/forum-categories/*']],
            ['Staff applications', 'admin/applications', 'account', 'applications.review', ['admin/applications', 'admin/applications/*']],
            ['Staff positions', 'admin/positions', 'account', 'applications.review', ['admin/positions', 'admin/positions/*']],
            ['Vote sites', 'admin/vote-sites', 'check', 'content.manage', ['admin/vote-sites', 'admin/vote-sites/*']],
            ['Badges', 'admin/badges', 'shield', 'users.manage', ['admin/badges', 'admin/badges/*']],
        ]],
        'appearance' => ['Appearance', [
            ['Themes', 'admin/themes', 'compass', 'appearance.manage', ['admin/themes']],
            ['Theme colours', 'admin/themes/{theme}/colours', 'gem', 'appearance.manage', ['admin/themes/*/colours']],
            ['Theme settings', 'admin/appearance', 'settings', 'appearance.manage', ['admin/appearance']],
            ['Menus', 'admin/menus', 'menu', 'appearance.manage', ['admin/menus', 'admin/menus/*']],
            ['Custom CSS', 'admin/custom-css', 'compass', 'appearance.manage', ['admin/custom-css']],
        ]],
        'system' => ['System', [
            ['Servers', 'admin/servers', 'server', 'servers.manage', ['admin/servers', 'admin/servers/*']],
            ['Settings', 'admin/settings', 'settings', 'settings.manage', ['admin/settings']],
            ['Integrations', 'admin/integrations', 'settings', 'integrations.manage', ['admin/integrations']],
            ['Audit log', 'admin/audit', 'shield', 'audit.view', ['admin/audit']],
        ]],
    ];

    /**
     * @return array{areas: list<array{key: string, label: string, url: string, current: bool}>, pages: list<array{label: string, url: string, icon: string, current: bool}>}
     */
    public static function for(Request $request, string $activeTheme): array
    {
        $areas = [];
        $pages = [];
        foreach (self::AREAS as $key => [$label, $entries]) {
            $visible = [];
            foreach ($entries as [$pageLabel, $path, $icon, $permission, $patterns]) {
                if ($permission !== null && Gate::denies($permission)) {
                    continue;
                }
                $visible[] = [
                    'label' => $pageLabel,
                    'url' => url(str_replace('{theme}', $activeTheme, $path)),
                    'icon' => $icon,
                    'current' => $request->is(...$patterns),
                ];
            }
            if ($visible === []) {
                continue;
            }
            $current = in_array(true, array_column($visible, 'current'), true);
            $areas[] = ['key' => $key, 'label' => $label, 'url' => $visible[0]['url'], 'current' => $current];
            if ($current) {
                $pages = $visible;
            }
        }

        return ['areas' => $areas, 'pages' => count($pages) > 1 ? $pages : []];
    }
}
