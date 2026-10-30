<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Models\StaffApplication;
use App\Models\StaffPosition;
use App\Models\VoteSite;
use Tests\TestCase;

class CommunityModulesTest extends TestCase
{
    public function test_vote_page_lists_only_visible_sites_with_safe_external_links(): void
    {
        VoteSite::query()->create(['name' => 'Listing A', 'url' => 'https://vote.example/a', 'cooldown_hours' => 24, 'is_visible' => true]);
        VoteSite::query()->create(['name' => 'Hidden B', 'url' => 'https://vote.example/b', 'is_visible' => false]);

        $this->get(route('community.vote'))->assertOk()->assertSee('Listing A')->assertDontSee('Hidden B')
            ->assertSee('rel="noopener noreferrer nofollow"', false);
    }

    public function test_vote_site_admin_rejects_non_https_links(): void
    {
        app(Settings::class)->set('security.require_admin_2fa', false);
        $this->actingAs($this->customer('owner'))->withSession(['auth.password_confirmed_at' => time()])
            ->post('/admin/vote-sites', ['name' => 'Bad', 'url' => 'javascript:alert(1)', 'cooldown_hours' => 24, 'sort' => 0])
            ->assertSessionHasErrors('url');
        $this->assertSame(0, VoteSite::query()->count());
    }

    private function position(): StaffPosition
    {
        return StaffPosition::query()->create(['name' => 'Builder', 'slug' => 'builder', 'questions' => "How old are you?\nWhy do you want to join?", 'is_open' => true]);
    }

    public function test_player_can_apply_once_until_reviewed_and_answers_are_escaped(): void
    {
        $position = $this->position();
        $player = $this->customer();

        $this->actingAs($player)->post(route('community.apply', $position), ['answers' => ['17', '<script>alert(1)</script>']])
            ->assertRedirect(route('community.positions'));
        $this->actingAs($player)->post(route('community.apply', $position), ['answers' => ['17', 'again']])
            ->assertSessionHasErrors('answers');
        $this->assertSame(1, StaffApplication::query()->count());

        app(Settings::class)->set('security.require_admin_2fa', false);
        $moderator = $this->customer('moderator');
        $application = StaffApplication::query()->sole();
        $this->actingAs($moderator)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('admin.applications.show', $application))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_answer_count_must_match_questions_and_closed_positions_reject(): void
    {
        $position = $this->position();
        $player = $this->customer();
        $this->actingAs($player)->post(route('community.apply', $position), ['answers' => ['only one']])->assertSessionHasErrors('answers');

        $position->update(['is_open' => false]);
        $this->actingAs($player)->post(route('community.apply', $position), ['answers' => ['a1', 'a2']])->assertNotFound();
        $this->assertSame(0, StaffApplication::query()->count());
    }

    public function test_only_reviewers_decide_and_a_decision_happens_once(): void
    {
        app(Settings::class)->set('security.require_admin_2fa', false);
        $position = $this->position();
        $application = StaffApplication::query()->create(['staff_position_id' => $position->id, 'user_id' => $this->customer()->id, 'answers' => [], 'status' => 'pending']);

        $this->actingAs($this->customer('support'))->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.applications.decide', $application), ['decision' => 'accepted'])->assertForbidden();

        $moderator = $this->customer('moderator');
        $this->actingAs($moderator)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.applications.decide', $application), ['decision' => 'rejected', 'note' => 'Try next season']);
        $this->actingAs($moderator)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.applications.decide', $application), ['decision' => 'accepted']);

        $this->assertSame('rejected', $application->fresh()->status);
    }
}
