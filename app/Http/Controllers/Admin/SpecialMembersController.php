<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\CouncilMemberLookup;
use App\V2\AppUser;
use App\V2\SpecialMember;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// People who must be able to log in and be counted (e.g. a National Council
// member) but have no official TWH member id. Each one gets a member id from a
// RESERVED range that can never collide with a real one, a row in fpm_users
// (so login, attendance and voting work exactly like for any member), and a
// copy of that data kept here so the fpm_users row can be restored if the TWH
// sync ever removes it.
class SpecialMembersController extends Controller
{
    // 13 digits starting with 99. Real ids seen in fpm_users are at most
    // 12 digits (the longest are year-based like 202609210012), so this range
    // is outside anything TWH issues. Still verified against every table below.
    private const ID_BASE = 9900000000000;

    // Largest photo we put into fpm_users.PersonImage. That column is a TEXT
    // on production (64KB), so stay well under it.
    private const MAX_PHOTO_BYTES = 60000;

    public function index()
    {
        $members = SpecialMember::orderByDesc('id')->get();
        $ids = $members->pluck('member_id')->map(fn($i) => (string) $i)->all();
        $inFpm = DB::table('fpm_users')->whereIn('MemberId', $ids)->get(['MemberId', 'member_status'])->keyBy(fn($r) => (string) $r->MemberId);
        $loggedIn = AppUser::whereIn('member_id', $ids)->pluck('member_id')->unique()->flip();
        $inRoster = DB::table('council_roster_members')->whereIn('member_id', $ids)->pluck('member_id')->flip();

        return view('cms.special_members.index')->with([
            'layout' => 'layouts.cms',
            'members' => $members,
            'inFpm' => $inFpm,
            'loggedIn' => $loggedIn,
            'inRoster' => $inRoster,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'full_name' => 'required|string|max:191',
            'phone' => 'required|string|max:32',
            'sector' => 'nullable|string|max:191',
            'district' => 'nullable|string|max:191',
            'email' => 'nullable|email|max:191',
            'note' => 'nullable|string|max:2000',
            'photo' => 'nullable|image|max:8192',
        ]);

        // Login looks members up by member id + phone digits (no "+"), so the
        // phone is stored exactly in that form.
        $phone = preg_replace('/\D+/', '', $request->phone);
        if (strlen($phone) < 7) {
            return back()->withErrors(['phone' => 'The phone number is not valid.'])->withInput();
        }

        $clash = DB::table('fpm_users')->where('MobileNumber', $phone)->first(['MemberId', 'UserFullName']);
        if ($clash) {
            return back()->withErrors(['phone' => 'This number is already registered to ' . $clash->UserFullName . ' (#' . $clash->MemberId . '). Use another number, or add them as a regular member.'])->withInput();
        }

        $photo = null;
        if ($request->hasFile('photo')) {
            $photo = $this->resizePhoto($request->file('photo')->getRealPath());
            if ($photo === null) {
                return back()->withErrors(['photo' => 'The photo could not be processed.'])->withInput();
            }
        }

        $special = null;
        $photoNote = '';

        DB::transaction(function () use ($request, $phone, $photo, &$special, &$photoNote) {
            $memberId = $this->nextFreeMemberId();

            $special = SpecialMember::create([
                'member_id' => $memberId,
                'full_name' => trim($request->full_name),
                'phone' => $phone,
                'sector' => $request->sector,
                'district' => $request->district,
                'email' => $request->email,
                'note' => $request->note,
                'created_by_admin_id' => auth('admin')->id(),
            ]);

            if ($photo !== null) {
                DB::table('special_members')->where('id', $special->id)->update(['photo' => $photo]);
            }

            $photoNote = $this->writeFpmRow($special, $photo);
        });

        return redirect()->route('admin.special-members.index')->with(
            'message',
            'Added ' . $special->full_name . ' with member number ' . $special->member_id . '.' . $photoNote
        );
    }

