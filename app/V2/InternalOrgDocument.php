<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class InternalOrgDocument extends Model
{
    protected $table = 'internal_org_documents';

    protected $fillable = ['tab', 'content'];

    public static array $tabs = [
        'nizham_dakhili'     => 'النظام الداخلي',
        'tawjihat_tatbiqiya' => 'التوجيهات التطبيقية',
    ];
}
