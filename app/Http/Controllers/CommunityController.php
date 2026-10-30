<?php

namespace App\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Domain\Captcha\CaptchaVerifier;
use App\Models\StaffApplication;
use App\Models\StaffPosition;
use App\Models\VoteSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CommunityController extends Controller
{
    public function vote(): View
    {
        return view('theme::community.vote', ['sites' => VoteSite::query()->where('is_visible', true)->orderBy('sort')->orderBy('id')->get()]);
    }

    public function positions(Request $request): View
    {
        return view('theme::community.positions', [
            'positions' => StaffPosition::query()->where('is_open', true)->orderBy('sort')->orderBy('id')->get(),
            'mine' => $request->user() ? StaffApplication::query()->with('position')->where('user_id', $request->user()->id)->latest('id')->limit(20)->get() : collect(),
        ]);
    }

    public function form(StaffPosition $position): View
    {
        abort_unless($position->is_open, 404);

        return view('theme::community.apply', ['position' => $position, 'questions' => $position->questionList()]);
    }

    public function apply(Request $request, StaffPosition $position, CaptchaVerifier $captcha, AuditLogger $audit): RedirectResponse
    {
        abort_unless($position->is_open, 404);
        $questions = $position->questionList();
        $data = $request->validate([
            'answers' => ['required', 'array', 'size:'.count($questions)],
            'answers.*' => ['required', 'string', 'min:2', 'max:2000'],
        ]);
        $captcha->verifyOrFail($request, 'application');
        $user = $request->user();

        $created = DB::transaction(function () use ($user, $position, $questions, $data) {
            // Serialise per account so two parallel submissions cannot both pass the check.
            $user->newQuery()->whereKey($user->id)->lockForUpdate()->first();
            if (StaffApplication::query()->where('user_id', $user->id)->where('staff_position_id', $position->id)->where('status', 'pending')->exists()) {
                return null;
            }

            return StaffApplication::query()->create([
                'staff_position_id' => $position->id,
                'user_id' => $user->id,
                'answers' => array_map(fn ($q, $a) => ['question' => $q, 'answer' => trim((string) $a)], $questions, array_values($data['answers'])),
                'status' => 'pending',
            ]);
        });

        if ($created === null) {
            return redirect()->route('community.positions')->withErrors(['answers' => __('You already have an application waiting for review for this position.')]);
        }
        $audit->log('application.submitted', $created, ['position' => $position->id], $user->id);

        return redirect()->route('community.positions')->with('success', __('Your application was sent.'));
    }
}