    // Puts the fpm_users row back from our own copy (after a TWH sync removed it).
    public function restore($id)
    {
        $special = SpecialMember::findOrFail($id);

        if (DB::table('fpm_users')->where('MemberId', $special->member_id)->exists()) {
            return back()->with('message', 'The record is already in the member list.');
        }

        $photo = DB::table('special_members')->where('id', $special->id)->value('photo');
        $note = $this->writeFpmRow($special, $photo);

        return back()->with('message', 'The record was put back into the member list.' . $note);
    }

    // Enable / disable login for this member (fpm_users.member_status).
    public function toggleStatus($id)
    {
        $special = SpecialMember::findOrFail($id);
        $row = DB::table('fpm_users')->where('MemberId', $special->member_id)->first(['member_status']);

        if (!$row) {
            return back()->with('message', 'The record is not in the member list; use Restore first.');
        }

        $new = $row->member_status ? 0 : 1;
        DB::table('fpm_users')->where('MemberId', $special->member_id)->update(['member_status' => $new]);

        return back()->with('message', $new ? 'Account enabled.' : 'Account disabled.');
    }

    // The reserved id: next number above everything already used in the range,
    // then double-checked against every table a member id lives in.
    private function nextFreeMemberId(): int
    {
        $max = (int) DB::table('special_members')->max('member_id');
        $candidate = max(self::ID_BASE, $max) + 1;

        for ($i = 0; $i < 1000; $i++, $candidate++) {
            if (!$this->memberIdInUse($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Could not find a free special member id.');
    }

    private function memberIdInUse(int $id): bool
    {
        $s = (string) $id;

        if (DB::table('fpm_users')->where('MemberId', $id)->exists()) return true;
        if (DB::table('special_members')->where('member_id', $id)->exists()) return true;
        if (DB::table('app_users')->where('member_id', $s)->exists()) return true;
        if (DB::table('council_roster_members')->where('member_id', $s)->exists()) return true;
        if (DB::table('council_national_poll_permissions')->where('member_id', $s)->exists()) return true;
        if (Schema::hasTable('internal_election_permissions') && DB::table('internal_election_permissions')->where('member_id', $s)->exists()) return true;

        return false;
    }

    // Inserts the fpm_users row. Returns a note for the admin if the photo
    // could not be stored.
    private function writeFpmRow(SpecialMember $special, $photo): string
    {
        $row = [
            'MemberId' => $special->member_id,
            'UserFullName' => $special->full_name,
            'MobileNumber' => $special->phone,
            'Email' => (string) $special->email,
            'district' => $special->district,
            'LastUnitPosition' => $special->sector,
            'member_status' => 1,
            'BirthDate' => '1970-01-01',
            'CanCreateEventRequest' => 0,
            'CanCreateMeetings' => 0,
            'ElectionState' => '',
            'ElectionStateId' => 0,
            'ElectionStateVoltersNumber' => 0,
            'Role' => '',
            'RoleId' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        try {
            DB::table('fpm_users')->insert($row + ['PersonImage' => $photo ?? '']);
            return '';
        } catch (QueryException $e) {
            // Raw JPEG bytes can be refused by a text-typed PersonImage column
            // (invalid text / too long). Create the member without a photo
            // rather than failing the whole thing.
            if ($photo === null) {
                throw $e;
            }
            DB::table('fpm_users')->insert($row + ['PersonImage' => '']);
            return ' (the photo could not be saved on the member record: ' . mb_substr($e->getMessage(), 0, 120) . ')';
        }
    }

    // Square-ish JPEG, max 300px on the long side, quality lowered until it
    // fits under MAX_PHOTO_BYTES.
    private function resizePhoto(string $path): ?string
    {
        $info = @getimagesize($path);
        if (!$info) return null;

        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
        if (!$src) return null;

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, 300 / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        $out = null;
        foreach ([85, 75, 65, 55, 45, 35] as $quality) {
            ob_start();
            imagejpeg($dst, null, $quality);
            $out = ob_get_clean();
            if (strlen($out) <= self::MAX_PHOTO_BYTES) break;
        }

        imagedestroy($src);
        imagedestroy($dst);

        return strlen($out) <= self::MAX_PHOTO_BYTES ? $out : null;
    }
}
