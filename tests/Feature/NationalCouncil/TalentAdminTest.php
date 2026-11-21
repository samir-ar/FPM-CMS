<?php

namespace Tests\Feature\NationalCouncil;

use App\V2\TalentAttachment;
use App\V2\TalentRole;
use App\V2\TalentSubmission;
use App\V2\TalentTrack;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// FPM Talent CMS: the four admin pages, the tracks form (window, audience, cycle), roles, FAQ, the submissions list /
// review / export, and the private files with their own permission.
class TalentAdminTest extends CouncilTestCase
{
    private function admin()
    {
        return $this->actingAs($this->adminUser(), 'admin');
    }

    private function submission(array $over = []): TalentSubmission
    {
        return TalentSubmission::create($over + [
            'track' => 'peer', 'cycle' => 1, 'user_id' => null, 'member_id' => '5800000001', 'is_guest' => false, 'submitter_name' => 'ZQ مرسل',
            'nominee_name' => 'ZQ مرشح', 'profession' => 'طبيب', 'reasons' => 'سبب', 'roles_json' => json_encode([['id' => 1, 'name' => 'قيادات شبابية', 'other' => null]], JSON_UNESCAPED_UNICODE),
            'acknowledged' => true, 'status' => 'new',
        ]);
    }

    public function test_every_page_opens_for_the_super_admin(): void
    {
        $s = $this->submission();
        foreach (['admin.talent-tracks.index', 'admin.talent-roles.index', 'admin.talent-faqs.index', 'admin.talent-submissions.index'] as $name) {
            $this->admin()->get(route($name, [], false))->assertOk();
        }
        $this->admin()->get(route('admin.talent-faqs.index', ['language' => 'en'], false))->assertOk();
        $this->admin()->get(route('admin.talent-submissions.show', $s->id, false))->assertOk()->assertSee('ZQ مرشح');
    }

    public function test_the_tracks_form_saves_texts_window_and_audience_and_the_peer_track_stays_members_only(): void
    {
        $this->admin()->post(route('admin.talent-tracks.update', [], false), ['tracks' => [
            'peer' => ['title' => 'عنوان ١', 'subtitle' => 'تحت', 'intro' => 'مقدمة', 'acknowledgement' => 'أقر', 'closed_message' => 'مقفل', 'is_enabled' => '1',
                'opens_at' => '2026-12-01T09:00', 'closes_at' => '2026-12-10T18:00', 'audience' => 'public', 'max_per_member' => '10'],
            'self' => ['title' => 'عنوان ٢', 'is_enabled' => '1', 'audience' => 'public', 'max_per_member' => '1'],
        ]])->assertRedirect()->assertSessionHasNoErrors();

        $peer = TalentTrack::where('code', 'peer')->first();
        $this->assertSame('عنوان ١', $peer->title);
        $this->assertSame('members', $peer->audience, 'the peer track can never be public');
        $this->assertSame('2026-12-01 09:00', $peer->opens_at->format('Y-m-d H:i'));
        $this->assertTrue($peer->is_enabled);
        $this->assertSame('public', TalentTrack::where('code', 'self')->value('audience'));
    }

    public function test_a_closing_date_before_the_opening_date_is_refused(): void
    {
        $this->admin()->post(route('admin.talent-tracks.update', [], false), ['tracks' => [
            'peer' => ['title' => 'x', 'opens_at' => '2026-12-10T09:00', 'closes_at' => '2026-12-01T09:00', 'max_per_member' => '10'],
            'self' => ['title' => 'y', 'max_per_member' => '1'],
        ]])->assertSessionHasErrors();
    }

    public function test_a_new_cycle_keeps_old_submissions(): void
    {
        $this->submission();
        $before = TalentTrack::where('code', 'peer')->value('cycle');

        $this->admin()->post(route('admin.talent-tracks.new-cycle', 'peer', false))->assertRedirect();

        $this->assertSame($before + 1, (int) TalentTrack::where('code', 'peer')->value('cycle'));
        $this->assertSame(1, TalentSubmission::where('nominee_name', 'ZQ مرشح')->count(), 'nothing is deleted');
    }

    public function test_roles_can_be_added_reordered_and_switched_off(): void
    {
        $this->admin()->post(route('admin.talent-roles.store', [], false), ['name' => 'ZQ دور جديد'])->assertRedirect();
        $role = TalentRole::where('name', 'ZQ دور جديد')->first();
        $this->assertNotNull($role);

        $this->admin()->post(route('admin.talent-roles.update', [], false), ['roles' => [$role->id => ['name' => 'ZQ دور معدل', 'sort_order' => 50, 'allows_text' => '1']]])->assertRedirect();
        $role->refresh();
        $this->assertSame('ZQ دور معدل', $role->name);
        $this->assertFalse($role->is_active, 'an unticked box switches it off');
        $this->assertTrue($role->allows_text);
    }

