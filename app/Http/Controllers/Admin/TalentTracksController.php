<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\V2\TalentTrack;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

// FPM Talent > Tracks: the name, texts, open window and audience of the two tracks the app shows (peer nomination and
// self-interest). Nothing opens until it is switched on here (and inside its dates, when dates are set).
class TalentTracksController extends Controller
{
    public function index()
    {
        return view('cms.talent.tracks')->with([
            'layout' => 'layouts.cms',
            'tracks' => TalentTrack::orderBy('id')->get(),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'tracks.*.title' => 'required|string|max:191',
            'tracks.*.subtitle' => 'nullable|string|max:255',
            'tracks.*.intro' => 'nullable|string|max:5000',
            'tracks.*.acknowledgement' => 'nullable|string|max:2000',
            'tracks.*.closed_message' => 'nullable|string|max:1000',
            'tracks.*.opens_at' => 'nullable|date',
            'tracks.*.closes_at' => 'nullable|date',
            'tracks.*.audience' => 'nullable|in:members,public',
            'tracks.*.max_per_member' => 'required|integer|min:1|max:1000',
        ]);

        foreach (TalentTrack::all() as $track) {
            $in = $request->input('tracks.' . $track->code);
            if (!is_array($in)) {
                continue;
            }
            $opens = !empty($in['opens_at']) ? Carbon::parse($in['opens_at']) : null;
            $closes = !empty($in['closes_at']) ? Carbon::parse($in['closes_at']) : null;
            if ($opens && $closes && $closes->lte($opens)) {
                return back()->withInput()->withErrors(['"' . $track->title . '": the closing date must be after the opening date']);
            }

            $track->update([
                'title' => trim($in['title']),
                'subtitle' => ($in['subtitle'] ?? '') !== '' ? trim($in['subtitle']) : null,
                'intro' => ($in['intro'] ?? '') !== '' ? $in['intro'] : null,
                'acknowledgement' => ($in['acknowledgement'] ?? '') !== '' ? $in['acknowledgement'] : null,
                'closed_message' => ($in['closed_message'] ?? '') !== '' ? $in['closed_message'] : null,
                'is_enabled' => !empty($in['is_enabled']),
                'opens_at' => $opens,
                'closes_at' => $closes,
                // the peer track is always members only
                'audience' => $track->code === TalentTrack::PEER ? 'members' : (($in['audience'] ?? 'members') === 'public' ? 'public' : 'members'),
                'max_per_member' => (int) $in['max_per_member'],
            ]);
        }

        Log::info('talent_tracks_updated', ['admin_id' => optional($request->user())->id]);

        return redirect()->route('admin.talent-tracks.index')->with('message', 'Saved. The app follows these settings the next time it opens the FPM Talent screen.');
    }

    // "Start a new cycle": members get a fresh allowance (the 10 names) and old submissions stay in the list under their
    // old cycle number. Nothing is deleted.
    public function newCycle(Request $request, $code)
    {
        $track = TalentTrack::where('code', $code)->firstOrFail();
        $track->increment('cycle');
        Log::info('talent_new_cycle', ['track' => $track->code, 'cycle' => $track->cycle, 'admin_id' => optional($request->user())->id]);

        return redirect()->route('admin.talent-tracks.index')->with('message', '"' . $track->title . '": cycle ' . $track->cycle . ' started. Members have a fresh allowance; earlier submissions are kept under cycle ' . ($track->cycle - 1) . '.');
    }
}
