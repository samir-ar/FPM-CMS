<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    public function users()
    {
        return $this->belongsToMany(User::class, 'admins_pages', 'page_id', 'user_id');
    }

    public function children()
    {
        return $this->hasMany(Page::class, 'parent_id', 'id');
    }

    public function hasChilds()
    {
        return Page::where('parent_id', $this->id)->count();
    }
}
