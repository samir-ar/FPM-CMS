<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;

// CMS > National Council polls list: no per-poll "Results" column (hidden 2026-11-26); the results board button stays.
class CouncilPollListColumnsTest extends CouncilTestCase
{
    public function test_the_list_has_no_results_column(): void
    {
        $id = DB::table('council_national_polls')->insertGetId(['title' => 'ZR list poll', 'is_published' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->adminUser(), 'admin');

        $html = $this->get(route('admin.national-council-poll.index', [], false))->assertOk()->getContent();
        $this->assertStringNotContainsString('<th>Results</th>', $html);
        $this->assertStringNotContainsString('"data":"results"', $html);
        $this->assertStringContainsString('<th>Show on results page</th>', $html);
        $this->assertStringContainsString('Results of several polls', $html);

        $url = route('admin.national-council-poll.index', [], false) . '?draw=1&start=0&length=100&columns[0][data]=id&columns[0][name]=id&columns[0][orderable]=true&order[0][column]=0&order[0][dir]=desc';
        $row = collect($this->getJson($url, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data'))->firstWhere('id', $id);
        $this->assertArrayNotHasKey('results', $row);
        $this->assertArrayHasKey('questions', $row);
    }
}
