<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;

// Saving a profile deletes every grant that has no ticked line on the Profile form. The sidebar items without such a line
// (Instant, Monthly Bills, Add Account, six Party Organization pages) vanished from the menu of production's Super Admin.
class RestoreSidebarPagesTest extends CouncilTestCase
{
    private const URLS = [
        'admin.transactions.index', 'admin.bills.index', 'admin.add_user_form', 'admin.district-coordinator.index',
        'admin.district-body.index', 'admin.local-body.index', 'admin.central-committee-coordinator.index',
        'admin.central-committee.index', 'admin.consulting-committee.index',
    ];

    private function migrate(): void
    {
        (include database_path('migrations/2026_11_22_120000_restore_sidebar_pages_lost_by_profile_save.php'))->up();
    }

    public function test_lost_grants_come_back_for_super_admin_and_the_pages_get_a_profile_form_line(): void
    {
        $super = DB::table('profiles')->where('name', 'Super Admin')->value('id');
        $ids = DB::table('pages')->whereIn('url', self::URLS)->pluck('id', 'url');
        $this->assertCount(count(self::URLS), $ids);

        // what a profile save did on production
        DB::table('profile_permissions')->where('profile_id', $super)->whereIn('page_id', $ids->values())->delete();
        DB::table('pages')->whereIn('id', $ids->values())->update(['show_in_profile_form' => false]);

        $this->migrate();

        foreach ($ids as $url => $id) {
            $this->assertSame('full', DB::table('profile_permissions')->where('profile_id', $super)->where('page_id', $id)->value('level'), "$url");
            $this->assertEquals(1, DB::table('pages')->where('id', $id)->value('show_in_profile_form'), "$url is on the Profile form");
        }

        // the sidebar rule: a child shows when the admin has its own grant
        $this->assertTrue($this->adminUser()->hasPage($ids['admin.transactions.index']));
        $this->assertTrue($this->adminUser()->hasPage($ids['admin.add_user_form']));
    }

    public function test_another_profile_follows_its_parent_and_a_made_choice_is_never_overwritten(): void
    {
        $txRoot = DB::table('pages')->where('name', 'Transactions')->whereNull('parent_id')->value('id');
        $instant = DB::table('pages')->where('url', 'admin.transactions.index')->value('id');
        $bills = DB::table('pages')->where('url', 'admin.bills.index')->value('id');
        $other = DB::table('profiles')->insertGetId(['name' => 'T other ' . uniqid(), 'created_at' => now(), 'updated_at' => now()]);
        $noTx = DB::table('profiles')->insertGetId(['name' => 'T none ' . uniqid(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('profile_permissions')->insert([
            ['profile_id' => $other, 'page_id' => $txRoot, 'level' => 'view', 'created_at' => now(), 'updated_at' => now()],
            ['profile_id' => $other, 'page_id' => $bills, 'level' => 'full', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('profile_permissions')->where('profile_id', $other)->where('page_id', $instant)->delete();

        $this->migrate();
        $this->migrate(); // twice: no duplicates, nothing changes

        $this->assertSame('view', DB::table('profile_permissions')->where('profile_id', $other)->where('page_id', $instant)->value('level'), 'follows the parent');
        $this->assertSame('full', DB::table('profile_permissions')->where('profile_id', $other)->where('page_id', $bills)->value('level'), 'an existing grant is kept as it was');
        $this->assertSame(1, DB::table('profile_permissions')->where('profile_id', $other)->where('page_id', $instant)->count());
        $this->assertSame(0, DB::table('profile_permissions')->where('profile_id', $noTx)->whereIn('page_id', [$instant, $bills])->count(), 'a profile without Transactions gets nothing');
    }

    public function test_saving_a_profile_no_longer_wipes_these_pages_once_they_are_on_the_form(): void
    {
        $this->migrate();
        $form = DB::table('pages')->whereIn('url', self::URLS)->pluck('show_in_profile_form');
        $this->assertTrue($form->every(fn ($v) => (int) $v === 1));
    }
}
