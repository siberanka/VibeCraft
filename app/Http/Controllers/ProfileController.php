<?php

namespace App\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Models\Badge;
use App\Models\ForumPost;
use App\Models\PlayerStat;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    private const STAFF_ROLES = ['owner', 'admin', 'moderator', 'support'];

    /** Public profile: username, join date, verified game name, badges, staff role and shared game stats only. */
    public function show(string $name): View
    {
        abort_unless(preg_match('/^[A-Za-z0-9_]{3,24}$/', $name) === 1, 404);
        $member = User::query()->where('name', $name)->whereNull('banned_at')->firstOrFail();
        $member->load(['roles:id,name', 'identities']);
        $identity = $member->primaryIdentity();

        return view('theme::community.profile', [
            'member' => $member,
            'identity' => $identity,
            'staffRole' => $member->roles->pluck('name')->first(fn ($role) => in_array($role, self::STAFF_ROLES, true)),
            'badges' => Badge::query()->whereHas('users', fn ($q) => $q->whereKey($member->id))->orderBy('sort')->get(),
            'posts' => ForumPost::query()->where('user_id', $member->id)->where('hidden', false)->count(),
            'stats' => $identity ? PlayerStat::query()->where('player_uuid', $identity->uuid)->get(['stat', 'value'])
                ->groupBy('stat')->map(fn ($rows) => (int) $rows->max('value'))->all() : [],
        ]);
    }

    public function grant(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['badge' => ['required', 'integer', 'exists:badges,id'], 'action' => ['required', 'in:grant,revoke']]);
        $badge = Badge::query()->findOrFail($data['badge']);
        $data['action'] === 'grant'
            ? $badge->users()->syncWithoutDetaching([$user->id => ['granted_by' => $request->user()->id, 'created_at' => now()]])
            : $badge->users()->detach($user->id);
        $audit->log('badge.'.$data['action'], $user, ['badge' => $badge->id], $request->user()->id);

        return back()->with('status', __('Saved.'));
    }
}
