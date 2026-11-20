<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;

// The internal election progress page updates itself every 10 seconds: the swapped parts must exist, in page order,
// and the hourly chart must carry its data in an attribute so it can be redrawn.
class InternalElectionProgressLiveTest extends CouncilTestCase
{
    public function test_the_progress_page_has_its_live_parts_and_the_loop(): void
    {
        $now = now();
        $id = DB::table('internal_elections')->insertGetId(['title' => 'T live progress', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);

        $html = $this->actingAs($this->adminUser(), 'admin')->get(route('admin.internal-election.progress', $id, false))->assertOk()->getContent();

        $pos = array_map(fn ($needle) => strpos($html, $needle), ['id="livePill"', 'id="liveTiles"', 'id="liveBar"', 'id="liveHourlyEmpty"', 'id="hourly-votes-chart"', 'id="liveDistricts"', 'id="liveApp"']);
        $this->assertNotContains(false, $pos, 'every live part is on the page');
        $sorted = $pos;
        sort($sorted);
        $this->assertSame($sorted, $pos, 'the parts appear in the expected order');

        $this->assertStringContainsString('data-hourly="[]"', $html, 'no votes yet: an empty chart payload, the notice is shown');
        $this->assertStringContainsString('setTimeout(tick, 10000)', $html);
        $this->assertStringContainsString('id="liveToggle"', $html);
        // the notice is visible and the chart hidden while there are no votes
        $this->assertMatchesRegularExpression('/id="liveHourlyEmpty" class="alert alert-warning" style="margin:0;"/', $html);
        $this->assertMatchesRegularExpression('/id="hourly-votes-chart" style="height: 250px; display:none;/', $html);
    }
}
