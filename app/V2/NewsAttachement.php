<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class NewsAttachement extends Model
{
   protected $fillable = ["name","news_id"];
   
    public function news(){
        return $this->belongsTo(News::class);
    }

}
