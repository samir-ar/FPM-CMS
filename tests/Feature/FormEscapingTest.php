<?php

namespace Tests\Feature;

use App\Http\Traits\FormTrait;
use Illuminate\Support\Facades\DB;
use Tests\Feature\NationalCouncil\CouncilTestCase;

/**
 * A saved title with an apostrophe (Monaco's, L'Orient, 'quoted') was pasted raw
 * inside value='...', so the edit form showed it cut at the apostrophe and saving
 * the form wrote the cut title back. Values must survive a round trip through
 * the form unchanged.
 */
class FormEscapingTest extends CouncilTestCase
{
    private function inputValue(string $html, string $name): ?string
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?><body>' . $html . '</body>');
        foreach ($dom->getElementsByTagName('input') as $input) {
            if ($input->getAttribute('name') === $name) {
                return $input->getAttribute('value');
            }
        }

        return null;
    }

    public function test_a_value_with_apostrophes_quotes_and_symbols_comes_back_unchanged()
    {
        $form = new class {
            use FormTrait;
        };

        foreach ([
            "Monaco's money laundering probe",
            "L'Orient-Le Jour",
            "باسيل: نجحنا في نقابة ال'محامين' اليوم",
            'He said "hello" & left <b>now</b>',
            "mixed ' and \" and & and < and >",
        ] as $original) {
            foreach (['small_text', 'number', 'hidden'] as $type) {
                $html = $form->drawHtml($type, 'L', 'f', $original);
                $this->assertSame(
                    $original,
                    $this->inputValue($html, 'f'),
                    "$type input lost part of: $original"
                );
            }
        }
    }

    public function test_a_placeholder_with_an_apostrophe_does_not_break_the_field()
    {
        $form = new class {
            use FormTrait;
        };

        $html = $form->drawHtml('small_text', 'L', 'f', 'value', null, "Enter the person's name");

        $this->assertSame('value', $this->inputValue($html, 'f'));
        $this->assertStringContainsString("person&#039;s name", $html);
    }

    public function test_the_news_edit_form_shows_the_full_title_and_saving_it_does_not_cut_it()
    {
        $title = "باسيل: نجحنا في نقابة ال'محامين' اليوم";
        $id = DB::table('news')->insertGetId([
            'title' => json_encode(['en' => $title, 'ar' => $title], JSON_UNESCAPED_UNICODE),
            'source' => json_encode(['en' => 's', 'ar' => 'م'], JSON_UNESCAPED_UNICODE),
            'details' => json_encode(['en' => '<p>d</p>', 'ar' => '<p>ت</p>'], JSON_UNESCAPED_UNICODE),
            'date' => now(), 'show_for_guest' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('groups_news')->insert(['news_id' => $id, 'group_group_id' => 81, 'created_at' => now(), 'updated_at' => now()]);
        $admin = $this->adminUser();

        // 1) the form shows the whole title
        $html = $this->actingAs($admin, 'admin')->get(route('admin.news.edit', $id, false))->assertOk()->getContent();
        $this->assertSame($title, $this->inputValue($html, 'title'), 'the edit form must show the complete title');

        // 2) submitting the form exactly as the browser would keeps it complete
        $this->actingAs($admin, 'admin')->put(route('admin.news.update', $id, false), [
            'title' => $this->inputValue($html, 'title'),
            'title_ar' => $this->inputValue($html, 'title_ar'),
            'source' => 's', 'source_ar' => 'م', 'details' => 'd', 'details_ar' => 'ت',
            'date' => now()->format('Y-m-d H:i'), 'strict_lang' => 'ar', 'groups' => [81], 'show_for_guest' => '1',
        ]);

        $saved = json_decode(DB::table('news')->where('id', $id)->value('title'), true);
        $this->assertSame($title, $saved['en']);
        $this->assertSame($title, $saved['ar']);
    }
}
