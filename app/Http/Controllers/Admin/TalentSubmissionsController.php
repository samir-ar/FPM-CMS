<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\V2\TalentAttachment;
use App\V2\TalentSubmission;
use App\V2\TalentTrack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

// FPM Talent > Submissions: what members and guests sent. Filter, read, give a review status + private notes, export.
// The CV / document files are private: only the page "Talent CV Files" (its own permission) can download them.
class TalentSubmissionsController extends Controller
{
    private function filtered(Request $request, bool $withCount = true)
    {
        $q = TalentSubmission::query();
        if ($withCount) {
            $q->withCount('attachments');
        }
        if (in_array($request->query('track'), ['peer', 'self'])) {
            $q->where('track', $request->query('track'));
        }
        if (array_key_exists((string) $request->query('status'), TalentSubmission::STATUSES)) {
            $q->where('status', $request->query('status'));
        }
        if ($request->filled('cycle')) {
            $q->where('cycle', (int) $request->query('cycle'));
        }
        if (in_array($request->query('who'), ['member', 'guest'])) {
            $q->where('is_guest', $request->query('who') === 'guest');
        }
        if ($request->filled('q')) {
            $term = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($request->query('q'))) . '%';
            $q->where(function ($w) use ($term) {
                $w->where('submitter_name', 'like', $term)->orWhere('nominee_name', 'like', $term)
                    ->orWhere('submitter_phone', 'like', $term)->orWhere('member_id', 'like', $term)->orWhere('profession', 'like', $term);
            });
        }

        return $q;
    }

    public function index(Request $request)
    {
        $grouped = null;
        if ($request->query('group') === 'nominee') {
            // the same person named by several members: biggest first
            $grouped = $this->filtered($request, false)->where('track', 'peer')->whereNotNull('nominee_name')
                ->selectRaw('nominee_name, COUNT(*) AS times, COUNT(DISTINCT COALESCE(member_id, submitter_phone)) AS people, MAX(created_at) AS last_at')
                ->groupBy('nominee_name')->orderByDesc('people')->orderByDesc('times')->limit(300)->get();
        }

        return view('cms.talent.submissions')->with([
            'layout' => 'layouts.cms',
            'rows' => $grouped ? null : $this->filtered($request)->orderByDesc('id')->paginate(25)->withQueryString(),
            'grouped' => $grouped,
            'statuses' => TalentSubmission::STATUSES,
            'tracks' => TalentTrack::pluck('title', 'code'),
            'cycles' => TalentSubmission::select('cycle')->distinct()->orderBy('cycle')->pluck('cycle'),
            'counts' => TalentSubmission::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
            'canSeeFiles' => $this->canDownloadFiles($request),
        ]);
    }

    public function show(Request $request, $id)
    {
        $s = TalentSubmission::with('attachments')->findOrFail($id);

        return view('cms.talent.submission')->with([
            'layout' => 'layouts.cms',
            's' => $s,
            'statuses' => TalentSubmission::STATUSES,
            'trackTitle' => TalentTrack::where('code', $s->track)->value('title'),
            'canSeeFiles' => $this->canDownloadFiles($request),
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate(['status' => 'required|in:' . implode(',', array_keys(TalentSubmission::STATUSES)), 'admin_notes' => 'nullable|string|max:5000']);
        $s = TalentSubmission::findOrFail($id);
        $old = $s->status;
        $s->update(['status' => $data['status'], 'admin_notes' => $data['admin_notes'] ?? null]);
        Log::info('talent_submission_reviewed', ['id' => $s->id, 'from' => $old, 'to' => $s->status, 'admin_id' => optional($request->user())->id]);

        return redirect()->route('admin.talent-submissions.show', $s->id)->with('message', 'Saved.');
    }

    // CSV for Excel (UTF-8 with BOM so Arabic opens correctly); follows the same filters as the list. File names only,
    // never the files themselves.
    public function export(Request $request)
    {
        $rows = $this->filtered($request)->with('attachments')->orderBy('id')->get();
        $roles = fn ($s) => collect($s->roles())->map(fn ($r) => $r['name'] . (!empty($r['other']) ? ' (' . $r['other'] . ')' : ''))->implode(' | ');
        $head = ['ID', 'Date', 'Track', 'Cycle', 'Who', 'Name', 'Member ID', 'Phone', 'Email', 'District', 'Town', 'Organizational role',
            'Nominee', 'Nominee district', 'Nominee town', 'Education level', 'Profession', 'Relation to FPM', 'Relationship between', 'Reasons', 'Strengths', 'Personal info', 'Academic', 'Professional',
            'Organizational / public', 'Interests', 'Cover letter', 'Roles', 'Status', 'Notes', 'Files'];

        return response()->streamDownload(function () use ($rows, $head, $roles) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $head);
            foreach ($rows as $s) {
                fputcsv($out, [
                    $s->id, $s->created_at, $s->track, $s->cycle, $s->is_guest ? 'Guest' : 'Member', $s->submitter_name, $s->member_id, $s->submitter_phone,
                    $s->submitter_email, $s->submitter_district, $s->submitter_town, $s->submitter_role, $s->nominee_name, $s->nominee_district, $s->nominee_town, $s->education_level, $s->profession, $s->relation_to_fpm,
                    $s->relationship_between, $s->reasons, $s->strengths, $s->personal_info, $s->academic, $s->professional, $s->organizational,
                    $s->interests, $s->cover_letter, $roles($s), $s->status, $s->admin_notes, $s->attachments->pluck('original_name')->implode(' | '),
                ]);
            }
            fclose($out);
        }, 'fpm-talent-' . date('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // The private file. Own permission ("Talent CV Files"); every download is logged.
    public function download(Request $request, $id)
    {
        $file = TalentAttachment::findOrFail($id);
        if (!Storage::disk('local')->exists($file->path)) {
            abort(404, 'File not found on the server');
        }
        Log::info('talent_file_downloaded', ['attachment_id' => $file->id, 'submission_id' => $file->submission_id, 'admin_id' => optional($request->user())->id]);

        return Storage::disk('local')->download($file->path, $file->original_name);
    }

    private function canDownloadFiles(Request $request): bool
    {
        $pageId = DB::table('pages')->where('url', 'admin.talent-files.download')->value('id');
        $user = auth('admin')->user() ?: $request->user();

        return $pageId && $user && method_exists($user, 'permissionLevel') && $user->permissionLevel($pageId) !== null;
    }
}
