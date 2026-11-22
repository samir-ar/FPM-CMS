<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

// The round icon shown on a Home card, set per card type from the CMS ("Home card icons"). Read for every Home card, so
// the three rows are loaded once per request. A missing table (migration not run yet) simply means "no icons set".
class HomeCardIcon extends Model
{
    protected $table = 'home_card_icons';
    protected $guarded = ['id'];
    protected $casts = ['use_for_all' => 'boolean'];

    const TYPES = ['NEWS' => 'News', 'EVENT' => 'Events', 'POLL' => 'Polls'];
    const DIR = 'images/home_icons';

    private static ?array $loaded = null;

    public static function forget(): void
    {
        self::$loaded = null;
    }

    public static function row(string $type): ?self
    {
        if (self::$loaded === null) {
            try {
                self::$loaded = Schema::hasTable('home_card_icons') ? self::all()->keyBy('type')->all() : [];
            } catch (\Throwable $e) {
                self::$loaded = [];
            }
        }

        return self::$loaded[$type] ?? null;
    }

    public function url(): ?string
    {
        return $this->image
            ? Storage::disk('s3')->url(config('app.aws_bucket_project_name') . '/storage/' . self::DIR . '/' . \App\Support\StorageUrl::fileName($this->image))
            : null;
    }

    // The icon URL for a card of this type, or null when none is set.
    public static function urlFor(string $type): ?string
    {
        $row = self::row($type);

        return $row ? $row->url() : null;
    }

    public static function forcedForType(string $type): bool
    {
        $row = self::row($type);

        return $row && $row->image && $row->use_for_all;
    }
}
