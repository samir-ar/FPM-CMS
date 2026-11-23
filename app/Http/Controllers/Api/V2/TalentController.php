<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Repositories\V2\FpmApisRepository;
use App\Http\Traits\ResponseTrait;
use App\V2\TalentAttachment;
use App\V2\TalentChoice;
use App\V2\TalentFaq;
use App\V2\TalentRole;
use App\V2\TalentSubmission;
use App\V2\TalentTrack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

// FPM Talent (منصة الكفاءات): what the app shows (tracks with their open / upcoming / closed state, the roles list, the FAQ)
// and what it sends (a peer nomination or a self-interest form, from a member or - self track only, when the CMS made it
// public - from a guest who confirmed his phone with a code). Files (CV, documents) go to the PRIVATE local disk.
class TalentController extends Controller
{
    use ResponseTrait;

    const GUEST_CODE_TTL = 600;        // the code lives 10 minutes
    const GUEST_TOKEN_TTL = 1800;      // after the code, the guest has 30 minutes to submit
    const GUEST_CODES_PER_DAY = 3;     // per phone
    const GUEST_CODES_PER_DEVICE_DAY = 10;
    const GUEST_WRONG_TRIES = 5;
    const MAX_DOCS = 3;
    const TEXT_MAX = 4000;

    private function member(Request $request)
    {
        return $request->user ?? null;
    }

    // ---------------------------------------------------------------- what the app shows

