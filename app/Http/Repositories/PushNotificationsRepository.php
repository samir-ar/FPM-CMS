<?php


namespace App\Http\Repositories;

use App\AppUser;
use App\Http\Traits\PushNotificationTrait;
use App\Http\Repositories\FpmApisRepository;

class PushNotificationsRepository
{
    use PushNotificationTrait;

    public function sendNotification($members, $data)
    {

    }
}