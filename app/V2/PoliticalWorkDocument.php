<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PoliticalWorkDocument extends Model
{
    protected $fillable = ['category', 'title', 'file_name', 'document_date'];

    protected $casts = ['document_date' => 'date'];

    public static array $categories = [
        'takattol'       => 'تكتل لبنان القوي',
        'haya_siyasiya'  => 'الهيئة السياسية',
        'majlis_siyasi'  => 'المجلس السياسي',
    ];

    public function fileUrl(): ?string
    {
        if (!$this->file_name) {
            return null;
        }

        $forceS3 = config('app.force_s3_storage') ?? (config('app.env') != 'local');

        return $forceS3
            ? Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/storage/political_work/' . $this->file_name)
            : url('political_work/' . $this->file_name);
    }
}
