<?php

namespace Tests\Feature\NationalCouncil;

use App\Http\Controllers\Api\V2\TalentController;
use App\V2\AppUser;
use App\V2\TalentFormItem;
use App\V2\TalentRole;
use App\V2\TalentSubmission;
use App\V2\TalentTrack;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// FPM Talent: the forms of the two tracks are edited in the CMS (add, remove, reorder, required / hidden) and the app draws
// them from the server. An app that sends form_version follows the CMS form; an older app keeps its fixed rules.
class TalentFormEditorTest extends CouncilTestCase
{
    private string $body = '';
    private int $status = 200;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Cache::flush();
    }

    private function admin()
    {
        return $this->actingAs($this->adminUser(), 'admin');
    }

    private function talent(string $method, array $payload = [], ?int $userId = null, array $files = []): array
    {
        $req = Request::create('/api/v2/talent-' . $method, 'POST', $payload, [], $files, ['REMOTE_ADDR' => '10.9.9.9']);
        if ($userId) {
            $req->merge(['user' => AppUser::withTrashed()->find($userId)]);
        }
        app()->instance('request', $req);
        $name = ['config' => 'config', 'submit' => 'submit'][$method];
        $res = app(TalentController::class)->$name($req);
        $this->status = $res->getStatusCode();
        $this->body = $res->getContent();

        return json_decode($this->body, true) ?? [];
    }

    private function open(string $code): void
    {
        TalentTrack::where('code', $code)->update(['is_enabled' => true, 'opens_at' => now()->subHour(), 'closes_at' => now()->addDay(), 'audience' => 'members']);
    }

    private function roleIds(): array
    {
        return TalentRole::where('is_active', true)->orderBy('sort_order')->limit(1)->pluck('id')->map(fn ($i) => ['id' => $i])->all();
    }

    private function selfPayload(array $over = []): array
    {
        return $over + [
            'track' => 'self', 'form_version' => 1, 'education_level' => 'ماجيستير', 'academic' => 'ماجستير', 'professional' => 'خبرة', 'interests' => 'سياسات',
            'cover_letter' => 'رسالة', 'roles' => $this->roleIds(), 'acknowledged' => '1',
        ];
    }

    private function cv(): array
    {
        return ['cv' => UploadedFile::fake()->create('cv.pdf', 120, 'application/pdf')];
    }

    /** the rows of a form as the editor posts them, in the order they are on screen */
    private function rowsOf(string $track, callable $change = null): array
    {
        $rows = [];
        foreach (TalentFormItem::ordered($track) as $i) {
            $row = ['id' => $i->id, 'kind' => $i->kind, 'label' => $i->label, 'hint' => $i->hint, 'input' => $i->input, 'options' => $i->options, 'delete' => ''];
            if ($i->required) {
                $row['required'] = '1';
            }
            if ($i->is_active) {
                $row['active'] = '1';
            }
            $rows[] = $row;
        }

        return $change ? $change($rows) : $rows;
    }

    private function save(string $track, array $rows)
    {
        return $this->admin()->post(route('admin.talent-form.update', $track, false), ['items' => $rows]);
    }

    private function keyIndex(array $rows, string $key): int
    {
        foreach ($rows as $n => $r) {
            if (($r['id'] ?? null) && TalentFormItem::find($r['id'])?->key === $key) {
                return $n;
            }
        }
        $this->fail("no row for $key");
    }

    // ------------------------------------------------------------------ the data

    public function test_both_forms_start_exactly_as_the_app_had_them(): void
    {
        $peer = TalentFormItem::ordered('peer');
        $self = TalentFormItem::ordered('self');
        $this->assertSame(11, $peer->count());
        $this->assertSame(15, $self->count());

        $this->assertSame(['section', 'field', 'location', 'field', 'field', 'section', 'roles', 'section', 'field', 'field', 'field'], $peer->pluck('kind')->all());
        $this->assertSame(['nominee_name', 'profession', 'relation_to_fpm', 'reasons', 'strengths', 'relationship_between'], $peer->where('kind', 'field')->pluck('key')->all());
        $this->assertSame(['guest', 'field', 'section', 'field', 'field', 'field', 'field', 'field', 'section', 'roles', 'section', 'field', 'section', 'cv', 'docs'], $self->pluck('kind')->all());
        $this->assertSame('رسالة تعريفية', $self->firstWhere('key', 'cover_letter')->label);
        $this->assertTrue($self->firstWhere('key', 'cover_letter')->required);
        $this->assertFalse($self->firstWhere('key', 'organizational')->required);
        $this->assertTrue($self->firstWhere('kind', 'guest')->is_locked);
    }

    public function test_the_config_carries_the_form_for_each_audience(): void
    {
        $this->open('peer');
        $this->open('self');
        $u = $this->makeAppUser('ZQ member', '5800000011');

        $tracks = collect($this->talent('config', [], $u)['tracks'])->keyBy('code');
        $this->assertSame(1, $tracks['self']['form_version']);
        $selfTypes = array_column($tracks['self']['form'], 'type');
        $this->assertNotContains('guest', $selfTypes, 'a member has no guest block');
        $this->assertContains('cv', $selfTypes);
        $keys = array_column(array_filter($tracks['self']['form'], fn ($x) => $x['type'] === 'field'), 'key');
        $this->assertContains('personal_info', $keys, 'the member-only question');
        $cover = collect($tracks['self']['form'])->firstWhere('key', 'cover_letter');
        $this->assertSame('رسالة تعريفية', $cover['label']);
        $this->assertSame('textarea', $cover['input']);
        $this->assertTrue($cover['required']);
        $this->assertSame('fpm_relation', collect($tracks['peer']['form'])->firstWhere('key', 'relation_to_fpm')['choices']);
        $this->assertSame('location', collect($tracks['peer']['form'])->firstWhere('type', 'location')['type']);

        // a guest gets the guest block, not the member-only question
        TalentTrack::where('code', 'self')->update(['audience' => 'public']);
        $guestTracks = collect($this->talent('config', [])['tracks'])->keyBy('code');
        $guestForm = $guestTracks['self']['form'];
        $this->assertContains('guest', array_column($guestForm, 'type'));
        $this->assertNotContains('personal_info', array_column($guestForm, 'key'));
    }

    // ------------------------------------------------------------------ the CMS editor

    public function test_the_editor_opens_and_is_linked_from_the_tracks_page(): void
    {
        $this->admin()->get(route('admin.talent-tracks.index', [], false))->assertOk()->assertSee('Edit the form');
        foreach (['peer', 'self'] as $code) {
            $this->admin()->get(route('admin.talent-form.edit', $code, false))->assertOk()->assertSee('Add a question')->assertSee('Add a section title');
        }
        $this->admin()->get(route('admin.talent-form.edit', 'self', false))->assertOk()->assertSee('رسالة تعريفية');
        $this->admin()->get(route('admin.talent-form.edit', 'peer', false))->assertOk()->assertSee('أسباب الاقتراح');
        $this->admin()->get(route('admin.talent-form.edit', 'nope', false))->assertNotFound();
    }

    public function test_the_admin_can_edit_hide_add_reorder_and_remove(): void
    {
        $rows = $this->rowsOf('self');
        $cover = $this->keyIndex($rows, 'cover_letter');
        $academic = $this->keyIndex($rows, 'academic');

        // rename + make optional + hide
        $rows[$cover]['label'] = 'نبذة عنك';
        unset($rows[$cover]['required']);
        unset($rows[$academic]['active']);
        // add a question and a section at the end
        $rows[] = ['id' => '', 'kind' => 'section', 'label' => 'معلومات إضافية', 'active' => '1'];
        $rows[] = ['id' => '', 'kind' => 'field', 'label' => 'ما هو شغفك؟', 'input' => 'textarea', 'required' => '1', 'active' => '1'];
        $rows[] = ['id' => '', 'kind' => 'field', 'label' => 'لغة', 'input' => 'dropdown', 'options' => "عربي\nانكليزي", 'active' => '1'];
        // move the roles block to the top
        $rolesAt = collect($rows)->search(fn ($r) => $r['kind'] === 'roles');
        $roles = $rows[$rolesAt];
        unset($rows[$rolesAt]);
        array_unshift($rows, $roles);

        $this->save('self', array_values($rows))->assertRedirect()->assertSessionHasNoErrors();

        $items = TalentFormItem::ordered('self');
        $this->assertSame('roles', $items->first()->kind, 'moved to the top');
        $c = $items->firstWhere('key', 'cover_letter');
        $this->assertSame('نبذة عنك', $c->label);
        $this->assertFalse($c->required);
        $this->assertFalse($items->firstWhere('key', 'academic')->is_active, 'hidden, not deleted');
        $this->assertSame(['section', 'field', 'field'], $items->slice(-3)->pluck('kind')->all());
        $custom = $items->where('is_builtin', false)->where('kind', 'field')->values();
        $this->assertCount(2, $custom);
        $this->assertStringStartsWith('x_', $custom[0]->key);
        $this->assertSame('textarea', $custom[0]->input);
        $this->assertTrue($custom[0]->required);
        $this->assertSame(['عربي', 'انكليزي'], $custom[1]->optionList());

        // remove: a built-in row is hidden, a row added in the CMS is deleted for good
        $rows = $this->rowsOf('self');
        $rows[$this->keyIndex($rows, 'interests')]['delete'] = '1';
        $customAt = collect($rows)->search(fn ($r) => $r['label'] === 'ما هو شغفك؟');
        $rows[$customAt]['delete'] = '1';
        $this->save('self', $rows)->assertRedirect();
        $this->assertTrue(TalentFormItem::where('track', 'self')->where('key', 'interests')->exists());
        $this->assertFalse(TalentFormItem::where('track', 'self')->where('key', 'interests')->value('is_active'));
        $this->assertFalse(TalentFormItem::where('track', 'self')->where('label', 'ما هو شغفك؟')->exists());

        // the guest block can be moved and renamed, never hidden
        $rows = $this->rowsOf('self');
        $guestAt = collect($rows)->search(fn ($r) => $r['kind'] === 'guest');
        unset($rows[$guestAt]['active']);
        $rows[$guestAt]['delete'] = '1';
        $this->save('self', $rows)->assertRedirect();
        $this->assertTrue((bool) TalentFormItem::where('track', 'self')->where('kind', 'guest')->value('is_active'));
    }

    public function test_a_wrong_row_saves_nothing(): void
    {
        $before = TalentFormItem::where('track', 'self')->count();

        $rows = $this->rowsOf('self');
        $rows[] = ['id' => '', 'kind' => 'field', 'label' => 'سؤال جيد', 'input' => 'text', 'active' => '1'];
        $rows[] = ['id' => '', 'kind' => 'field', 'label' => '   ', 'input' => 'text', 'active' => '1'];
        $this->save('self', $rows)->assertSessionHasErrors();
        $this->assertSame($before, TalentFormItem::where('track', 'self')->count(), 'the good row was not saved either');

        $rows = $this->rowsOf('self');
        $rows[] = ['id' => '', 'kind' => 'field', 'label' => 'اختر', 'input' => 'dropdown', 'options' => 'واحد', 'active' => '1'];
        $this->save('self', $rows)->assertSessionHasErrors();

        // a row of another form cannot be touched
        $rows = $this->rowsOf('self');
        $rows[] = ['id' => TalentFormItem::where('track', 'peer')->value('id'), 'kind' => 'field', 'label' => 'x', 'active' => '1'];
        $this->save('self', $rows)->assertSessionHasErrors();
    }

    // ------------------------------------------------------------------ the app sends a form

    public function test_an_app_that_follows_the_form_is_validated_against_the_cms_form(): void
    {
        $this->open('self');
        $u = $this->makeAppUser('ZQ member', '5800000012');

        // the admin hides the cover letter and the CV is not required, and adds a required long question + an optional list
        $rows = $this->rowsOf('self');
        unset($rows[$this->keyIndex($rows, 'cover_letter')]['active']);
        $cvAt = collect($rows)->search(fn ($r) => $r['kind'] === 'cv');
        unset($rows[$cvAt]['required']);
        $rows[] = ['id' => '', 'kind' => 'field', 'label' => 'ما هو شغفك؟', 'input' => 'textarea', 'required' => '1', 'active' => '1'];
        $rows[] = ['id' => '', 'kind' => 'field', 'label' => 'اللغة', 'input' => 'dropdown', 'options' => "عربي\nانكليزي", 'active' => '1'];
        $this->save('self', array_values($rows))->assertRedirect();
        $passion = TalentFormItem::where('track', 'self')->where('label', 'ما هو شغفك؟')->value('key');
        $lang = TalentFormItem::where('track', 'self')->where('label', 'اللغة')->value('key');

        // the required new question is missing: refused, and the message names it
        $payload = $this->selfPayload();
        unset($payload['cover_letter']);
        $this->talent('submit', $payload, $u);
        $this->assertSame(400, $this->status);
        $this->assertStringContainsString('ما هو شغفك؟', json_decode($this->body, true)['error']['message'], 'the message names the question');

        // a value that is not in the list is refused
        $this->talent('submit', $payload + [$passion => 'الكتابة', $lang => 'فرنسي'], $u);
        $this->assertSame(400, $this->status);

        // fine: no cover letter, no CV, the new answers are kept
        $this->talent('submit', $payload + [$passion => 'الكتابة', $lang => 'عربي'], $u);
        $this->assertTrue((bool) (json_decode($this->body, true)['success'] ?? false), $this->body);
        $row = TalentSubmission::where('track', 'self')->where('user_id', $u)->first();
        $this->assertNull($row->cover_letter);
        $this->assertSame(0, $row->attachments()->count());
        $this->assertSame([['key' => $passion, 'label' => 'ما هو شغفك؟', 'value' => 'الكتابة'], ['key' => $lang, 'label' => 'اللغة', 'value' => 'عربي']], $row->extras());

        // the CMS shows the answers and the export carries them
        $this->admin()->get(route('admin.talent-submissions.show', $row->id, false))->assertOk()->assertSee('ما هو شغفك؟')->assertSee('الكتابة');
        $csv = $this->admin()->get(route('admin.talent-submissions.export', [], false))->assertOk()->streamedContent();
        $this->assertStringContainsString('ما هو شغفك؟: الكتابة', $csv);
        $this->assertStringContainsString('Extra questions', $csv);
    }

    public function test_the_roles_block_can_be_left_off_the_form(): void
    {
        $this->open('peer');
        $u = $this->makeAppUser('ZQ member', '5800000013');
        $rows = $this->rowsOf('peer');
        $rolesAt = collect($rows)->search(fn ($r) => $r['kind'] === 'roles');
        unset($rows[$rolesAt]['active']);
        $this->save('peer', $rows)->assertRedirect();

        $payload = ['track' => 'peer', 'form_version' => 1, 'nominee_name' => 'ZQ شخص', 'nominee_district' => 'المتن', 'nominee_town' => 'ضبية', 'profession' => 'مهندس',
            'relation_to_fpm' => 'مؤيد/ة للتيار', 'reasons' => 'سبب', 'strengths' => 'قوة', 'relationship_between' => 'علاقة صداقة', 'acknowledged' => '1'];
        $this->talent('submit', $payload, $u);
        $this->assertTrue((bool) (json_decode($this->body, true)['success'] ?? false), $this->body);
        $this->assertSame('[]', TalentSubmission::where('track', 'peer')->where('user_id', $u)->value('roles_json'));
    }

    // ------------------------------------------------------------------ an older app is never broken

    public function test_an_older_app_keeps_the_fixed_rules_whatever_the_admin_does(): void
    {
        $this->open('self');
        $u = $this->makeAppUser('ZQ member', '5800000014');

        // the admin hides the cover letter and adds a required question
        $rows = $this->rowsOf('self');
        unset($rows[$this->keyIndex($rows, 'cover_letter')]['active']);
        $rows[] = ['id' => '', 'kind' => 'field', 'label' => 'سؤال جديد', 'input' => 'text', 'required' => '1', 'active' => '1'];
        $this->save('self', array_values($rows))->assertRedirect();

        // an app without form_version still has to send the cover letter and the CV, and does not know the new question
        $old = $this->selfPayload();
        unset($old['form_version']);
        $without = $old;
        unset($without['cover_letter']);
        $this->talent('submit', $without, $u, $this->cv());
        $this->assertSame(400, $this->status, 'the fixed rules still ask for the cover letter');

        $this->talent('submit', $old, $u, $this->cv());
        $this->assertTrue((bool) (json_decode($this->body, true)['success'] ?? false), 'the new required question does not block an older app: ' . $this->body);
        $this->assertNull(TalentSubmission::where('user_id', $u)->first()->extra_json);
    }

    public function test_a_double_tap_still_makes_one_row_even_when_the_form_has_changed(): void
    {
        $this->open('peer');   // (the self track allows one form per member, so a second tap would be refused for that reason)
        $u = $this->makeAppUser('ZQ member', '5800000015');
        $payload = ['track' => 'peer', 'form_version' => 1, 'nominee_name' => 'ZQ شخص', 'nominee_district' => 'المتن', 'nominee_town' => 'ضبية', 'profession' => 'مهندس',
            'relation_to_fpm' => 'مؤيد/ة للتيار', 'reasons' => 'سبب', 'strengths' => 'قوة', 'relationship_between' => 'علاقة صداقة', 'roles' => $this->roleIds(), 'acknowledged' => '1'];

        $this->talent('submit', $payload, $u);
        $this->assertTrue((bool) (json_decode($this->body, true)['success'] ?? false), $this->body);
        $this->talent('submit', $payload, $u);
        $this->assertTrue((bool) (json_decode($this->body, true)['duplicate'] ?? false), 'the same form again within a minute is one row');
        $this->assertSame(1, TalentSubmission::where('user_id', $u)->count());

        // a different answer is a different form
        $this->talent('submit', ['reasons' => 'سبب آخر'] + $payload, $u);
        $this->assertSame(2, TalentSubmission::where('user_id', $u)->count());
    }
}
