<?php

namespace App\Core;

use App\Models\Badge;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CreditPackage;
use App\Models\Delivery;
use App\Models\ForumCategory;
use App\Models\GameServer;
use App\Models\Media;
use App\Models\Order;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductCommand;
use App\Models\StaffPosition;
use App\Models\Ticket;
use App\Models\User;
use App\Models\VoteSite;

/** Explicit schemas are the boundary between a staff form and persisted data. */
final class AdminCatalog
{
    public static function get(string $resource): array
    {
        $catalog = [
            'categories' => [Category::class, 'Categories', 'store.manage', [
                'name' => ['Name', 'required|string|max:80'], 'slug' => ['Slug', 'required|alpha_dash:ascii|max:100'],
                'description' => ['Description', 'nullable|string|max:500', 'textarea'],
                'image_media_id' => ['Image', 'nullable|integer|exists:media,id', 'select', Media::class],
                'sort' => ['Sort order', 'required|integer|min:0|max:100000', 'number'], 'is_visible' => ['Visible', 'boolean', 'checkbox'],
            ]],
            'products' => [Product::class, 'Products', 'store.manage', [
                'category_id' => ['Category', 'required|integer|exists:categories,id', 'select', Category::class],
                'name' => ['Name', 'required|string|max:120'], 'slug' => ['Slug', 'required|alpha_dash:ascii|max:140'],
                'summary' => ['Summary', 'nullable|string|max:300'], 'description_markdown' => ['Description', 'nullable|string|max:50000', 'textarea'],
                'image_media_id' => ['Image', 'nullable|integer|exists:media,id', 'select', Media::class],
                'price_minor' => ['Price in minor units', 'required|integer|min:0|max:100000000', 'number'],
                'stock' => ['Stock (blank for unlimited)', 'nullable|integer|min:0|max:1000000', 'number'],
                'max_quantity' => ['Maximum quantity', 'required|integer|min:1|max:100', 'number'],
                'per_user_limit' => ['Per-account limit', 'nullable|integer|min:1|max:10000', 'number'],
                'duration_days' => ['Duration in days', 'nullable|integer|min:1|max:3650', 'number'],
                'requires_link' => ['Verified Minecraft account required', 'boolean', 'checkbox'],
                'chest_delivery' => ['Deliver through the chest (player claims later)', 'boolean', 'checkbox'],
                'is_visible' => ['Visible', 'boolean', 'checkbox'], 'is_featured' => ['Featured', 'boolean', 'checkbox'],
                'sort' => ['Sort order', 'required|integer|min:0|max:100000', 'number'],
            ]],
            'commands' => [ProductCommand::class, 'Product commands', 'store.manage', [
                'product_id' => ['Product', 'required|integer|exists:products,id', 'select', Product::class],
                'game_server_id' => ['Server', 'required|integer|exists:game_servers,id', 'select', GameServer::class],
                'phase' => ['Phase', 'required|in:purchase,expire,refund', 'select', ['purchase' => 'Purchase', 'expire' => 'Expiry', 'refund' => 'Refund']],
                'command' => ['Console command', 'required|string|max:255'], 'require_online' => ['Player must be online', 'boolean', 'checkbox'],
                'sort' => ['Sort order', 'required|integer|min:0|max:100000', 'number'],
            ]],
            'credit-packages' => [CreditPackage::class, 'Credit packages', 'store.manage', [
                'name' => ['Name', 'required|string|max:40'],
                'price_minor' => ['Price (minor units)', 'required|integer|min:1|max:100000000', 'number'],
                'bonus_minor' => ['Bonus credit (minor units)', 'required|integer|min:0|max:100000000|lte:price_minor', 'number'],
                'sort' => ['Sort order', 'required|integer|min:0|max:100000', 'number'],
                'is_visible' => ['Visible', 'boolean', 'checkbox'],
            ]],
            'coupons' => [Coupon::class, 'Coupons', 'coupons.manage', [
                'code' => ['Code', 'required|regex:/^[A-Z0-9_-]{2,40}$/'],
                'type' => ['Type', 'required|in:percent,fixed', 'select', ['percent' => 'Percentage (100 = 1%)', 'fixed' => 'Fixed minor units']],
                'value' => ['Discount value', 'required|integer|min:1|max:100000000', 'number'],
                'min_subtotal_minor' => ['Minimum subtotal', 'required|integer|min:0|max:100000000', 'number'],
                'max_uses' => ['Maximum uses', 'nullable|integer|min:1|max:1000000', 'number'],
                'max_uses_per_user' => ['Uses per account', 'required|integer|min:1|max:1000', 'number'],
                'starts_at' => ['Starts at', 'nullable|date', 'datetime-local'], 'ends_at' => ['Ends at', 'nullable|date|after:starts_at', 'datetime-local'],
                'is_active' => ['Active', 'boolean', 'checkbox'],
            ]],
            'servers' => [GameServer::class, 'Servers', 'servers.manage', [
                'name' => ['Name', 'required|string|max:80'], 'slug' => ['Slug', 'required|alpha_dash:ascii|max:80'],
                'description' => ['Description', 'nullable|string|max:255', 'textarea'],
                'java_address' => ['Java address', 'nullable|regex:/^[a-zA-Z0-9.:-]{1,253}$/'],
                'bedrock_address' => ['Bedrock address', 'nullable|regex:/^[a-zA-Z0-9.:-]{1,253}$/'],
                'bedrock_port' => ['Bedrock port', 'required|integer|min:1|max:65535', 'number'],
                'query_host' => ['Status query host', 'nullable|regex:/^[a-zA-Z0-9.:-]{1,253}$/'],
                'query_port' => ['Status query port', 'required|integer|min:1|max:65535', 'number'],
                'query_edition' => ['Edition', 'required|in:java,bedrock', 'select', ['java' => 'Java', 'bedrock' => 'Bedrock']],
                'show_on_site' => ['Show on site', 'boolean', 'checkbox'], 'bridge_enabled' => ['Bridge enabled', 'boolean', 'checkbox'],
                'sort' => ['Sort order', 'required|integer|min:0|max:100000', 'number'],
            ]],
            'posts' => [Post::class, 'News', 'content.manage', [
                'title' => ['Title', 'required|string|max:160'], 'slug' => ['Slug', 'required|alpha_dash:ascii|max:180'],
                'category' => ['Category', 'nullable|string|max:60'], 'excerpt' => ['Summary', 'nullable|string|max:400', 'textarea'],
                'body_markdown' => ['Content (Markdown)', 'required|string|max:100000', 'textarea'],
                'cover_media_id' => ['Cover image', 'nullable|integer|exists:media,id', 'select', Media::class],
                'status' => ['Status', 'required|in:draft,published', 'select', ['draft' => 'Draft', 'published' => 'Published']],
                'published_at' => ['Publish at', 'nullable|date', 'datetime-local'], 'pinned' => ['Pinned', 'boolean', 'checkbox'],
            ]],
            'pages' => [Page::class, 'Pages', 'content.manage', [
                'title' => ['Title', 'required|string|max:160'], 'slug' => ['Slug', 'required|alpha_dash:ascii|max:180'],
                'body_markdown' => ['Content (Markdown)', 'required|string|max:100000', 'textarea'],
                'status' => ['Status', 'required|in:draft,published', 'select', ['draft' => 'Draft', 'published' => 'Published']],
                'show_in_nav' => ['Show in navigation', 'boolean', 'checkbox'], 'nav_order' => ['Sort order', 'required|integer|min:0|max:100000', 'number'],
            ]],
            'badges' => [Badge::class, 'Badges', 'users.manage', [
                'name' => ['Name', 'required|string|max:40'], 'slug' => ['Slug', 'required|alpha_dash:ascii|max:60'],
                'color' => ['Colour', 'required|in:#3db7a6,#ff754a,#d4b273,#8e7cc3,#e05a6b,#5dade2,#87d5ad', 'select', ['#3db7a6' => 'Teal', '#ff754a' => 'Orange', '#d4b273' => 'Gold', '#8e7cc3' => 'Violet', '#e05a6b' => 'Red', '#5dade2' => 'Blue', '#87d5ad' => 'Green']],
                'description' => ['Description', 'nullable|string|max:160'], 'sort' => ['Sort order', 'required|integer|min:0|max:100000', 'number'],
            ]],
            'forum-categories' => [ForumCategory::class, 'Forum categories', 'forum.moderate', [
                'name' => ['Name', 'required|string|max:80'], 'slug' => ['Slug', 'required|alpha_dash:ascii|max:100'],
                'description' => ['Description', 'nullable|string|max:300', 'textarea'],
                'staff_only_topics' => ['Only staff start topics', 'boolean', 'checkbox'],
                'sort' => ['Sort order', 'required|integer|min:0|max:100000', 'number'], 'is_visible' => ['Visible', 'boolean', 'checkbox'],
            ]],
            'vote-sites' => [VoteSite::class, 'Vote sites', 'content.manage', [
                'name' => ['Name', 'required|string|max:80'], 'url' => ['Vote link', 'required|url:https|max:255'],
                'cooldown_hours' => ['Hours between votes', 'required|integer|min:1|max:168', 'number'],
                'sort' => ['Sort order', 'required|integer|min:0|max:100000', 'number'], 'is_visible' => ['Visible', 'boolean', 'checkbox'],
            ]],
            'positions' => [StaffPosition::class, 'Staff positions', 'applications.review', [
                'name' => ['Name', 'required|string|max:80'], 'slug' => ['Slug', 'required|alpha_dash:ascii|max:100'],
                'description' => ['Description', 'nullable|string|max:2000', 'textarea'],
                'questions' => ['Questions (one per line, up to 10)', 'required|string|max:3000', 'textarea'],
                'is_open' => ['Accepting applications', 'boolean', 'checkbox'], 'sort' => ['Sort order', 'required|integer|min:0|max:100000', 'number'],
            ]],
            'orders' => [Order::class, 'Orders', 'orders.view', []],
            'tickets' => [Ticket::class, 'Support tickets', 'tickets.view', []],
            'users' => [User::class, 'Accounts', 'users.view', []],
            'deliveries' => [Delivery::class, 'Deliveries', 'deliveries.manage', []],
        ];
        abort_unless(isset($catalog[$resource]), 404);
        [$model, $title, $permission, $schema] = $catalog[$resource];

        return compact('model', 'title', 'permission', 'schema');
    }
}
