<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class CompetencyVacancy extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function nominations()
    {
        return $this->hasMany(CompetencyNomination::class, 'vacancy_id');
    }
}
