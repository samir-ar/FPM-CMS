<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class NewsImage extends Model
{
    protected $fillable = ["name","news_id"];
    public function news(){
        return $this->belongsTo(News::class);
    }


    public function getNameAttribute($attr)
    {
        if(!$attr)
            return null;

        return rawurlencode($attr);
    }
}
