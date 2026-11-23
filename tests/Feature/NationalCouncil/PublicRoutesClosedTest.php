<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;

// Found by the guest audit of 2026-11-23: three things were open to anybody without a login.
class PublicRoutesClosedTest extends CouncilTestCase
{
    public function test_the_register_page_and_route_are_gone(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'ZQ', 'email' => 'zq-reg-' . uniqid() . '@example.com', 'password' => 'Probe12345!', 'password_confirmation' => 'Probe12345!'])->assertNotFound();
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('register'));
        $this->assertFileDoesNotExist(resource_path('views/auth/register.blade.php'));

        // the admin login and the password reset pages are still there
        $this->get('/login')->assertOk();
        $this->get('/password/reset')->assertOk();
    }

    public function test_the_legacy_representatives_list_is_gone_but_the_current_one_stays(): void
    {
        $this->postJson('/api/representatives', [])->assertNotFound();

        // the list the app uses (v2) still works for a guest: only the two public boxes
        $res = $this->postJson('/api/v2/representatives', [])->assertOk()->json();
        foreach ($res['sections'] as $s) {
            $this->assertContains((int) $s['id'], [11, 12]);
        }
    }

    public function test_the_community_lists_need_a_logged_in_member(): void
    {
        $typeId = DB::table('business_types')->value('id');
        foreach ([
            'business-type' => [],
            'get-community' => [],
            'community-districts' => [],
            'directory-members' => ['business_type_id' => $typeId ?: 1],
        ] as $path => $data) {
            $this->postJson('/api/v2/' . $path, $data)->assertStatus(401);
            $this->withHeaders(['token' => 'not-a-real-token'])->postJson('/api/v2/' . $path, $data)->assertStatus(401);
        }

        // a verified member gets in (answers 200, or 400 for the directory when there is no such type - never 401)
        // (the login check also looks the member up in the member list, so he gets a row there like every real member)
        DB::table('fpm_users')->insert([
            'MemberId' => 5900000222, 'UserFullName' => 'ZQ member', 'MobileNumber' => '5900000222', 'Email' => '', 'district' => '', 'LastUnitPosition' => '',
            'member_status' => 1, 'BirthDate' => '1970-01-01', 'CanCreateEventRequest' => 0, 'CanCreateMeetings' => 0, 'ElectionState' => '', 'ElectionStateId' => 0,
            'ElectionStateVoltersNumber' => 0, 'Role' => '', 'RoleId' => 0, 'PersonImage' => '', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->makeAppUser('ZQ member', '5900000222', ['token' => 'zq-community-token', 'verified' => 1]);
        foreach (['business-type', 'get-community', 'community-districts'] as $path) {
            $this->withHeaders(['token' => 'zq-community-token'])->postJson('/api/v2/' . $path, [])->assertOk();
        }
        $this->assertNotSame(401, $this->withHeaders(['token' => 'zq-community-token'])->postJson('/api/v2/directory-members', ['business_type_id' => $typeId ?: 1])->getStatusCode());
    }

    // content the app never offers to guests is members only; the political statements, legislative documents and laws stay public on purpose
    public function test_achievements_national_plans_faq_and_payment_amounts_need_a_member_and_the_old_open_aliases_are_gone(): void
    {
        foreach (['achievements' => [], 'national-plans' => ['tab' => 'iqtisad'], 'faq-categories' => [], 'faqs' => ['cat_id' => 1], 'get-payment-amounts' => []] as $path => $data) {
            $this->postJson('/api/v2/' . $path, $data)->assertStatus(401);
        }

        DB::table('fpm_users')->insert([
            'MemberId' => 5900000333, 'UserFullName' => 'ZQ member 2', 'MobileNumber' => '5900000333', 'Email' => '', 'district' => '', 'LastUnitPosition' => '',
            'member_status' => 1, 'BirthDate' => '1970-01-01', 'CanCreateEventRequest' => 0, 'CanCreateMeetings' => 0, 'ElectionState' => '', 'ElectionStateId' => 0,
            'ElectionStateVoltersNumber' => 0, 'Role' => '', 'RoleId' => 0, 'PersonImage' => '', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->makeAppUser('ZQ member 2', '5900000333', ['token' => 'zq-content-token', 'verified' => 1]);
        $this->withHeaders(['token' => 'zq-content-token'])->postJson('/api/v2/achievements', [])->assertOk();
        $this->withHeaders(['token' => 'zq-content-token'])->postJson('/api/v2/faq-categories', [])->assertOk();

        // the old API group no longer has open copies of these (404, not even a refusal)
        foreach (['about-us', 'country-codes', 'faq-categories', 'faqs', 'get-payment-amounts', 'send-verification', 'verify-pin'] as $path) {
            $this->postJson('/api/' . $path, [])->assertNotFound();
        }
    }

    public function test_political_statements_legislative_documents_and_laws_stay_public(): void
    {
        $this->postJson('/api/v2/political-work', ['category' => 'takattol'])->assertOk();
        $this->postJson('/api/v2/legislative-categories', [])->assertOk();
        $this->postJson('/api/v2/laws', [])->assertOk();
        $cat = DB::table('legislative_doc_categories')->value('id');
        if ($cat) {
            $this->postJson('/api/v2/legislative-docs', ['category_id' => $cat])->assertOk();
            $this->postJson('/api/v2/legislative-subcategories', ['category_id' => $cat])->assertOk();
        }
        // and what the app needs before login
        $this->postJson('/api/v2/about-us', [])->assertOk();
        $this->postJson('/api/v2/country-codes', [])->assertOk();
        $this->postJson('/api/v2/app-versions', [])->assertOk();
    }

    public function test_the_public_pieces_that_guests_need_are_untouched(): void
    {
        // Talk to us shows the social links, the app proxies community images: both stay public
        $this->postJson('/api/v2/social-links', [])->assertOk();
        $this->postJson('/api/v2/app-versions', [])->assertOk();
        $this->postJson('/api/v2/country-codes', [])->assertOk();
    }
}
