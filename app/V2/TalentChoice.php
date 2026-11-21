<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

// One value of a FPM Talent drop-down list, edited in the CMS ("Talent Choices").
class TalentChoice extends Model
{
    protected $table = 'talent_choices';
    protected $guarded = ['id'];
    protected $casts = ['is_active' => 'boolean'];

    const GROUPS = [
        'fpm_relation' => 'Relation of the named person to the party (naming form)',
        'person_relation' => 'Relation between the person who names and the named person (naming form)',
        'education' => 'Education level (self-interest form)',
    ];

    // the active names of a list, in order
    public static function names(string $group): array
    {
        return self::where('group', $group)->where('is_active', true)->orderBy('sort_order')->orderBy('id')->pluck('name')->all();
    }
}
