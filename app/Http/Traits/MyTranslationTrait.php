<?php


namespace App\Http\Traits;

use Spatie\Translatable\HasTranslations;

trait MyTranslationTrait
{
    use HasTranslations;

    public function asJson($translations)
    {
        return json_encode($translations, JSON_UNESCAPED_UNICODE);
    }
}