    public function test_the_faq_is_added_edited_and_deleted_per_language(): void
    {
        $this->admin()->post(route('admin.talent-faqs.store', [], false), ['language' => 'en', 'question' => 'ZQ How?', 'answer' => 'Like this'])->assertRedirect();
        $faq = DB::table('talent_faqs')->where('question', 'ZQ How?')->first();
        $this->assertSame('en', $faq->language);

        $this->admin()->post(route('admin.talent-faqs.update', [], false), ['language' => 'en', 'faqs' => [$faq->id => ['question' => 'ZQ How now?', 'answer' => 'Edited', 'sort_order' => 3, 'is_active' => '1']]])->assertRedirect();
        $this->assertSame('ZQ How now?', DB::table('talent_faqs')->where('id', $faq->id)->value('question'));

        $this->admin()->post(route('admin.talent-faqs.update', [], false), ['language' => 'en', 'faqs' => [$faq->id => ['question' => 'x', 'answer' => 'y', 'delete' => '1']]])->assertRedirect();
        $this->assertNull(DB::table('talent_faqs')->where('id', $faq->id)->first());
    }

    public function test_the_submissions_list_filters_reviews_groups_and_exports(): void
    {
        $a = $this->submission(['nominee_name' => 'ZQ مشترك', 'member_id' => '5800000011']);
        $this->submission(['nominee_name' => 'ZQ مشترك', 'member_id' => '5800000012']);
        $this->submission(['track' => 'self', 'nominee_name' => null, 'is_guest' => true, 'member_id' => null, 'submitter_phone' => '96170000001', 'submitter_name' => 'ZQ ضيف']);

        $this->admin()->get(route('admin.talent-submissions.index', ['who' => 'guest'], false))->assertOk()->assertSee('ZQ ضيف')->assertDontSee('ZQ مشترك');
        $this->admin()->get(route('admin.talent-submissions.index', ['q' => 'ZQ مشترك'], false))->assertOk()->assertSee('ZQ مشترك');

        $grouped = $this->admin()->get(route('admin.talent-submissions.index', ['track' => 'peer', 'group' => 'nominee', 'q' => 'ZQ مشترك'], false))->assertOk()->getContent();
        $this->assertStringContainsString('People named by members', $grouped);

        $this->admin()->post(route('admin.talent-submissions.update', $a->id, false), ['status' => 'shortlisted', 'admin_notes' => 'ملاحظة خاصة'])->assertRedirect();
        $this->assertSame('shortlisted', $a->fresh()->status);

        $csv = $this->admin()->get(route('admin.talent-submissions.export', ['q' => 'ZQ'], false))->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('ZQ ضيف', $csv);
        $this->assertStringContainsString('shortlisted', $csv);
    }

