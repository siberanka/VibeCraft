<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Models\StaffApplication;
use App\Models\StaffPosition;
use App\Models\Ticket;
use App\Notifications\PlayerNotice;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlayerNoticesTest extends TestCase
{
    public function test_staff_reply_and_application_decision_notify_the_player(): void
    {
        app(Settings::class)->set('security.require_admin_2fa', false);
        $player = $this->customer();
        $ticket = Ticket::query()->create(['user_id' => $player->id, 'subject' => 'Help', 'category' => 'general', 'status' => 'open']);
        $staff = $this->customer('moderator');

        $this->actingAs($staff)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.tickets.reply', $ticket), ['body' => 'Hi']);
        $this->actingAs($staff)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.tickets.reply', $ticket), ['body' => 'Note', 'is_internal' => 1]);
        Notification::assertSentToTimes($player, PlayerNotice::class, 1);

        $position = StaffPosition::query()->create(['name' => 'Builder', 'slug' => 'builder', 'questions' => 'Why?', 'is_open' => true]);
        $application = StaffApplication::query()->create(['staff_position_id' => $position->id, 'user_id' => $player->id, 'answers' => [], 'status' => 'pending']);
        $this->actingAs($staff)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.applications.decide', $application), ['decision' => 'accepted']);
        Notification::assertSentTo($player, PlayerNotice::class, fn (PlayerNotice $n) => $n->kind === 'application_accepted');
    }

    public function test_notification_page_shows_only_own_notices_and_marks_them_read(): void
    {
        $player = $this->customer();
        $other = $this->customer();
        foreach ([[$player, 'Mine'], [$other, 'Theirs']] as [$user, $label]) {
            $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => PlayerNotice::class,
                'data' => ['kind' => 'ticket_answered', 'params' => ['subject' => $label], 'path' => '/tickets']]);
        }

        $this->actingAs($player)->get(route('account.notifications'))->assertOk()->assertSee('Mine')->assertDontSee('Theirs');
        $this->assertSame(0, $player->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }
}
