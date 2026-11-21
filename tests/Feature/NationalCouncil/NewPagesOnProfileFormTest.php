<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;

// Login Limits and Home Card Icons each have their own line on the Profile form (permissions), so the admin decides who
// sees them; the routes are guarded by those lines.
class NewPagesOnProfileFormTest extends CouncilTestCase
{
    public function test_both_pages_exist_flagged_for_the_profile_form_and_are_granted_to_super_admin(): void
    {
        foreach (['admin.users.login-limits' => 'Login Limits', 'admin.home-card-icons.index' => 'Home Card Icons'] as $url => $name) {
            $page = DB::table('pages')->where('url', $url)->first();
            $this->assertNotNull($page, "$name page row exists");
            $this->assertEquals(1, $page->show_in_profile_form, "$name is listed on the Profile form");
            $superId = DB::table('profiles')->where('name', 'Super Admin')->value('id');
            $this->assertSame('full', DB::table('profile_permissions')->where('profile_id', $superId)->where('page_id', $page->id)->value('level'));
        }
    }

    public function test_the_profile_form_shows_both_lines(): void
    {
        $profileId = DB::table('profiles')->where('name', 'Super Admin')->value('id');
        $html = $this->actingAs($this->adminUser(), 'admin')->get(route('admin.profiles.edit', $profileId, false))->assertOk()->getContent();

        $this->assertStringContainsString('Login Limits', $html);
        $this->assertStringContainsString('Home Card Icons', $html);
    }
}
