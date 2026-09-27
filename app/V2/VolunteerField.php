<?php

namespace App\V2;

use App\Http\Traits\MyTranslationTrait;
use Illuminate\Database\Eloquent\Model;

class VolunteerField extends Model
{
    use MyTranslationTrait;

    public $translatable = ['name'];

    public function volunteer()
    {
        return $this->belongsTo(Volunteer::class, 'volunteer_id', 'id');
    }
}
