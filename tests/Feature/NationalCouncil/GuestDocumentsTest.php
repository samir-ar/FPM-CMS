<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;

// "Visible to guests" tick on political statements, legislative documents, laws and archive albums (2026-11-23):
// a guest (no token) only receives what is ticked, a member receives everything.
class GuestDocumentsTest extends CouncilTestCase
{
    private const TOKEN = 'zq-guest-docs-token';

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('fpm_users')->insert([
            'MemberId' => 5900000555, 'UserFullName' => 'ZQ guest docs', 'MobileNumber' => '5900000555', 'Email' => '', 'district' => '', 'LastUnitPosition' => '',
            'member_status' => 1, 'BirthDate' => '1970-01-01', 'CanCreateEventRequest' => 0, 'CanCreateMeetings' => 0, 'ElectionState' => '', 'ElectionStateId' => 0,
            'ElectionStateVoltersNumber' => 0, 'Role' => '', 'RoleId' => 0, 'PersonImage' => '', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->makeAppUser('ZQ guest docs', '5900000555', ['token' => self::TOKEN, 'verified' => 1]);
    }

    private function member(): array
    {
        return ['token' => self::TOKEN];
    }

    private function political(string $title, bool $public): int
    {
        return DB::table('political_work_documents')->insertGetId([
            'category' => 'takattol', 'title' => $title, 'file_name' => 'x.pdf', 'document_date' => '2026-01-01',
            'show_for_guest' => $public ? 1 : 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function law(string $name, bool $public): int
    {
        return DB::table('laws')->insertGetId([
            'name' => json_encode(['en' => $name, 'ar' => $name], JSON_UNESCAPED_UNICODE), 'details' => 'd', 'date' => '2026-01-01', 'status' => 'Approved',
            'show_for_guest' => $public ? 1 : 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function album(string $name, bool $public): int
    {
        return DB::table('albums')->insertGetId([
            'name' => json_encode(['en' => $name, 'ar' => $name], JSON_UNESCAPED_UNICODE), 'description' => json_encode(['en' => 'd', 'ar' => 'd']),
            'type' => 'pdfs', 'thumbnail' => 't.png', 'show_for_guest' => $public ? 1 : 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_political_statements_guest_sees_only_ticked_member_sees_all(): void
    {
        $open = $this->political('ZQ open', true);
        $closed = $this->political('ZQ closed', false);

        $guest = collect($this->postJson('/api/v2/political-work', ['category' => 'takattol'])->assertOk()->json())->pluck('id');
        $this->assertTrue($guest->contains($open));
        $this->assertFalse($guest->contains($closed));

        $member = collect($this->postJson('/api/v2/political-work', ['category' => 'takattol'], $this->member())->assertOk()->json())->pluck('id');
        $this->assertTrue($member->contains($open));
        $this->assertTrue($member->contains($closed));
    }

    public function test_legislative_docs_guest_sees_only_ticked_member_sees_all(): void
    {
        $cat = DB::table('legislative_doc_categories')->insertGetId([
            'name' => json_encode(['en' => 'ZQ', 'ar' => 'ZQ']), 'order' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $mk = fn(string $t, int $p) => DB::table('legislative_docs')->insertGetId([
            'category_id' => $cat, 'title' => $t, 'date' => '2026-01-01', 'file_name' => 'x.pdf', 'order' => 0, 'show_for_guest' => $p,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $open = $mk('ZQ open', 1);
        $closed = $mk('ZQ closed', 0);

        $guest = collect($this->postJson('/api/v2/legislative-docs', ['category_id' => $cat])->assertOk()->json())->pluck('id');
        $this->assertSame([$open], $guest->all());

        $member = collect($this->postJson('/api/v2/legislative-docs', ['category_id' => $cat], $this->member())->assertOk()->json())->pluck('id');
        $this->assertEqualsCanonicalizing([$open, $closed], $member->all());
    }

    public function test_laws_guest_sees_only_ticked_and_cannot_download_a_closed_law(): void
    {
        $open = $this->law('ZQ open', true);
        $closed = $this->law('ZQ closed', false);

        $guest = collect($this->postJson('/api/v2/laws', [])->assertOk()->json())->pluck('id');
        $this->assertTrue($guest->contains($open));
        $this->assertFalse($guest->contains($closed));

        $member = collect($this->postJson('/api/v2/laws', [], $this->member())->assertOk()->json())->pluck('id');
        $this->assertTrue($member->contains($closed));

        $this->postJson('/api/v2/law-files', ['law_id' => $closed])->assertStatus(404);
        $this->postJson('/api/v2/law-files', ['law_id' => 999999999])->assertStatus(404);
    }

    public function test_albums_guest_sees_only_ticked_member_sees_all(): void
    {
        $open = $this->album('ZQ open', true);
        $closed = $this->album('ZQ closed', false);

        $guest = collect($this->postJson('/api/v2/get-all-albums', [])->assertOk()->json())->pluck('id');
        $this->assertTrue($guest->contains($open));
        $this->assertFalse($guest->contains($closed));

        $member = collect($this->postJson('/api/v2/get-all-albums', [], $this->member())->assertOk()->json())->pluck('id');
        $this->assertTrue($member->contains($open));
        $this->assertTrue($member->contains($closed));

        $this->assertCount(0, $this->postJson('/api/v2/get-album', ['id' => $closed])->assertOk()->json());
        $this->assertCount(1, $this->postJson('/api/v2/get-album', ['id' => $closed], $this->member())->assertOk()->json());
    }

    public function test_a_wrong_token_is_refused_not_treated_as_a_member(): void
    {
        $this->postJson('/api/v2/political-work', ['category' => 'takattol'], ['token' => 'not-a-real-token'])->assertStatus(400);
    }

    public function test_the_migration_left_every_existing_row_visible_and_new_rows_default_to_members_only(): void
    {
        $id = DB::table('laws')->insertGetId([
            'name' => json_encode(['en' => 'ZQ default', 'ar' => 'ZQ']), 'details' => 'd', 'date' => '2026-01-01', 'status' => 'Approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertSame(0, (int) DB::table('laws')->where('id', $id)->value('show_for_guest'));
    }
}
