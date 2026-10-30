<?php

namespace App\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Notifications\PlayerNotice;
use App\Core\Settings\Settings;
use App\Domain\Captcha\CaptchaVerifier;
use App\Jobs\NotifyDiscord;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function __construct(private Settings $settings) {}

    private function enabled(): void { abort_unless($this->settings->bool('modules.tickets'), 404); }

    public function index(Request $request)
    {
        $this->enabled();
        return view('theme::tickets.index', ['tickets' => $request->user()->tickets()->latest('last_activity_at')->paginate(15)]);
    }

    public function create(Request $request)
    {
        $this->enabled();
        return view('theme::tickets.create', ['categories' => Ticket::CATEGORIES, 'orders' => $request->user()->orders()->latest('id')->limit(50)->get()]);
    }

    public function store(Request $request, CaptchaVerifier $captcha)
    {
        $this->enabled();
        $captcha->verifyOrFail($request, 'ticket');
        $data = $request->validate(['subject' => ['required', 'string', 'max:140'], 'category' => ['required', Rule::in(Ticket::CATEGORIES)],
            'body' => ['required', 'string', 'min:5', 'max:10000'], 'order_id' => ['nullable', 'integer', Rule::exists('orders', 'id')->where('user_id', $request->user()->id)]]);
        $ticket = DB::transaction(function () use ($data, $request) {
            $ticket = Ticket::create(['user_id' => $request->user()->id, 'subject' => $data['subject'], 'category' => $data['category'], 'order_id' => $data['order_id'] ?? null, 'last_activity_at' => now()]);
            $ticket->messages()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
            NotifyDiscord::dispatch('ticket', $ticket->id)->afterCommit();
            return $ticket;
        });
        return redirect()->route('tickets.show', $ticket);
    }

    public function show(Request $request, Ticket $ticket)
    {
        $this->enabled();
        abort_unless($ticket->user_id === $request->user()->id, 404);
        return view('theme::tickets.show', ['ticket' => $ticket, 'messages' => $ticket->messages()->where('is_internal', false)->with('author')->get()]);
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $this->enabled();
        abort_unless($ticket->user_id === $request->user()->id, 404);
        $data = $request->validate(['body' => ['required', 'string', 'min:1', 'max:10000']]);
        DB::transaction(function () use ($request, $ticket, $data) {
            $locked = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status === 'closed', 409);
            $locked->messages()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
            $locked->update(['status' => 'customer_reply', 'last_activity_at' => now()]);
        });
        return back();
    }

    public function close(Request $request, Ticket $ticket)
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);
        $ticket->update(['status' => 'closed']);
        return back();
    }

    public function staffReply(Request $request, Ticket $ticket, AuditLogger $audit)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:10000'], 'is_internal' => ['sometimes', 'boolean']]);
        DB::transaction(function () use ($request, $ticket, $data) {
            $ticket->messages()->create(['user_id' => $request->user()->id, 'body' => $data['body'], 'is_staff' => true, 'is_internal' => $request->boolean('is_internal')]);
            if (! $request->boolean('is_internal')) { $ticket->update(['status' => 'answered', 'last_activity_at' => now()]); }
        });
        $audit->log('ticket.staff_reply', $ticket, ['internal' => $request->boolean('is_internal')], $request->user()->id);
        if (! $request->boolean('is_internal') && $ticket->user !== null && $ticket->user_id !== $request->user()->id) {
            $ticket->user->notify(new PlayerNotice('ticket_answered', ['subject' => $ticket->subject], '/tickets/'.$ticket->public_id));
        }
        return back()->with('status', __('Reply saved.'));
    }
}
