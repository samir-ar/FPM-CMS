<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;

// Instant, Monthly Bills, Add Account and the six Party Organization pages each have their OWN None/View/Full line.
// Before, their routes were checked against the PARENT page (Transactions / Party Organization / All Administrators),
// so the line of the page itself only decided whether the sidebar entry showed.
class SidebarPagesOwnPermissionTest extends CouncilTestCase
{
    private function adminWith(array $grants): \App\User
    {
        $profileId = DB::table('profiles')->insertGetId(['name' => 'T own ' . uniqid(), 'created_at' => now(), 'updated_at' => now()]);
        foreach ($grants as $url => $level) {
            $pageId = DB::table('pages')->where('url', $url)->value('id');
            $this->assertNotNull($pageId, $url);
            DB::table('profile_permissions')->insert(['profile_id' => $profileId, 'page_id' => $pageId, 'level' => $level, 'created_at' => now(), 'updated_at' => now()]);
        }
        $admin = $this->adminUser();
        $admin->profile_id = $profileId; // in memory only

        return $admin;
    }

    private function code(\App\User $admin, string $url, string $method = 'get'): int
    {
        return $this->actingAs($admin, 'admin')->call(strtoupper($method), $url)->getStatusCode();
    }

    public function test_instant_and_monthly_bills_follow_their_own_line_not_the_transactions_line(): void
    {
        $tx = route('admin.transactions.index', [], false);
        $bills = route('admin.bills.index', [], false);

        $onlyInstantView = $this->adminWith(['admin.transactions.index' => 'view']);
        $this->assertNotSame(403, $this->code($onlyInstantView, $tx));
        $this->assertSame(403, $this->code($onlyInstantView, $bills), 'Monthly Bills is closed');
        $this->assertSame(403, $this->code($onlyInstantView, route('admin.transactions.create', [], false)), 'View cannot create');

        $instantFull = $this->adminWith(['admin.transactions.index' => 'full', 'admin.bills.index' => 'view']);
        $this->assertNotSame(403, $this->code($instantFull, route('admin.transactions.create', [], false)));
        $this->assertNotSame(403, $this->code($instantFull, $bills));
        $this->assertSame(403, $this->code($instantFull, route('admin.bills.create', [], false)), 'Bills is only View');
    }

    public function test_each_party_organization_page_has_its_own_level(): void
    {
        $body = route('admin.district-body.index', [], false);
        $local = route('admin.local-body.index', [], false);
        $coord = route('admin.district-coordinator.index', [], false);

        $a = $this->adminWith(['admin.district-body.index' => 'view']);
        $this->assertNotSame(403, $this->code($a, $body));
        $this->assertSame(403, $this->code($a, $local));
        $this->assertSame(403, $this->code($a, $coord));
        $this->assertSame(403, $this->code($a, route('admin.district-body.create', [], false)), 'View cannot create');

        $b = $this->adminWith(['admin.district-body.index' => 'full', 'admin.local-body.index' => 'view']);
        $this->assertNotSame(403, $this->code($b, route('admin.district-body.create', [], false)));
        $this->assertNotSame(403, $this->code($b, $local));
        $this->assertSame(403, $this->code($b, route('admin.local-body.create', [], false)));

        $none = $this->adminWith([]);
        foreach ([$body, $local, $coord, route('admin.central-committee.index', [], false), route('admin.consulting-committee.index', [], false), route('admin.central-committee-coordinator.index', [], false)] as $url) {
            $this->assertSame(403, $this->code($none, $url), "$url closed for a profile without the page");
        }
    }

    public function test_the_shared_helper_routes_open_for_any_one_of_the_party_pages(): void
    {
        // a route with a plain id: the record lookup of get-candidate/{candidate} answers 404 BEFORE the permission check
        // for everybody, which would make this test pass for the wrong reason
        $lookup = '/admin/get-regions-by-district-id/1';
        foreach (['admin.consulting-committee.index', 'admin.local-body.index', 'admin.district-coordinator.index', 'admin.central-committee.index'] as $url) {
            $this->assertNotSame(403, $this->code($this->adminWith([$url => 'view']), $lookup, 'post'), "helper open for $url");
        }
        $this->assertSame(403, $this->code($this->adminWith(['admin.transactions.index' => 'full']), $lookup, 'post'), 'a page of another group does not open it');
        $this->assertSame(403, $this->code($this->adminWith([]), $lookup, 'post'));
    }

    public function test_add_account_needs_full_on_its_own_line(): void
    {
        $url = route('admin.add_user_form', [], false);
        $this->assertSame(403, $this->code($this->adminWith(['admin.admins.index' => 'full']), $url), 'All Administrators alone no longer opens Add Account');
        $this->assertSame(403, $this->code($this->adminWith(['admin.add_user_form' => 'view']), $url), 'View is not enough to add an account');
        $this->assertNotSame(403, $this->code($this->adminWith(['admin.add_user_form' => 'full']), $url));
    }

    public function test_no_route_is_checked_against_the_old_parent_page_ids_any_more(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $this->assertSame(0, preg_match("/page-perm:50\b|page-perm:1,/", $routes));
    }
}
