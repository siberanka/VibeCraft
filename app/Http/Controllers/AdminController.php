<?php

namespace App\Http\Controllers;

use App\Core\AdminCatalog;
use App\Core\Audit\AuditLogger;
use App\Core\Security\Permissions;
use App\Core\Settings\Settings;
use App\Core\Theme\ThemeManager;
use App\Domain\Bridge\BridgeCredentials;
use App\Domain\Commerce\CommandTemplate;
use App\Domain\Commerce\DeliveryQueue;
use App\Domain\Commerce\OrderLifecycle;
use App\Domain\Content\Markdown;
use App\Jobs\NotifyDiscord;
use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\GameServer;
use App\Models\Media;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function __construct(private Settings $settings, private AuditLogger $logger) {}

    private function catalog(string $resource): array
    {
        $spec = AdminCatalog::get($resource);
        Gate::authorize($spec['permission']);

        return $spec;
    }

    public function dashboard(Request $request)
    {
        $stats = [];
        if ($request->user()->can('users.view')) {
            $stats['Accounts'] = User::count();
        }
        if ($request->user()->can('orders.view')) {
            $stats['Paid orders'] = Order::where('status', Order::PAID)->count();
        }
        if ($request->user()->can('tickets.view')) {
            $stats['Open tickets'] = Ticket::where('status', '!=', 'closed')->count();
        }
        if ($request->user()->can('deliveries.manage')) {
            $stats['Pending deliveries'] = Delivery::whereIn('status', ['queued', 'failed', 'dead', 'manual_review'])->count();
        }

        return view('theme::admin.dashboard', ['stats' => $stats, 'recentOrders' => $request->user()->can('orders.view') ? Order::latest('id')->limit(8)->get() : collect()]);
    }

    public function index(string $resource)
    {
        $spec = $this->catalog($resource);
        $records = $spec['model']::query()->latest('id')->paginate(20);
        $columns = match ($resource) {
            'orders' => ['public_id' => 'Reference', 'status' => 'Status', 'total_minor' => 'Total (minor units)', 'fulfillment_status' => 'Delivery'],
            'tickets' => ['public_id' => 'Reference', 'subject' => 'Subject', 'status' => 'Status'],
            'users' => ['name' => 'Name', 'email' => 'Email', 'created_at' => 'Created'],
            'deliveries' => ['public_id' => 'Reference', 'status' => 'Status', 'attempts' => 'Attempts', 'last_error' => 'Last error'],
            default => array_combine(array_slice(array_keys($spec['schema']), 0, 4), array_map(fn ($f) => $f[0], array_slice($spec['schema'], 0, 4))),
        };

        return view('theme::admin.index', ['title' => __($spec['title']), 'resource' => $resource, 'records' => $records, 'columns' => $columns,
            'createUrl' => $spec['schema'] ? route('admin.create', $resource) : null,
            'editBase' => $spec['schema'] ? url('/admin/'.$resource) : null]);
    }

    public function create(string $resource)
    {
        $spec = $this->catalog($resource);
        abort_unless($spec['schema'], 405);

        return $this->form($resource, $spec, null);
    }

    public function edit(string $resource, int $id)
    {
        $spec = $this->catalog($resource);
        abort_unless($spec['schema'], 405);

        return $this->form($resource, $spec, $spec['model']::findOrFail($id));
    }

    private function form(string $resource, array $spec, $record)
    {
        $fields = [];
        foreach ($spec['schema'] as $name => $field) {
            $options = $field[3] ?? [];
            $isMedia = $options === Media::class;
            if (is_string($options)) {
                $options = $isMedia ? [] : $options::orderBy('id')->limit(1000)->pluck('name', 'id')->all();
            }
            $defaults = ['sort' => 0, 'nav_order' => 0, 'max_quantity' => 1, 'query_port' => 25565, 'bedrock_port' => 19132, 'min_subtotal_minor' => 0, 'max_uses_per_user' => 1];
            $value = $record?->getAttribute($name) ?? ($defaults[$name] ?? null);
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d\TH:i');
            }
            $fields[$name] = ['label' => __($field[0]), 'type' => $isMedia ? 'media' : ($field[2] ?? 'text'), 'value' => $value,
                'required' => str_contains($field[1], 'required'), 'options' => $options];
        }

        return view('theme::admin.form', ['title' => __($spec['title']), 'fields' => $fields, 'record' => $record,
            'action' => $record ? route('admin.update', [$resource, $record->id]) : route('admin.store', $resource), 'method' => $record ? 'PUT' : 'POST']);
    }

    private function save(Request $request, string $resource, ?int $id = null)
    {
        $spec = $this->catalog($resource);
        abort_unless($spec['schema'], 405);
        $model = $id ? $spec['model']::findOrFail($id) : new $spec['model'];
        $moneyFields = $resource === 'products' ? ['price' => 'price_minor'] : ($resource === 'coupons' ? ['min_subtotal' => 'min_subtotal_minor', 'discount' => 'value'] : []);
        foreach ($moneyFields as $display => $stored) {
            if (! $request->has($display)) {
                continue;
            }
            $input = $request->input($display);
            $minor = is_string($input) ? Money::parse($input) : null;
            if ($minor === null) {
                throw ValidationException::withMessages([$display => __('Enter a valid amount.')]);
            }
            $request->merge([$stored => $minor]);
        }
        $rules = [];
        foreach ($spec['schema'] as $name => $field) {
            $rules[$name] = explode('|', $field[1]);
            if (in_array($name, ['slug', 'code'], true)) {
                $rules[$name][] = Rule::unique($model->getTable(), $name)->ignore($model->getKey());
            }
        }
        $data = $request->validate($rules);
        foreach ($spec['schema'] as $name => $field) {
            if (($field[2] ?? '') === 'checkbox') {
                $data[$name] = $request->boolean($name);
            }
        }
        if (isset($data['body_markdown'])) {
            $data['body_html'] = Markdown::render($data['body_markdown']);
        }
        if (array_key_exists('description_markdown', $data)) {
            $data['description_html'] = Markdown::render($data['description_markdown']);
        }
        if ($resource === 'posts') {
            $data['author_id'] = $model->author_id ?? $request->user()->id;
            if (($data['status'] ?? '') === 'published' && empty($data['published_at'])) {
                $data['published_at'] = now();
            }
        }
        if ($resource === 'commands' && ($errors = CommandTemplate::validate($data['command'])) !== []) {
            throw ValidationException::withMessages(['command' => $errors]);
        }
        if ($resource === 'coupons' && $data['type'] === 'percent' && $data['value'] > 10000) {
            throw ValidationException::withMessages(['value' => __('Percentage must not exceed 100%.')]);
        }
        $model->fill($data);
        if ($resource === 'servers' && ! $model->exists) {
            $keys = BridgeCredentials::generate();
            $model->forceFill(['bridge_key_id' => $keys['key_id'], 'bridge_secret' => $keys['secret']]);
        }
        $model->save();
        Cache::forget('vibecraft.public_servers.v2');
        Cache::forget('vibecraft.nav_pages.v2');
        $this->logger->log($resource.($id ? '.updated' : '.created'), $model, ['fields' => array_keys($data)], $request->user()->id);
        $response = redirect()->route('admin.index', $resource)->with('status', __('Saved.'));
        if (isset($keys)) {
            $response->with('bridge_key_id', $keys['key_id'])->with('bridge_secret', $keys['secret']);
        }

        return $response;
    }

    public function store(Request $request, string $resource)
    {
        return $this->save($request, $resource);
    }

    public function update(Request $request, string $resource, int $id)
    {
        return $this->save($request, $resource, $id);
    }

    private const SETTING_RULES = [
        'site.name' => 'required|string|max:80', 'site.hero_title' => 'nullable|string|max:60', 'site.tagline' => 'nullable|string|max:120',
        'site.description' => 'nullable|string|max:300', 'site.announcement' => 'nullable|string|max:300',
        'site.java_address' => 'nullable|regex:/^[a-zA-Z0-9.:-]{1,253}$/', 'site.bedrock_address' => 'nullable|regex:/^[a-zA-Z0-9.:-]{1,253}$/',
        'site.bedrock_port' => 'integer|min:1|max:65535', 'site.footer_note' => 'nullable|string|max:300',
        'site.default_locale' => 'in:tr,en', 'social.discord_invite' => 'nullable|url:https|max:255',
        'site.logo_media_id' => 'nullable|integer|exists:media,id', 'site.hero_media_id' => 'nullable|integer|exists:media,id',
        'modules.store' => 'boolean', 'modules.news' => 'boolean', 'modules.tickets' => 'boolean', 'modules.wiki' => 'boolean',
        'auth.registration' => 'boolean', 'auth.require_email_verification' => 'boolean',
        'security.require_admin_2fa' => 'boolean', 'security.hsts' => 'boolean',
        'store.wallet_label' => 'required|string|max:40', 'store.show_recent_purchases' => 'boolean', 'store.gifts' => 'boolean',
    ];

    public function settings()
    {
        return view('theme::admin.settings', ['settings' => collect(self::SETTING_RULES)->map(fn ($rule, $key) => $this->settings->get($key))->all()]);
    }

    public function saveSettings(Request $request)
    {
        $input = $request->input('settings', []);
        abort_unless(is_array($input), 422);
        // Literal dotted keys must not be interpreted as nested user input by the validator.
        $values = [];
        foreach (self::SETTING_RULES as $key => $rule) {
            if (! array_key_exists($key, $input)) {
                continue;
            }
            $valid = validator(['value' => $input[$key]], ['value' => $rule])->validate();
            $values[$key] = $rule === 'boolean' ? (bool) $valid['value'] : $valid['value'];
        }
        $this->settings->setMany($values);
        $this->logger->log('settings.updated', null, ['fields' => array_keys($values)], $request->user()->id);

        return back()->with('status', __('Saved.'));
    }

    private const INTEGRATION_RULES = [
        'payments.stripe.enabled' => 'boolean', 'payments.stripe.publishable_key' => 'nullable|string|max:255',
        'payments.paytr.enabled' => 'boolean', 'payments.paytr.merchant_id' => 'nullable|digits_between:1,20',
        'payments.paytr.test_mode' => 'boolean', 'captcha.provider' => 'in:none,turnstile,hcaptcha,recaptcha',
        'captcha.site_key' => 'nullable|string|max:255', 'discord.client_id' => 'nullable|digits_between:5,25', 'discord.notify_orders' => 'boolean', 'discord.notify_tickets' => 'boolean',
    ];

    private const SECRET_KEYS = ['payments.stripe.secret_key', 'payments.stripe.webhook_secret', 'payments.paytr.merchant_key', 'payments.paytr.merchant_salt', 'captcha.secret', 'discord.webhook_url', 'discord.client_secret'];

    public function integrations()
    {
        $values = collect(self::INTEGRATION_RULES)->map(fn ($rule, $key) => $this->settings->get($key))->all();
        foreach (self::SECRET_KEYS as $key) {
            $values[$key] = '';
        }

        return view('theme::admin.settings', ['settings' => $values, 'action' => route('admin.integrations.update'), 'title' => __('Integrations'), 'secretKeys' => self::SECRET_KEYS]);
    }

    public function saveIntegrations(Request $request)
    {
        $input = $request->input('settings', []);
        abort_unless(is_array($input), 422);
        $values = [];
        $secrets = [];
        foreach (self::INTEGRATION_RULES as $key => $rule) {
            if (! array_key_exists($key, $input)) {
                continue;
            }
            $valid = validator(['value' => $input[$key]], ['value' => $rule])->validate();
            $values[$key] = $rule === 'boolean' ? (bool) $valid['value'] : $valid['value'];
        }
        foreach (self::SECRET_KEYS as $key) {
            if (! isset($input[$key]) || $input[$key] === '') {
                continue;
            }
            $valid = validator(['value' => $input[$key]], ['value' => 'required|string|max:500'])->validate();
            if ($key === 'discord.webhook_url' && ! NotifyDiscord::isValidWebhookUrl($valid['value'])) {
                throw ValidationException::withMessages(['settings' => __('Invalid Discord webhook URL.')]);
            }
            $secrets[$key] = $valid['value'];
        }
        DB::transaction(function () use ($values, $secrets) {
            $this->settings->setMany($values);
            foreach ($secrets as $key => $value) {
                $this->settings->setSecret($key, $value);
            }
        });
        $this->logger->log('integrations.updated', null, ['fields' => array_merge(array_keys($values), array_keys($secrets))], $request->user()->id);

        return back()->with('status', __('Saved.'));
    }

    public function themes(ThemeManager $manager)
    {
        return view('theme::admin.themes', ['themes' => collect($manager->available())->mapWithKeys(fn ($id) => [$id => $manager->manifest($id)])->all(), 'activeTheme' => $manager->active()]);
    }

    public function saveTheme(Request $request, ThemeManager $manager)
    {
        $data = $request->validate(['theme' => ['required', Rule::in($manager->available())]]);
        $this->settings->set('theme.active', $data['theme']);
        $this->logger->log('theme.changed', null, ['theme' => $data['theme']], $request->user()->id);

        return back()->with('status', __('Saved.'));
    }

    public function audit()
    {
        return view('theme::admin.audit', ['records' => AuditLog::latest('id')->paginate(30)]);
    }

    public function ticket(Ticket $ticket)
    {
        return view('theme::admin.ticket', ['ticket' => $ticket->load('user'), 'messages' => $ticket->messages()->with('author')->get()]);
    }

    public function order(Order $order)
    {
        return view('theme::admin.order', ['order' => $order->load(['user', 'items', 'payments', 'deliveries'])]);
    }

    public function user(User $user)
    {
        return view('theme::admin.user', ['member' => $user->load('roles'), 'roles' => array_keys(Permissions::ROLES)]);
    }

    public function userAction(Request $request, User $user)
    {
        $data = $request->validate(['action' => ['required', 'in:ban,unban,role'], 'reason' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in(array_keys(Permissions::ROLES))]]);
        if ($data['action'] === 'role') {
            Gate::authorize('roles.manage');
            abort_unless($request->user()->hasRole('owner') && ! empty($data['role']), 403);
        } else {
            Gate::authorize('users.ban');
        }
        DB::transaction(function () use ($request, $user, $data) {
            // Serialize changes that could remove the last owner.
            $owners = User::whereHas('roles', fn ($q) => $q->where('name', 'owner'))->orderBy('id')->lockForUpdate()->get();
            $target = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($data['action'] === 'role') {
                if ($target->hasRole('owner') && $data['role'] !== 'owner') {
                    abort_if($owners->count() <= 1, 409, 'The last owner cannot be removed.');
                }
                $target->syncRoles([$data['role']]);
            } else {
                abort_if($target->id === $request->user()->id || $target->hasRole('owner'), 403);
                abort_if($target->isStaff() && ! $request->user()->hasRole('owner'), 403);
                if ($data['action'] === 'ban') {
                    abort_unless(mb_strlen(trim($data['reason'] ?? '')) >= 5, 422);
                    $target->forceFill(['banned_at' => now(), 'ban_reason' => $data['reason']])->save();
                } else {
                    $target->forceFill(['banned_at' => null, 'ban_reason' => null])->save();
                }
            }
        });
        $this->logger->log('user.'.$data['action'], $user, ['role' => $data['role'] ?? null], $request->user()->id);

        return back()->with('status', __('Saved.'));
    }

    public function deliveryAction(Request $request, Delivery $delivery, DeliveryQueue $queue)
    {
        $data = $request->validate(['action' => ['required', 'in:retry,cancel']]);
        if ($data['action'] === 'retry') {
            abort_unless(in_array($delivery->status, ['dead', 'failed', 'manual_review'], true), 409);
            abort_if($delivery->phase === 'purchase' && $delivery->order?->status !== Order::PAID, 409);
            $queue->retry($delivery, $request->user()->id);
        } else {
            abort_unless(in_array($delivery->status, ['queued', 'dead', 'failed', 'manual_review'], true), 409);
            $queue->cancel($delivery, $request->user()->id);
        }

        return back()->with('status', __('Saved.'));
    }

    public function rotateKey(Request $request, GameServer $server)
    {
        $secret = BridgeCredentials::rotate($server);
        $this->logger->log('server.key_rotated', $server, [], $request->user()->id);

        return back()->with('bridge_key_id', $server->bridge_key_id)->with('bridge_secret', $secret);
    }

    public function orderAction(Request $request, Order $order, OrderLifecycle $lifecycle)
    {
        $data = $request->validate(['action' => ['required', 'in:cancel,refund'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        if ($data['action'] === 'refund') {
            Gate::authorize('finance.refund');
            // External refunds must be confirmed by signed provider events.
            abort_unless($order->payment_method === 'wallet', 409, 'Refund external payments through their provider.');
            $lifecycle->markRefunded($order, 'wallet');
        } else {
            $lifecycle->cancel($order, $data['reason']);
        }

        return back()->with('status', __('Saved.'));
    }
}