    public function config(Request $request)
    {
        $member = $this->member($request);
        $isMember = (bool) $member;

        $tracks = TalentTrack::orderBy('id')->get()
            ->filter(fn (TalentTrack $t) => $t->visibleTo($isMember))
            ->map(function (TalentTrack $t) use ($member) {
                $used = $member ? $this->usedCount($t, $member) : 0;

                return [
                    'code' => $t->code,
                    'title' => $t->title,
                    'subtitle' => $t->subtitle,
                    'intro' => $t->intro,
                    'acknowledgement' => $t->acknowledgement,
                    'state' => $t->state(),
                    'message' => $t->state() === 'open' ? null : $this->stateMessage($t),
                    'opens_at' => $t->opens_at ? $t->opens_at->timestamp : null,
                    'closes_at' => $t->closes_at ? $t->closes_at->timestamp : null,
                    'audience' => $t->audienceFor(),
                    'max_per_member' => (int) $t->max_per_member,
                    'my_count' => $used,
                ];
            })->values();

        $roles = TalentRole::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'allows_text' => (bool) $r->allows_text])->values();

        // the drop-down lists the CMS manages (Talent Choices)
        $choices = [];
        foreach (array_keys(TalentChoice::GROUPS) as $group) {
            $choices[$group] = TalentChoice::names($group);
        }

        return response()->json(['is_member' => $isMember, 'tracks' => $tracks, 'roles' => $roles, 'choices' => $choices]);
    }

    public function faqs(Request $request)
    {
        // the language the screen's own button asked for, NOT the phone's Accept-Language (SetLanguage writes that over request('language'))
        $lang = $request->attributes->get('requested_language', $request->input('language')) === 'en' ? 'en' : 'ar';

        return response()->json(
            TalentFaq::where('language', $lang)->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn ($f) => ['id' => $f->id, 'question' => $f->question, 'answer' => $f->answer])->values()
        );
    }

    private function stateMessage(TalentTrack $t): string
    {
        if ($t->state() === 'upcoming' && $t->opens_at) {
            return 'يفتح باب المشاركة بتاريخ ' . $t->opens_at->format('Y-m-d H:i');
        }

        return $t->closed_message ?: 'باب المشاركة مقفل حالياً.';
    }

    // How many times this member already submitted on this track in the current cycle (member level: any of his accounts).
    private function usedCount(TalentTrack $t, $member): int
    {
        return TalentSubmission::where('track', $t->code)->where('cycle', $t->cycle)->where('is_guest', false)
            ->where(function ($q) use ($member) {
                $q->where('user_id', $member->id);
                if (!empty($member->member_id)) {
                    $q->orWhere('member_id', ltrim((string) $member->member_id, '0'));
                }
            })->count();
    }

    // ---------------------------------------------------------------- guest: phone + code

    public function guestSendCode(Request $request)
    {
        $v = Validator::make($request->all(), ['phone' => ['required', 'regex:/^\+?[0-9]{7,15}$/']]);
        if ($v->fails()) {
            return $this->api_error_response('invalid_phone', 101, 'رقم الهاتف غير صحيح');
        }
        if ($this->member($request)) {
            return $this->api_error_response('not_for_members', 109, 'هذه الخطوة للزوار فقط');
        }

        $track = TalentTrack::where('code', TalentTrack::SELF)->first();
        if (!$track || $track->audienceFor() !== 'public' || $track->state() !== 'open') {
            return $this->api_error_response('track_closed', 102, 'باب المشاركة مقفل حالياً');
        }

        $phone = preg_replace('/\D+/', '', $request->input('phone'));
        $device = trim((string) $request->input('hardware_id', $request->input('device_id', '')));
        $dayKey = 'talent-code-phone:' . $phone;
        $deviceKey = 'talent-code-device:' . ($device !== '' ? $device : $request->ip());

        if (RateLimiter::tooManyAttempts($dayKey, self::GUEST_CODES_PER_DAY) || RateLimiter::tooManyAttempts($deviceKey, self::GUEST_CODES_PER_DEVICE_DAY)) {
            return $this->api_error_response('too_many_codes', 103, 'وصلت إلى الحد الأقصى لطلب الرمز اليوم، حاول غداً', 429);
        }
        RateLimiter::hit($dayKey, 86400);
        RateLimiter::hit($deviceKey, 86400);

        $code = (string) random_int(100000, 999999);
        Cache::put('talent-guest-code:' . $phone, ['hash' => hash('sha256', $code), 'tries' => 0], self::GUEST_CODE_TTL);

        try {
            (new FpmApisRepository())->sendSMS(['phone_number' => $phone, 'message' => 'رمز التحقق لمنصة الكفاءات: ' . $code]);
        } catch (\Throwable $e) {
            // the provider often closes the connection after sending: the SMS may have been sent, so say so
            Log::info('talent_guest_sms_not_confirmed', ['error' => preg_replace('/(password|username)=[^&\s"]+/i', '$1=***', $e->getMessage())]);
        }

        return response()->json(['success' => true, 'expires_in' => self::GUEST_CODE_TTL]);
    }

    public function guestVerify(Request $request)
    {
        $v = Validator::make($request->all(), ['phone' => ['required', 'regex:/^\+?[0-9]{7,15}$/'], 'code' => 'required|digits:6']);
        if ($v->fails()) {
            return $this->api_error_response('invalid_code', 104, 'الرمز غير صحيح');
        }

        $phone = preg_replace('/\D+/', '', $request->input('phone'));
        $key = 'talent-guest-code:' . $phone;
        $entry = Cache::get($key);
        if (!$entry) {
            return $this->api_error_response('code_expired', 105, 'انتهت صلاحية الرمز، اطلب رمزاً جديداً');
        }
        if (!hash_equals($entry['hash'], hash('sha256', (string) $request->input('code')))) {
            $entry['tries']++;
            if ($entry['tries'] >= self::GUEST_WRONG_TRIES) {
                Cache::forget($key);
            } else {
                Cache::put($key, $entry, self::GUEST_CODE_TTL);
            }

            return $this->api_error_response('invalid_code', 104, 'الرمز غير صحيح');
        }

        Cache::forget($key);
        $token = bin2hex(random_bytes(20));
        Cache::put('talent-guest-token:' . $token, $phone, self::GUEST_TOKEN_TTL);

        return response()->json(['success' => true, 'token' => $token, 'expires_in' => self::GUEST_TOKEN_TTL]);
    }

    // ---------------------------------------------------------------- submit

    public function submit(Request $request)
    {
        $member = $this->member($request);
        $track = TalentTrack::where('code', (string) $request->input('track'))->first();
        if (!$track) {
            return $this->api_error_response('unknown_track', 101, 'المسار غير معروف');
        }
        if (!$track->visibleTo((bool) $member)) {
            return $this->api_error_response('not_allowed', 108, 'هذا المسار غير متاح لك');
        }
        if ($track->state() !== 'open') {
            return $this->api_error_response('track_closed', 102, $this->stateMessage($track));
        }

        // who is sending
        $guestPhone = null;
        if (!$member) {
            $guestPhone = Cache::get('talent-guest-token:' . $request->input('guest_token'));
            if (!$guestPhone) {
                return $this->api_error_response('guest_not_verified', 106, 'يرجى تأكيد رقم الهاتف أولاً');
            }
        }

        $rules = $this->rulesFor($track, (bool) $member);
        $validator = Validator::make($request->all(), $rules['rules'], [], $rules['names']);
        if ($validator->fails()) {
            return $this->api_error_response('missing_parameters', 101, implode(', ', $validator->messages()->all()));
        }

        // the ticked roles (and the free text of "any other field")
        $roles = $this->resolveRoles($request->input('roles'));
        if ($roles === null) {
            return $this->api_error_response('invalid_roles', 107, 'يرجى اختيار دور واحد على الأقل');
        }

        // limit per member / phone and cycle
        $used = $member ? $this->usedCount($track, $member)
            : TalentSubmission::where('track', $track->code)->where('cycle', $track->cycle)->where('is_guest', true)->where('submitter_phone', $guestPhone)->count();
        if ($used >= (int) $track->max_per_member) {
            return $this->api_error_response('max_reached', 103, $member ? 'لقد وصلت إلى الحد الأقصى المسموح به' : 'لقد قدّمت طلباً بهذا الرقم مسبقاً');
        }

        // a second tap / a retry of the same form must not create a second row
        $fingerprint = 'talent-submit:' . md5(($member ? 'u' . $member->id : 'g' . $guestPhone) . '|' . $track->code . '|' . $request->input('nominee_name') . '|' . $request->input('reasons') . '|' . $request->input('cover_letter'));
        if (!Cache::add($fingerprint, 1, 60)) {
            return response()->json(['success' => true, 'duplicate' => true]);
        }

        $submission = DB::transaction(function () use ($request, $track, $member, $guestPhone, $roles) {
            $fpm = $member && !empty($member->member_id)
                ? DB::table('fpm_users')->where('MemberId', ltrim((string) $member->member_id, '0'))->first()
                : null;

            $isPeer = $track->code === TalentTrack::PEER;
            $text = fn ($k) => ($v = trim(strip_tags((string) $request->input($k)))) !== '' ? mb_substr($v, 0, self::TEXT_MAX) : null;

            $submission = TalentSubmission::create([
                'track' => $track->code,
                'cycle' => $track->cycle,
                'user_id' => $member ? $member->id : null,
                'member_id' => $member && !empty($member->member_id) ? ltrim((string) $member->member_id, '0') : null,
                'is_guest' => !$member,
                'submitter_name' => $member ? ($fpm->UserFullName ?? $member->name) : $text('full_name'),
                'submitter_phone' => $member ? ($member->phone_number ?? null) : $guestPhone,
                'submitter_email' => $member ? ($fpm->Email ?? null) : $text('email'),
                'submitter_district' => $member ? ($fpm->district ?? null) : $text('district'),
                'submitter_town' => $member ? ($fpm->town ?? null) : $text('town'),
                'submitter_role' => $member ? ($fpm->LastUnitPosition ?? $fpm->Role ?? null) : null,
                'nominee_name' => $isPeer ? $text('nominee_name') : null,
                'nominee_district' => $isPeer ? $text('nominee_district') : null,
                'nominee_town' => $isPeer ? $text('nominee_town') : null,
                'education_level' => $isPeer ? null : $text('education_level'),
                'profession' => $isPeer ? $text('profession') : null,
                'relation_to_fpm' => $isPeer ? $text('relation_to_fpm') : null,
                'relationship_between' => $isPeer ? $text('relationship_between') : null,
                'reasons' => $isPeer ? $text('reasons') : null,
                'strengths' => $isPeer ? $text('strengths') : null,
                'personal_info' => $isPeer ? null : $text('personal_info'),
                'academic' => $isPeer ? null : $text('academic'),
                'professional' => $isPeer ? null : $text('professional'),
                'organizational' => $isPeer ? null : $text('organizational'),
                'interests' => $isPeer ? null : $text('interests'),
                'cover_letter' => $isPeer ? null : $text('cover_letter'),
                'roles_json' => json_encode($roles, JSON_UNESCAPED_UNICODE),
                'acknowledged' => true,
                'device_id' => mb_substr((string) $request->input('device_id'), 0, 191) ?: null,
                'hardware_id' => mb_substr((string) $request->input('hardware_id'), 0, 191) ?: null,
                'ip' => $request->ip(),
            ]);

            if ($request->hasFile('cv')) {
                $this->storeFile($submission, $request->file('cv'), 'cv');
            }
            foreach ((array) $request->file('docs', []) as $doc) {
                $this->storeFile($submission, $doc, 'doc');
            }

            return $submission;
        });

        if ($guestPhone) {
            Cache::forget('talent-guest-token:' . $request->input('guest_token'));
        }

        // tell the people listed on the track (after the answer is sent; a mail problem never touches the submission)
        \App\Support\TalentNotifier::afterSubmission($submission);

        return response()->json(['success' => true, 'id' => $submission->id]);
    }

    private function rulesFor(TalentTrack $track, bool $isMember): array
    {
        $long = 'required|string|max:' . self::TEXT_MAX;
        $common = ['roles' => 'required|array|min:1|max:20', 'acknowledged' => 'required|accepted'];
        $names = [
            'nominee_name' => 'اسم الشخص', 'profession' => 'المهنة', 'relation_to_fpm' => 'علاقته بالتيار', 'reasons' => 'أسباب الاقتراح',
            'strengths' => 'عناصر القوة والمؤهلات', 'relationship_between' => 'العلاقة بين الشخصين', 'academic' => 'المؤهلات العلمية',
            'professional' => 'الخبرات المهنية', 'interests' => 'مجالات الاهتمام', 'cover_letter' => 'الرسالة التعريفية',
            'full_name' => 'الاسم', 'nominee_district' => 'قضاء الشخص المسمّى', 'nominee_town' => 'بلدة الشخص المسمّى', 'education_level' => 'المستوى التعليمي', 'district' => 'القضاء', 'town' => 'البلدة', 'cv' => 'السيرة الذاتية', 'acknowledged' => 'الإقرار', 'roles' => 'الأدوار',
        ];

        if ($track->code === TalentTrack::PEER) {
            return ['names' => $names, 'rules' => $common + [
                'nominee_name' => 'required|string|max:191',
                'nominee_district' => 'required|string|max:191',
                'nominee_town' => 'required|string|max:191',
                'profession' => 'required|string|max:191',
                'relation_to_fpm' => ['required', 'string', Rule::in(TalentChoice::names('fpm_relation'))],
                'reasons' => $long,
                'strengths' => $long,
                'relationship_between' => ['required', 'string', Rule::in(TalentChoice::names('person_relation'))],
            ]];
        }

        $self = $common + [
            'education_level' => ['required', 'string', Rule::in(TalentChoice::names('education'))],
            'academic' => $long,
            'professional' => $long,
            'organizational' => 'nullable|string|max:' . self::TEXT_MAX,
            'interests' => $long,
            'cover_letter' => $long,
            'personal_info' => 'nullable|string|max:' . self::TEXT_MAX,
            'cv' => 'required|file|mimes:pdf,doc,docx|max:5120',
            'docs' => 'nullable|array|max:' . self::MAX_DOCS,
            'docs.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];
        if (!$isMember) {
            $self += ['full_name' => 'required|string|max:191', 'email' => 'nullable|email|max:191', 'district' => 'required|string|max:191', 'town' => 'required|string|max:191'];
        }

        return ['names' => $names, 'rules' => $self];
    }

    // roles: [{id, other?}] -> [{id,name,other}] checked against the active list; null when empty/invalid
    private function resolveRoles($input): ?array
    {
        $out = [];
        foreach ((array) $input as $r) {
            $id = (int) (is_array($r) ? ($r['id'] ?? 0) : $r);
            $role = TalentRole::where('id', $id)->where('is_active', true)->first();
            if (!$role) {
                continue;
            }
            $other = $role->allows_text && is_array($r) ? trim(strip_tags((string) ($r['other'] ?? ''))) : '';
            if ($role->allows_text && $other === '') {
                return null;
            }
            $out[$role->id] = ['id' => $role->id, 'name' => $role->name, 'other' => $role->allows_text ? mb_substr($other, 0, 191) : null];
        }

        return $out ? array_values($out) : null;
    }

    private function storeFile(TalentSubmission $submission, $file, string $kind): void
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $name = $kind . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $path = $file->storeAs('talent/' . $submission->id, $name, 'local');

        TalentAttachment::create([
            'submission_id' => $submission->id,
            'kind' => $kind,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 191),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);
    }
}
