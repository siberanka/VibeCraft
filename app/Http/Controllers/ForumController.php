<?php

namespace App\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Domain\Captcha\CaptchaVerifier;
use App\Domain\Content\Markdown;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumThread;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ForumController extends Controller
{
    public function index(): View
    {
        $categories = ForumCategory::query()->where('is_visible', true)->orderBy('sort')->orderBy('id')
            ->withCount(['threads' => fn ($q) => $q->where('hidden', false)])->get();

        return view('theme::forum.index', ['categories' => $categories]);
    }

    public function category(Request $request, ForumCategory $category): View
    {
        abort_unless($category->is_visible, 404);
        $threads = $category->threads()->where('hidden', false)->with('author:id,name')
            ->orderByDesc('pinned')->orderByDesc('last_post_at')->paginate(25);

        return view('theme::forum.category', ['category' => $category, 'threads' => $threads, 'canStart' => $this->canStart($request, $category)]);
    }

    public function thread(Request $request, ForumThread $thread): View
    {
        $category = $thread->category;
        $moderator = (bool) $request->user()?->can('forum.moderate');
        abort_unless($category !== null && $category->is_visible && (! $thread->hidden || $moderator), 404);
        $posts = $thread->posts()->when(! $moderator, fn ($q) => $q->where('hidden', false))->with('author:id,name')->orderBy('id')->paginate(20);

        return view('theme::forum.thread', ['thread' => $thread, 'category' => $category, 'posts' => $posts, 'moderator' => $moderator]);
    }

    public function create(Request $request, ForumCategory $category): View
    {
        abort_unless($category->is_visible && $this->canStart($request, $category), 403);

        return view('theme::forum.create', ['category' => $category]);
    }

    public function store(Request $request, ForumCategory $category, CaptchaVerifier $captcha): RedirectResponse
    {
        abort_unless($category->is_visible && $this->canStart($request, $category), 403);
        $data = $request->validate(['title' => ['required', 'string', 'min:4', 'max:120'], 'body' => ['required', 'string', 'min:2', 'max:10000']]);
        $captcha->verifyOrFail($request, 'forum');

        $thread = DB::transaction(function () use ($request, $category, $data) {
            $thread = ForumThread::query()->create([
                'forum_category_id' => $category->id, 'user_id' => $request->user()->id, 'title' => trim($data['title']), 'last_post_at' => now(),
            ]);
            ForumPost::query()->create([
                'forum_thread_id' => $thread->id, 'user_id' => $request->user()->id,
                'body_markdown' => $data['body'], 'body_html' => Markdown::render($data['body']),
            ]);

            return $thread;
        });

        return redirect()->route('forum.thread', $thread);
    }

    public function reply(Request $request, ForumThread $thread, CaptchaVerifier $captcha): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:10000']]);
        $captcha->verifyOrFail($request, 'forum');

        $posted = DB::transaction(function () use ($request, $thread, $data) {
            $locked = ForumThread::query()->whereKey($thread->id)->lockForUpdate()->firstOrFail();
            $category = $locked->category;
            if ($category === null || ! $category->is_visible || $locked->hidden || ($locked->locked && ! $request->user()->can('forum.moderate'))) {
                return false;
            }
            ForumPost::query()->create([
                'forum_thread_id' => $locked->id, 'user_id' => $request->user()->id,
                'body_markdown' => $data['body'], 'body_html' => Markdown::render($data['body']),
            ]);
            $locked->forceFill(['replies' => $locked->replies + 1, 'last_post_at' => now()])->save();

            return true;
        });

        if (! $posted) {
            return redirect()->route('forum.thread', $thread)->withErrors(['body' => __('This topic is closed for replies.')]);
        }

        $lastPage = max(1, (int) ceil(($thread->fresh()->replies + 1) / 20));

        return redirect()->route('forum.thread', ['thread' => $thread, 'page' => $lastPage]);
    }

    public function moderate(Request $request, ForumThread $thread, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', 'in:pin,unpin,lock,unlock,hide,show,hide_post,show_post'], 'post' => ['nullable', 'integer']]);
        $action = $data['action'];

        if (in_array($action, ['hide_post', 'show_post'], true)) {
            $post = ForumPost::query()->where('forum_thread_id', $thread->id)->whereKey((int) ($data['post'] ?? 0))->firstOrFail();
            $post->forceFill(['hidden' => $action === 'hide_post', 'hidden_by' => $action === 'hide_post' ? $request->user()->id : null])->save();
        } else {
            $field = ['pin' => ['pinned', true], 'unpin' => ['pinned', false], 'lock' => ['locked', true], 'unlock' => ['locked', false], 'hide' => ['hidden', true], 'show' => ['hidden', false]][$action];
            $thread->forceFill([$field[0] => $field[1]])->save();
        }
        $audit->log('forum.'.$action, $thread, ['post' => $data['post'] ?? null], $request->user()->id);

        return back()->with('status', __('Saved.'));
    }

    private function canStart(Request $request, ForumCategory $category): bool
    {
        $user = $request->user();

        return $user !== null && (! $category->staff_only_topics || $user->can('forum.moderate'));
    }
}
