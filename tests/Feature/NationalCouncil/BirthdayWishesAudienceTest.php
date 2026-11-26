<?php

namespace Tests\Feature\NationalCouncil;

use App\Http\Traits\BirthdayWishesTrait;
use Illuminate\Support\Facades\DB;

// Birthday wishes go only to accounts that opened the app since 24 Sept 2026.
class BirthdayWishesAudienceTest extends CouncilTestCase
{
    public function test_only_accounts_used_since_24_september_are_wished(): void
    {
        // NO real push may leave this machine: nobody else has a birthday (rolled back after the test),
        // and the test accounts carry no push id
        DB::table('app_users')->update(['date_of_birth' => null]);

        $make = function (string $member, ?string $openedOn, array $extra = []) {
            $id = $this->makeAppUser('ZR birthday ' . $member, $member, $extra + ['verified' => 1, 'date_of_birth' => '1980-11-26', 'player_id' => null]);
            if ($openedOn) {
                DB::table('user_activity_logs')->insert(['app_user_id' => $id, 'activity_date' => $openedOn, 'created_at' => $openedOn . ' 09:00:00']);
            }

            return $id;
        };
        $active = $make('5900000951', '2026-10-02');
        $firstDay = $make('5900000952', '2026-09-24');
        $before = $make('5900000953', '2026-09-23');
        $never = $make('5900000954', null);
        $deleted = $make('5900000955', '2026-11-01', ['deleted_at' => now()]);
        $otherDay = $make('5900000956', '2026-11-01', ['date_of_birth' => '1980-11-27']);

        $sender = new class {
            use BirthdayWishesTrait;
        };

        $this->assertSame(2, $sender->sendTodaysBirthdayWishes('2026-11-26'));

        $wished = DB::table('app_users')->whereNotNull('last_birthday_wish_sent_at')
            ->whereIn('id', [$active, $firstDay, $before, $never, $deleted, $otherDay])->orderBy('id')->pluck('id')->all();
        $this->assertSame([$active, $firstDay], $wished);
        $this->assertSame(0, DB::table('users_notifications')->whereIn('user_id', [$before, $never, $deleted, $otherDay])->count());

        $this->assertSame(0, $sender->sendTodaysBirthdayWishes('2026-11-26'), 'nobody is wished twice in a year');
    }
}
