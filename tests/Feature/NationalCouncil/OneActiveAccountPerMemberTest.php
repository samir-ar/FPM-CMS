<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// The database itself allows ONE active app account per member (leading zero ignored). Hidden (soft-deleted)
// accounts and accounts without a member id are not counted, so the clean-up leftovers and deleted accounts are fine.
class OneActiveAccountPerMemberTest extends CouncilTestCase
{
    private function insertAccount(string $memberId, array $extra = []): int
    {
        return DB::table('app_users')->insertGetId($extra + ['name' => 'T', 'member_id' => $memberId, 'phone_number' => (string) random_int(1000000000, 9999999999), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_a_second_active_account_of_the_same_member_is_refused(): void
    {
        $this->insertAccount('5611000001');

        foreach (['5611000001', '05611000001'] as $same) {
            try {
                $this->insertAccount($same);
                $this->fail("a second active account for $same should have been refused");
            } catch (QueryException $e) {
                $this->assertSame('23000', $e->getCode());
            }
        }

        $this->insertAccount('5611000002');   // another member: fine
        $this->assertSame(2, DB::table('app_users')->whereIn('member_id', ['5611000001', '5611000002'])->count());
    }

    public function test_hidden_and_member_less_accounts_never_collide(): void
    {
        $active = $this->insertAccount('5611000003');
        $this->insertAccount('5611000003', ['deleted_at' => now()]);
        $this->insertAccount('05611000003', ['deleted_at' => now()]);   // several hidden ones are fine
        $this->insertAccount('', ['deleted_at' => null]);
        DB::table('app_users')->insert(['name' => 'T none 1', 'member_id' => null, 'phone_number' => '1', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('app_users')->insert(['name' => 'T none 2', 'member_id' => null, 'phone_number' => '2', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(3, DB::table('app_users')->where('member_id', 'like', '%5611000003')->count());
        $this->assertNotNull($active);
    }

    public function test_a_deleted_account_cannot_be_brought_back_next_to_an_active_one(): void
    {
        $this->insertAccount('5611000004');
        $hidden = $this->insertAccount('5611000004', ['deleted_at' => now()]);

        try {
            DB::table('app_users')->where('id', $hidden)->update(['deleted_at' => null]);
            $this->fail('restoring a second account of an active member should have been refused');
        } catch (QueryException $e) {
            $this->assertSame('23000', $e->getCode());
        }

        // once the active one is hidden, the other can come back
        DB::table('app_users')->where('member_id', '5611000004')->whereNull('deleted_at')->update(['deleted_at' => now()]);
        DB::table('app_users')->where('id', $hidden)->update(['deleted_at' => null]);
        $this->assertSame(1, DB::table('app_users')->where('member_id', '5611000004')->whereNull('deleted_at')->count());
    }
}
