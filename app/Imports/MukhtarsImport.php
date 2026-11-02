<?php

namespace App\Imports;

use App\V2\Mukhtar;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

// Flat, one-row-per-member format: every row carries its own قضاء/قرية
// directly, so a single file can hold every district at once and each row
// is self-contained - no more tracking "current قضاء/قرية" across sparse
// rows that only sometimes repeat it. Mirrors MunicipalityMembersImport.
class MukhtarsImport implements ToCollection
{
    private static array $validPositions = ['مختار', 'عضو اختياري', 'متوفي'];

    private static array $qadaMap = [
        'المتن الشمالي'  => 'قضاء المتن',
        'بعبدا'          => 'قضاء بعبدا',
        'عاليه'          => 'قضاء عاليه',
        'الشوف'          => 'قضاء الشوف',
        'كسروان'         => 'قضاء كسروان',
        'جبيل'           => 'قضاء جبيل',
        'طرابلس'         => 'قضاء طرابلس',
        'المنية–الضنية'  => 'قضاء المنية–الضنية',
        'زغرتا'          => 'قضاء زغرتا',
        'الكورة'         => 'قضاء الكورة',
        'البترون'        => 'قضاء البترون',
        'بشري'           => 'قضاء بشري',
        'عكار'           => 'قضاء عكار',
        'زحلة'           => 'قضاء زحلة',
        'البقاع الغربي'  => 'قضاء البقاع الغربي',
        'راشيا'          => 'قضاء راشيا',
        'بعلبك'          => 'قضاء بعلبك',
        'الهرمل'         => 'قضاء الهرمل',
        'صيدا'           => 'قضاء صيدا',
        'صور'            => 'قضاء صور',
        'جزين'           => 'قضاء جزين',
        'النبطية'        => 'قضاء النبطية',
        'بنت جبيل'       => 'قضاء بنت جبيل',
        'مرجعيون'        => 'قضاء مرجعيون',
        'حاصبيا'         => 'قضاء حاصبيا',
    ];

    public function collection(Collection $rows)
    {
        $this->deleteExistingQadas($rows);

        $lastQada = null;
        $lastVillage = null;
        $sortOrder = 0;

        foreach ($rows as $index => $row) {
            // Row 0 (Excel row 1): column headers — skip
            if ($index === 0) continue;

            $qadaRaw      = trim($row[0] ?? '');
            $village      = trim($row[1] ?? '');
            $neighborhood = trim($row[2] ?? '');
            $name         = trim($row[3] ?? '');
            $votesRaw     = trim($row[4] ?? '');
            $positionRaw  = trim($row[5] ?? '');
            $phoneRaw     = trim($row[6] ?? '');
            $mountasibRaw = trim($row[7] ?? '');

            // Skip fully blank rows
            if ($qadaRaw === '' && $village === '' && $name === '') continue;

            // Every real data row must have at minimum a قضاء, قرية, and a
            // name — skip anything incomplete rather than inheriting from a
            // previous row.
            if ($qadaRaw === '' || $village === '' || $name === '') continue;

            $qada = self::$qadaMap[$qadaRaw] ?? $qadaRaw;

            // Sort order restarts per (قضاء, قرية) pair
            if ($qada !== $lastQada || $village !== $lastVillage) {
                $sortOrder = 0;
                $lastQada = $qada;
                $lastVillage = $village;
            }

            $position = in_array($positionRaw, self::$validPositions) ? $positionRaw : 'مختار';
            $phone = ($phoneRaw === '' || $phoneRaw === '#N/A') ? null : $phoneRaw;
            $isMountasib = $mountasibRaw !== '';
            $votes = (int) str_replace(['٬', ',', ' '], '', $votesRaw);

            Mukhtar::create([
                'qada'         => $qada,
                'village_name' => $village,
                'neighborhood' => $neighborhood ?: null,
                'full_name'    => $name,
                'position'     => $position,
                'votes'        => $votes,
                'phone'        => $phone,
                'is_mountasib' => $isMountasib,
                'sort_order'   => ++$sortOrder,
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
            Mukhtar::whereIn('qada', array_keys($qadas))->delete();
        }
    }
}