    public function test_the_private_file_needs_its_own_permission_and_streams_from_the_private_disk(): void
    {
        Storage::fake('local');
        $s = $this->submission(['track' => 'self']);
        Storage::disk('local')->put('talent/' . $s->id . '/cv_x.pdf', 'PDFDATA');
        $f = TalentAttachment::create(['submission_id' => $s->id, 'kind' => 'cv', 'original_name' => 'cv.pdf', 'path' => 'talent/' . $s->id . '/cv_x.pdf', 'mime' => 'application/pdf', 'size' => 7]);

        // Super Admin: allowed
        $this->admin()->get(route('admin.talent-files.download', $f->id, false))->assertOk();

        // an admin who may read submissions but has NO CV Files line: the page opens, the file does not
        $profileId = DB::table('profiles')->insertGetId(['name' => 'T hr ' . uniqid(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('profile_permissions')->insert(['profile_id' => $profileId, 'page_id' => DB::table('pages')->where('url', 'admin.talent-submissions.index')->value('id'), 'level' => 'view', 'created_at' => now(), 'updated_at' => now()]);
        $limited = $this->adminUser();
        $limited->profile_id = $profileId;

        $this->actingAs($limited, 'admin')->get(route('admin.talent-submissions.show', $s->id, false))->assertOk()->assertSee('no permission to download');
        $this->actingAs($limited, 'admin')->get(route('admin.talent-files.download', $f->id, false))->assertStatus(403);
    }

    public function test_the_new_pages_are_on_the_profile_form_and_the_files_page_is_hidden(): void
    {
        foreach (['admin.talent-tracks.index', 'admin.talent-roles.index', 'admin.talent-faqs.index', 'admin.talent-submissions.index', 'admin.talent-files.download'] as $url) {
            $page = DB::table('pages')->where('url', $url)->first();
            $this->assertNotNull($page, $url);
            $this->assertEquals(1, $page->show_in_profile_form, $url);
            $this->assertEquals($url === 'admin.talent-files.download' ? 1 : 0, $page->hide, $url);
        }
        $profileId = DB::table('profiles')->where('name', 'Super Admin')->value('id');
        $html = $this->admin()->get(route('admin.profiles.edit', $profileId, false))->assertOk()->getContent();
        foreach (['Talent Tracks', 'Talent Roles', 'Talent FAQ', 'Talent Submissions', 'Talent CV Files'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
    }

    public function test_the_choices_page_adds_renames_reorders_and_switches_off_values(): void
    {
        $this->admin()->get(route('admin.talent-choices.index', [], false))->assertOk()->assertSee('Education level');

        $this->admin()->post(route('admin.talent-choices.store', [], false), ['group' => 'person_relation', 'name' => 'ZQ علاقة جديدة'])->assertRedirect();
        $row = DB::table('talent_choices')->where('name', 'ZQ علاقة جديدة')->first();
        $this->assertSame('person_relation', $row->group);

        $this->admin()->post(route('admin.talent-choices.update', [], false), ['choices' => [$row->id => ['name' => 'ZQ علاقة معدلة', 'sort_order' => 50]]])->assertRedirect();
        $row = DB::table('talent_choices')->where('id', $row->id)->first();
        $this->assertSame('ZQ علاقة معدلة', $row->name);
        $this->assertEquals(0, $row->is_active, 'an unticked box switches it off');

        $this->admin()->post(route('admin.talent-choices.store', [], false), ['group' => 'nonsense', 'name' => 'x'])->assertSessionHasErrors();
    }

    public function test_the_choices_page_is_on_the_profile_form(): void
    {
        $page = DB::table('pages')->where('url', 'admin.talent-choices.index')->first();
        $this->assertNotNull($page);
        $this->assertEquals(1, $page->show_in_profile_form);
        $profileId = DB::table('profiles')->where('name', 'Super Admin')->value('id');
        $this->assertStringContainsString('Talent Choices', $this->admin()->get(route('admin.profiles.edit', $profileId, false))->assertOk()->getContent());
    }

    public function test_a_pdf_or_image_can_be_previewed_inside_the_page_and_a_word_file_cannot(): void
    {
        Storage::fake('local');
        $s = $this->submission(['track' => 'self']);
        Storage::disk('local')->put('talent/' . $s->id . '/cv_a.pdf', '%PDF-1.4 fake');
        Storage::disk('local')->put('talent/' . $s->id . '/doc_b.png', 'PNGDATA');
        Storage::disk('local')->put('talent/' . $s->id . '/cv_c.docx', 'WORDDATA');
        $pdf = TalentAttachment::create(['submission_id' => $s->id, 'kind' => 'cv', 'original_name' => 'cv.pdf', 'path' => 'talent/' . $s->id . '/cv_a.pdf', 'mime' => 'text/html', 'size' => 13]);
        $png = TalentAttachment::create(['submission_id' => $s->id, 'kind' => 'doc', 'original_name' => 'id.png', 'path' => 'talent/' . $s->id . '/doc_b.png', 'mime' => 'application/pdf', 'size' => 7]);
        $word = TalentAttachment::create(['submission_id' => $s->id, 'kind' => 'cv', 'original_name' => 'cv.docx', 'path' => 'talent/' . $s->id . '/cv_c.docx', 'mime' => 'application/msword', 'size' => 8]);

        // shown inline, with the type taken from OUR stored extension (the sender's claimed mime "text/html" is ignored)
        $res = $this->admin()->get(route('admin.talent-files.preview', $pdf->id, false))->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline', $res->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $res->headers->get('X-Content-Type-Options'));
        $this->assertSame('image/png', $this->admin()->get(route('admin.talent-files.preview', $png->id, false))->assertOk()->headers->get('Content-Type'));

        // a Word file has no preview
        $this->admin()->get(route('admin.talent-files.preview', $word->id, false))->assertStatus(415);

        // the submission page: Preview for the pdf and the image, none for the Word file
        $page = $this->admin()->get(route('admin.talent-submissions.show', $s->id, false))->assertOk()->getContent();
        $this->assertSame(2, substr_count($page, 'js-preview" data-target'), 'a Preview button for the pdf and the image only');
        $this->assertStringContainsString('no preview for this file type', $page);
    }

    public function test_the_preview_needs_the_cv_files_permission_like_the_download(): void
    {
        Storage::fake('local');
        $s = $this->submission(['track' => 'self']);
        Storage::disk('local')->put('talent/' . $s->id . '/cv_a.pdf', '%PDF-1.4 fake');
        $pdf = TalentAttachment::create(['submission_id' => $s->id, 'kind' => 'cv', 'original_name' => 'cv.pdf', 'path' => 'talent/' . $s->id . '/cv_a.pdf', 'mime' => 'application/pdf', 'size' => 13]);

        $profileId = DB::table('profiles')->insertGetId(['name' => 'T hr2 ' . uniqid(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('profile_permissions')->insert(['profile_id' => $profileId, 'page_id' => DB::table('pages')->where('url', 'admin.talent-submissions.index')->value('id'), 'level' => 'view', 'created_at' => now(), 'updated_at' => now()]);
        $limited = $this->adminUser();
        $limited->profile_id = $profileId;

        $this->actingAs($limited, 'admin')->get(route('admin.talent-files.preview', $pdf->id, false))->assertStatus(403);
        $page = $this->actingAs($limited, 'admin')->get(route('admin.talent-submissions.show', $s->id, false))->assertOk()->getContent();
        $this->assertStringNotContainsString('js-preview', $page, 'no Preview button without the CV Files permission');
    }
}
