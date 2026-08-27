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

    public function getUserByPhoneNumber($phone_number, $member_id = null)
    {
        $applyMatch = function ($query) use ($phone_number, $member_id) {
            if ($member_id) {
                //member_id is the real unique FPM identifier — match on it as well
                //as phone (which can vary in stored format, e.g. leading '+' or
                //not). Matching only on phone let duplicate rows for the same
                //member go unmatched whenever the stored/incoming phone format
                //didn't line up.
                $query->where(function ($q) use ($phone_number, $member_id) {
                    $q->where('member_id', $member_id)
                        ->orWhereRaw("TRIM(LEADING '+' FROM phone_number) = ?", [$this->normalizePhone($phone_number)]);
                });
            } else {
                $query->whereRaw("TRIM(LEADING '+' FROM phone_number) = ?", [$this->normalizePhone($phone_number)]);
            }

            return $query;
        };

        //An already-active row always wins first — never let a soft-deleted
        //row (even one touched more recently, e.g. by cleanup work) outrank
        //a currently-active account and get restored on top of it, which
        //would produce two active rows instead of one.
        $user = $applyMatch(AppUser::query())->orderBy('updated_at', 'desc')->first();

        if ($user) {
            return $user;
        }

        //No active row matched: a soft-delete (accidental or intentional)
        //would otherwise make this lookup blind and spawn a brand new
        //duplicate row on next login — restore the most recently updated
        //trashed match instead.
        $user = $applyMatch(AppUser::onlyTrashed())->orderBy('updated_at', 'desc')->first();

        if ($user) {
            $user->restore();
        }

        return $user;
    }
}
