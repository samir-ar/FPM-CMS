<?php

namespace Tests\Feature;

use App\Imports\MukhtarsImport;
use App\Imports\MunicipalityMembersImport;
use App\Support\LebanesePhone;
use Illuminate\Support\Facades\DB;
use Tests\Feature\NationalCouncil\CouncilTestCase;

// Excel turns the phone 03451111 into the number 3451111: the municipality / mukhtar lists were loaded with 7-digit phones
// that lost their leading 0 (and cannot be dialled). Every number of exactly 7 digits gets its 0 back, at import time too.
class PhoneLeadingZeroTest extends CouncilTestCase
{
    private const QADA = 'ZQ-test-qada';

    protected function tearDown(): void
    {
        // these two tables are not rolled back by the test transaction on this machine
        DB::table('municipality_members')->where('qada', self::QADA)->delete();
        DB::table('mukhtars')->where('qada', self::QADA)->delete();
        parent::tearDown();
    }

    public function test_a_number_of_exactly_seven_digits_gets_its_leading_zero_and_nothing_else_changes(): void
    {
        foreach ([
            '3451111' => '03451111',        // mobile 03
            '4964098' => '04964098',        // landline 04 (Metn)
            '1234567' => '01234567',        // landline 01 (Beirut)
            ' 345 1111 ' => '03451111',     // spaces written inside
            '345-1111' => '03451111',
        ] as $in => $out) {
            $this->assertSame($out, LebanesePhone::withLeadingZero((string) $in), "'$in'");
        }

        foreach (['03451111', '70184382', '81123456', '+96103451111', '96103451111', '123456', '123456789', '', '#N/A', 'no phone'] as $same) {
            $this->assertSame($same, LebanesePhone::withLeadingZero($same), "'$same' stays as it is");
        }
    }

    public function test_both_imports_store_the_phone_with_its_leading_zero(): void
    {
        // Excel hands the number over as an integer: the import must not store 3451111 / 4964098
        $mukhtars = collect([
            ['قضاء', 'قرية', 'حي', 'اسم', 'أصوات', 'صفة', 'هاتف', 'منتسب'],
            [self::QADA, 'ZQ village', '', 'ZQ mukhtar one', 5, 'مختار', 3451111, ''],
            [self::QADA, 'ZQ village', '', 'ZQ mukhtar two', 3, 'عضو اختياري', '70184382', ''],
            [self::QADA, 'ZQ village', '', 'ZQ mukhtar three', 1, 'عضو اختياري', '', ''],
        ]);
        (new MukhtarsImport())->collection($mukhtars);
        $phones = DB::table('mukhtars')->where('qada', self::QADA)->orderBy('sort_order')->pluck('phone', 'full_name')->all();
        $this->assertSame('03451111', $phones['ZQ mukhtar one']);
        $this->assertSame('70184382', $phones['ZQ mukhtar two'], 'an 8-digit mobile is left alone');
        $this->assertNull($phones['ZQ mukhtar three']);

        $members = collect([
            ['قضاء', 'بلدية', 'قرية', 'اسم', 'أصوات', 'صفة', 'هاتف', 'منتسب'],
            [self::QADA, 'ZQ municipality', 'ZQ village', 'ZQ member one', 9, 'رئيس', 4964098, ''],
            [self::QADA, 'ZQ municipality', 'ZQ village', 'ZQ member two', 8, 'عضو', '03123456', ''],
        ]);
        (new MunicipalityMembersImport())->collection($members);
        $phones = DB::table('municipality_members')->where('qada', self::QADA)->pluck('phone', 'full_name')->all();
        $this->assertSame('04964098', $phones['ZQ member one']);
        $this->assertSame('03123456', $phones['ZQ member two']);
    }
}
