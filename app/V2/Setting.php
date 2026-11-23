<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded=['id'];

    // The phone picture of the public landing page: the one uploaded in the CMS, or the built-in default.
    public static function landingPhoneUrl(): string
    {
        try {
            $name = static::query()->value('landing_phone_image');
        } catch (\Throwable $e) {
            $name = null; // column not migrated yet
        }

        if (!$name) {
            return url('landing/img/app.svg');
        }

        $forceS3 = config('app.force_s3_storage') ?? (config('app.env') != 'local');

        return $forceS3
            ? \Illuminate\Support\Facades\Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/storage/landing/' . rawurlencode($name))
            : url('landing/' . rawurlencode($name));
    }

    protected $casts = [
        'bool_flag' => 'boolean',
        'force_update_ios' => 'boolean',
        'force_update_android' => 'boolean'
     ];
}