<?php

namespace Tests\Feature\NationalCouncil;

use App\Http\Controllers\Api\V2\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// The FPM Talent terms exist in Arabic (the default) and English; the app has ONE button that switches between them.
class TalentTermsLanguageTest extends CouncilTestCase
{
    private function terms(array $payload): array
    {
        $req = Request::create('/api/v2/competency-static-page', 'POST', $payload);
        app()->instance('request', $req);

        return json_decode(app(ApiController::class)->getCompetencyStaticPage($req)->getContent(), true);
    }

    public function test_arabic_is_the_default_and_english_comes_with_the_language_switch(): void
    {
        $now = now();
        DB::table('contents')->where('category', 'like', 'competency-terms%')->delete();
        DB::table('contents')->insert([
            ['category' => 'competency-terms', 'text' => '<p>الشروط بالعربية</p>', 'created_at' => $now, 'updated_at' => $now],
            ['category' => 'competency-terms-en', 'text' => '<p>The terms in English</p>', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $default = $this->terms(['type' => 'terms']);
        $this->assertSame('ar', $default['language']);
        $this->assertStringContainsString('الشروط بالعربية', $default['content']);

        $this->assertStringContainsString('الشروط بالعربية', $this->terms(['type' => 'terms', 'language' => 'ar'])['content']);

        $en = $this->terms(['type' => 'terms', 'language' => 'en']);
        $this->assertSame('en', $en['language']);
        $this->assertStringContainsString('The terms in English', $en['content']);
    }

    // the real route runs the SetLanguage middleware, which writes the phone's Accept-Language over request('language')
    public function test_the_buttons_language_wins_over_the_phones_accept_language_header(): void
    {
        $now = now();
        DB::table('contents')->where('category', 'like', 'competency-terms%')->delete();
        DB::table('contents')->insert([
            ['category' => 'competency-terms', 'text' => '<p>الشروط بالعربية</p>', 'created_at' => $now, 'updated_at' => $now],
            ['category' => 'competency-terms-en', 'text' => '<p>The terms in English</p>', 'created_at' => $now, 'updated_at' => $now],
        ]);

        foreach (['en', 'ar', null] as $phone) {
            $headers = $phone ? ['Accept-Language' => $phone] : [];
            $ar = $this->withHeaders($headers)->postJson('/api/v2/competency-static-page', ['type' => 'terms', 'language' => 'ar'])->json();
            $this->assertSame('ar', $ar['language'], "Arabic button, phone '$phone'");
            $this->assertStringContainsString('الشروط بالعربية', $ar['content']);

            $en = $this->withHeaders($headers)->postJson('/api/v2/competency-static-page', ['type' => 'terms', 'language' => 'en'])->json();
            $this->assertSame('en', $en['language'], "English button, phone '$phone'");
            $this->assertStringContainsString('The terms in English', $en['content']);
        }
    }

    public function test_an_english_version_not_written_yet_comes_back_empty_not_as_arabic(): void
    {
        DB::table('contents')->where('category', 'competency-terms-en')->delete();

        $this->assertEmpty($this->terms(['type' => 'terms', 'language' => 'en'])['content']);
    }

    public function test_the_cms_page_saves_the_english_terms_next_to_the_arabic_ones(): void
    {
        $this->actingAs($this->adminUser(), 'admin')->post(route('admin.competency-static.update', [], false), [
            'terms' => '<p>عربي</p>', 'terms_en' => '<p>English text</p>', 'faq' => '',
        ])->assertRedirect();

        $this->assertSame('<p>English text</p>', DB::table('contents')->where('category', 'competency-terms-en')->value('text'));
        $this->assertSame('<p>عربي</p>', DB::table('contents')->where('category', 'competency-terms')->value('text'));

        $page = $this->actingAs($this->adminUser(), 'admin')->get(route('admin.competency-static.index', [], false))->assertOk()->getContent();
        $this->assertStringContainsString('Terms and Conditions (English)', $page);
    }
}
