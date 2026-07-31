<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class CompetencyNomination extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'nominee_aware' => 'boolean',
        'responsibility_confirmed' => 'boolean',
    ];

    public function vacancy()
    {
        return $this->belongsTo(CompetencyVacancy::class, 'vacancy_id');
    }

    public function submittedBy()
    {
        return $this->belongsTo(AppUser::class, 'submitted_by_user_id');
    }
}
