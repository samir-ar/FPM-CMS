<?php


namespace App\Http\Repositories;


use Hash;
use App\AppUser;
use App\Session;
use App\Http\Traits\TokenTrait;
use App\Services\QrCodeGenerator;


class UserRepository
{
    use TokenTrait;


    private function normalizePhone($phone_number)
    {
        return ltrim($phone_number, '+');
    }

    public function addUser($data)
    {
        $user = new AppUser();
        $user->member_id = $data['member_id'];
        $user->phone_number = $this->normalizePhone($data['phone_number']);
        $user->token = $data['token'];
        $user->image = base64_encode($data['image']);
        $user->name = $data['name'];

        try {
            $user->qr_code = QrCodeGenerator::generateBase64($data['member_id']);
        } catch (\Exception $e) {
            $user->qr_code = null;
        }

        if (isset($data['player_id'])) {
            $existing_users = AppUser::where('player_id', $data['player_id'])->get();
            foreach($existing_users as $u){
                $u->player_id = null;
                $u->save();
            }
            $user->player_id = $data['player_id'];
        }

        $user->save();

        return $user;
    }

    public function hasRegistered($phone_number)
    {
        $user = AppUser::select('id', 'verified', 'phone_number')
            ->whereRaw("TRIM(LEADING '+' FROM phone_number) = ?", [$this->normalizePhone($phone_number)])
            ->where('verified', true)->first();

        return $user ? true : false;
    }

    // The account a login belongs to. The MEMBER ID is the identity: the account of this member (leading zero
    // ignored) is reused whatever phone number it was created with, and the account of ANOTHER member is never
    // returned just because they share a phone number (families do: about 1,000 members share a phone). Before, the
    // match was "this member id OR this phone, newest first", so a member logging in on a shared phone could take
    // over the other member's account row (new member id, name, photo and token written onto it).
    //
    // An active account always wins over a soft-deleted one (a trashed one is only restored when the member has no
    // active account at all). Without a member id (not used by any caller today) it falls back to the phone alone.
    public function getUserByPhoneNumber($phone_number, $member_id = null)
    {
        $phone = $this->normalizePhone($phone_number);

        $applyMatch = function ($query) use ($phone, $member_id) {
            if ($member_id) {
                $stripped = ltrim((string) $member_id, '0');
                $ids = array_values(array_unique(array_filter([(string) $member_id, $stripped, '0' . $stripped], fn ($v) => $v !== '' && $v !== '0')));
                $query->whereIn('member_id', $ids);
            } else {
                $query->whereRaw("TRIM(LEADING '+' FROM phone_number) = ?", [$phone]);
            }

            // the account that already has this phone first, then the most recently used
            return $query->orderByRaw("CASE WHEN TRIM(LEADING '+' FROM phone_number) = ? THEN 0 ELSE 1 END", [$phone])
                ->orderBy('updated_at', 'desc')->orderBy('id', 'desc');
        };

        $user = $applyMatch(AppUser::query())->first();

        if ($user) {
            return $user;
        }

        $user = $applyMatch(AppUser::onlyTrashed())->first();

        if ($user) {
            $user->restore();
        }

        return $user;
    }
}
