<?php

namespace App\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Models\StaffApplication;
use App\Notifications\PlayerNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['pending', 'accepted', 'rejected'], true) ? $request->query('status') : 'pending';

        return view('theme::admin.applications', [
            'status' => $status,
            'applications' => StaffApplication::query()->with(['position', 'user'])->where('status', $status)->latest('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function show(StaffApplication $application): View
    {
        return view('theme::admin.application', ['application' => $application->load(['position', 'user.identities', 'user'])]);
    }

    public function decide(Request $request, StaffApplication $application, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:accepted,rejected'], 'note' => ['nullable', 'string', 'max:500']]);
        $updated = StaffApplication::query()->whereKey($application->id)->where('status', 'pending')->update([
            'status' => $data['decision'], 'review_note' => $data['note'] ?? null, 'reviewer_id' => $request->user()->id, 'reviewed_at' => now(), 'updated_at' => now(),
        ]);
        if ($updated === 1) {
            $audit->log('application.'.$data['decision'], $application, [], $request->user()->id);
            $application->user?->notify(new PlayerNotice('application_'.$data['decision'], ['position' => (string) $application->position?->name], '/apply'));
        }

        return redirect()->route('admin.applications.index')->with('status', $updated === 1 ? __('Saved.') : __('This application was already reviewed.'));
    }
}
