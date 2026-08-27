<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class CompetencyVacancy extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
    ];

    public static $types = [
        'specific' => 'منصب محدد',
        'general'  => 'منصب عام',
    ];

    public function nominations()
    {
        return $this->hasMany(CompetencyNomination::class, 'vacancy_id');
    }
}
