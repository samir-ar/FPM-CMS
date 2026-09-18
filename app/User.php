<?php

namespace App;

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

    public function getAvatarAttribute($val)
    {
        if($val)
            // return '/images/avatars/'.$val;
            return Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/' . 'storage/' . 'images/avatars/' . $val);

    }

    public function pages()
    {
        return $this->belongsToMany(Page::class, 'admins_pages', 'user_id', 'page_id');
    }

    public function profile()
    {
        return $this->belongsTo(\App\V2\Profile::class);
    }

    // 'full', 'view', or null (no access at all). Top-level pages only —
    // a child page's visibility is controlled by its parent's grant, same
    // as the old admins_pages system.
    public function permissionLevel($page_id): ?string
    {
        if (!$this->profile_id) {
            return null;
        }

        return \App\V2\ProfilePermission::where('profile_id', $this->profile_id)
            ->where('page_id', $page_id)
            ->value('level');
    }

    // Kept for the sidebar (layouts/cms.blade.php still calls this per page)
    // — now backed by the profile system instead of the old admins_pages
    // pivot. Any granted level (view or full) means the page is visible.
    public function hasPage($page_id)
    {
        return $this->permissionLevel($page_id) !== null;
    }
}
