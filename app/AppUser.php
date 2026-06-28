<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;


class AppUser extends Authenticatable
{
    use SoftDeletes;
    use Notifiable;


    protected $guard = 'appUser';

    protected $table = 'app_users';

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone_number' ,'date_of_birth', 'password', 'pin', 'qr_code'
    ];

    public function volunteer()
    {
        return $this->hasMany(Volunteer::class, 'user_id', 'id');
    }

    public function polls()
    {
        return $this->belongsToMany(Poll::class, 'users_polls', 'app_user_id', 'poll_id')
            ->withTimestamps()->withPivot('option_id');
    }

    public function notifications()
    {
        return $this->belongsToMany(Notification::class, 'users_notifications', 'user_id', 'notification_id')
            ->withPivot('viewed')->withTimestamps();
    }

    public function volunteers()
    {
        return $this->belongsToMany(Volunteer::class, 'users_volunteers', 'user_id', 'volunteer_id')
            ->withTimestamps();
    }

    public function news()
    {
        return $this->belongsToMany(News::class, 'users_news', 'user_id', 'news_id')->withTimestamps();
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class, 'user_id', 'id');
    }

    public function talks()
    {
        return $this->hasMany(TalkToUs::class, 'user_id', 'id');
    }

    public static $IMAGE_PATH="images/qrcode";

    public function getUser()
    {
        return collect([
            'id' => $this->id,
            'name' => $this->name,
            'member_id' => $this->member_id,
            'phone_number' => $this->phone_number,
            'rate' => $this->rate,
            'token' => $this->token,
            'verification_nb' => $this->verification_nb,
            'verified' => $this->verified,
            'player_id' => $this->player_id,
            'image' => $this->image,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'email' => $this->email,
            'date_of_birth' => $this->date_of_birth,
            'qr_code_image' => $this->qr_code,
        ]);
    }

}
