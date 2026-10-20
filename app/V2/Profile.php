<?php

namespace App\V2;

use App\User;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $fillable = ['name'];

    public function permissions()
    {
        return $this->hasMany(ProfilePermission::class);
    }

    public function admins()
    {
        return $this->hasMany(User::class);
    }

    // ['page_id' => 'view'|'full', ...] — handy for pre-filling the edit form.
    public function permissionMap(): array
    {
        return $this->permissions->pluck('level', 'page_id')->all();
    }
}
