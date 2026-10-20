<?php

namespace App\V2;

use App\Page;
use Illuminate\Database\Eloquent\Model;

class ProfilePermission extends Model
{
    protected $fillable = ['profile_id', 'page_id', 'level', 'extra_actions'];

    protected $casts = ['extra_actions' => 'array'];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function page()
    {
        return $this->belongsTo(Page::class);
    }
}
