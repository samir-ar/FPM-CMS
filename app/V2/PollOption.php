<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Http\Traits\MyTranslationTrait;


class PollOption extends Model
{
    use SoftDeletes;
    use MyTranslationTrait;

    public $translatable = ['option'];

    // a member's written answer on an option that takes text: at most this many characters (app and server)
    const TEXT_MAX = 300;


    protected $table='polls_options';

    // What the app shows on the option. A text option may be saved WITHOUT a label; the member then sees a plain
    // "Other" so the row is never blank (the stored label stays empty).
    public function displayLabel($locale = 'ar')
    {
        $label = (string) $this->getTranslation('option', $locale);
        if ($label === '' && $this->allows_text) {
            return $locale === 'en' ? 'Other' : 'أخرى';
        }

        return $label;
    }

    public function poll()
    {
        return $this->belongsTo(Poll::class, 'poll_id', 'id');
    }

    public function answers()
    {
        return $this->hasMany(AppUserPoll::class, 'option_id', 'id');
    }
}
