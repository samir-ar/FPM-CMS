<?php

namespace App\V2;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Storage;

class User extends Authenticatable
{
    use Notifiable;

    protected $guard = 'admin';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // public function getAvatarAttribute($val)
    // {
    //     if($val)
    //         // return '/images/avatars/'.$val;
    //         return Storage::disk('s3')->url(env('AWS_BUCKET_PROJECT_NAME') . '/' . 'storage/' . 'images/avatars/' . $val);

    // }

    public function pages()
    {
        return $this->belongsToMany(Page::class, 'admins_pages', 'user_id', 'page_id');
    }

    public function hasPage($page_id)
    {
        return !$this->pages()->where('page_id', $page_id)->get()->isEmpty();
    }



}
