<?php

namespace App\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

// One row of a FPM Talent form (a section title, a question, or a special block). The CMS edits them (add, remove, reorder,
// rename, required / hidden) and the app draws the form from them. See the migration 2026_11_25_120000 for the columns.
class TalentFormItem extends Model
{
    protected $table = 'talent_form_items';
    protected $guarded = ['id'];
    protected $casts = [
        'required' => 'boolean',
        'is_active' => 'boolean',
        'is_builtin' => 'boolean',
        'is_locked' => 'boolean',
    ];

    // the answer of a built-in question is kept in its own column of talent_submissions
    const BUILTIN_FIELDS = [
        'nominee_name', 'profession', 'relation_to_fpm', 'relationship_between', 'reasons', 'strengths',
        'education_level', 'academic', 'professional', 'organizational', 'interests', 'personal_info', 'cover_letter',
    ];

    // the kinds of block that are not a plain question
    const SPECIAL = ['roles', 'location', 'cv', 'docs', 'guest'];

    /** Every row of a track, in order (hidden ones too: the CMS editor shows them). */
    public static function ordered(string $track): Collection
    {
        return static::where('track', $track)->orderBy('sort_order')->orderBy('id')->get();
    }

    /** What one audience sees: the active rows meant for members (or for guests), in order. */
    public static function activeFor(string $track, bool $isMember): Collection
    {
        return static::ordered($track)
            ->filter(fn (self $i) => $i->is_active && ($i->audience === 'all' || $i->audience === ($isMember ? 'member' : 'guest')))
            ->values();
    }

    /** The choices of a dropdown added in the CMS (one per line). */
    public function optionList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $this->options) ?: [])));
    }

    public function isCustom(): bool
    {
        return $this->kind === 'field' && !$this->is_builtin;
    }

    /** What the app receives for this row. */
    public function toApi(): array
    {
        switch ($this->kind) {
            case 'section':
                return ['type' => 'section', 'title' => (string) $this->label];
            case 'roles':
                return ['type' => 'roles', 'hint' => $this->hint, 'required' => true];
            case 'location':
                return ['type' => 'location', 'district_label' => $this->label, 'town_label' => $this->hint, 'required' => (bool) $this->required];
            case 'cv':
                return ['type' => 'cv', 'label' => $this->label, 'required' => (bool) $this->required];
            case 'docs':
                return ['type' => 'docs', 'label' => $this->label];
            case 'guest':
                return ['type' => 'guest', 'title' => $this->label];
            default:
                return [
                    'type' => 'field',
                    'key' => $this->key,
                    'label' => (string) $this->label,
                    'hint' => $this->hint,
                    'input' => $this->input,
                    'required' => (bool) $this->required,
                    'lines' => (int) $this->lines,
                    'choices' => $this->choices_key,
                    'options' => $this->input === 'dropdown' && !$this->choices_key ? $this->optionList() : null,
                    'builtin' => (bool) $this->is_builtin,
                ];
        }
    }
}
