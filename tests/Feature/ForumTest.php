<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumThread;
use Tests\TestCase;

class ForumTest extends TestCase
{
    private function category(bool $staffOnly = false): ForumCategory
    {
        return ForumCategory::query()->create(['name' => $staffOnly ? 'News' : 'General', 'slug' => $staffOnly ? 'news' : 'general', 'staff_only_topics' => $staffOnly, 'is_visible' => true]);
    }

    public function test_members_start_topics_and_reply_with_escaped_markdown(): void
    {
        $category = $this->category();
        $player = $this->customer();

        $this->actingAs($player)->post(route('forum.store', $category), ['title' => 'Hello world', 'body' => '**hi** <img src=x onerror=alert(1)>'])->assertRedirect();
        $thread = ForumThread::query()->sole();
        $this->actingAs($this->customer())->post(route('forum.reply', $thread), ['body' => '[x](javascript:alert(1))'])->assertRedirect();

        $html = $this->get(route('forum.thread', $thread))->assertOk()->getContent();
        $this->assertStringContainsString('<strong>hi</strong>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->assertSame(1, $thread->fresh()->replies);
    }

    public function test_locked_hidden_and_staff_only_rules_are_enforced_server_side(): void
    {
        $news = $this->category(true);
        $player = $this->customer();
        $this->actingAs($player)->post(route('forum.store', $news), ['title' => 'Not staff', 'body' => 'nope'])->assertForbidden();

        $general = $this->category();
        $this->actingAs($player)->post(route('forum.store', $general), ['title' => 'Topic one', 'body' => 'first']);
        $thread = ForumThread::query()->sole();
        $thread->forceFill(['locked' => true])->save();
        $this->actingAs($this->customer())->post(route('forum.reply', $thread), ['body' => 'late'])->assertSessionHasErrors('body');
        $this->assertSame(1, ForumPost::query()->count());

        $thread->forceFill(['locked' => false, 'hidden' => true])->save();
        $this->get(route('forum.thread', $thread))->assertNotFound();
    }

    public function test_only_moderators_moderate_and_hidden_replies_disappear_for_players(): void
    {
        app(Settings::class)->set('security.require_admin_2fa', false);
        $general = $this->category();
        $player = $this->customer();
        $this->actingAs($player)->post(route('forum.store', $general), ['title' => 'Topic one', 'body' => 'secret spam']);
        $thread = ForumThread::query()->sole();
        $post = ForumPost::query()->sole();

        $this->actingAs($player)->post(route('forum.moderate', $thread), ['action' => 'hide_post', 'post' => $post->id])->assertForbidden();
        $this->actingAs($this->customer('moderator'))->post(route('forum.moderate', $thread), ['action' => 'hide_post', 'post' => $post->id])->assertRedirect();

        $this->actingAs($this->customer())->get(route('forum.thread', $thread))->assertOk()->assertDontSee('secret spam');
    }
}
