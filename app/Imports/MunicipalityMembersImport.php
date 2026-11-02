<?php

namespace App\Imports;

use App\V2\MunicipalityMember;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

// Flat, one-row-per-member format: every row carries its own قضاء/بلدية/قرية
// directly, so a single file can hold every district at once and each row
// is self-contained - no more tracking "current قضاء" across sparse rows
// that only sometimes repeat it.
class MunicipalityMembersImport implements ToCollection
{
    private static array $validPositions = ['رئيس', 'نائب رئيس', 'عضو', 'متوفي'];

    // Maps Excel qada names → Flutter district names
    private static array $qadaMap = [
        'المتن الشمالي'     => 'قضاء المتن',
        'بعبدا'             => 'قضاء بعبدا',
        'عاليه'             => 'قضاء عاليه',
        'الشوف'             => 'قضاء الشوف',
        'كسروان'            => 'قضاء كسروان',
        'جبيل'              => 'قضاء جبيل',
        'طرابلس'            => 'قضاء طرابلس',
        'المنية–الضنية'     => 'قضاء المنية–الضنية',
        'زغرتا'             => 'قضاء زغرتا',
        'الكورة'            => 'قضاء الكورة',
        'البترون'           => 'قضاء البترون',
        'بشري'              => 'قضاء بشري',
        'عكار'              => 'قضاء عكار',
        'زحلة'              => 'قضاء زحلة',
        'البقاع الغربي'     => 'قضاء البقاع الغربي',
        'راشيا'             => 'قضاء راشيا',
        'بعلبك'             => 'قضاء بعلبك',
        'الهرمل'            => 'قضاء الهرمل',
        'صيدا'              => 'قضاء صيدا',
        'صور'               => 'قضاء صور',
        'جزين'              => 'قضاء جزين',
        'النبطية'           => 'قضاء النبطية',
        'بنت جبيل'          => 'قضاء بنت جبيل',
        'مرجعيون'           => 'قضاء مرجعيون',
        'حاصبيا'            => 'قضاء حاصبيا',
    ];

    public function collection(Collection $rows)
    {
        $this->deleteExistingQadas($rows);

        $lastQada = null;
        $lastMunicipality = null;
        $sortOrder = 0;

        foreach ($rows as $index => $row) {
            // Row 0 (Excel row 1): column headers — skip
            if ($index === 0) continue;

            $qadaRaw      = trim($row[0] ?? '');
            $municipality = trim($row[1] ?? '');
            $village      = trim($row[2] ?? '');
            $name         = trim($row[3] ?? '');
            $votesRaw     = trim($row[4] ?? '');
            $positionRaw  = trim($row[5] ?? '');
            $phoneRaw     = trim($row[6] ?? '');
            $mountasibRaw = trim($row[7] ?? '');

            // Skip fully blank rows (trailing empty rows at the end of a sheet, etc.)
            if ($qadaRaw === '' && $municipality === '' && $name === '') continue;

            // Every real data row must have at minimum a قضاء, بلدية, and a name —
            // skip anything incomplete rather than guessing/inheriting from a
            // previous row (the whole point of the flat format is that nothing
            // is implied from context).
            if ($qadaRaw === '' || $municipality === '' || $name === '') continue;

            $qada = self::$qadaMap[$qadaRaw] ?? $qadaRaw;

            // Sort order restarts per (قضاء, بلدية) pair, same as before — just
            // detected by comparing consecutive rows now, instead of resetting
            // on every "col1 non-empty" row.
            if ($qada !== $lastQada || $municipality !== $lastMunicipality) {
                $sortOrder = 0;
                $lastQada = $qada;
                $lastMunicipality = $municipality;
            }

            $phone = ($phoneRaw === '' || $phoneRaw === '#N/A') ? null : $phoneRaw;
            $position = in_array($positionRaw, self::$validPositions) ? $positionRaw : 'عضو';
            $isMountasib = $mountasibRaw !== '';

            // Strip Arabic thousands separator ٬ and commas
            $votes = (int) str_replace(['٬', ',', ' '], '', $votesRaw);

            MunicipalityMember::create([
                'qada'              => $qada,
                'municipality_name' => $municipality,
                'village_name'      => $village ?: null,
                'full_name'         => $name,
                'position'          => $position,
                'votes'             => $votes,
                'phone'             => $phone,
                'is_mountasib'      => $isMountasib,
                'sort_order'        => ++$sortOrder,
            ]);
        }
    }

    // Scans the file for every قضاء it references and deletes that قضاء's
    // existing rows before the real import pass inserts the new ones — so
    // re-uploading a file replaces those قضاء's data cleanly instead of
    // duplicating it, while قضاء's not present in this file are untouched.
    private function deleteExistingQadas(Collection $rows): void
    {
        $qadas = [];

        foreach ($rows as $index => $row) {
            if ($index === 0) continue;

            $qadaRaw = trim($row[0] ?? '');
            if ($qadaRaw === '') continue;

            $qada = self::$qadaMap[$qadaRaw] ?? $qadaRaw;
            $qadas[$qada] = true;
        }

        if (!empty($qadas)) {
            MunicipalityMember::whereIn('qada', array_keys($qadas))->delete();
        }
    }
}
