<?php

namespace App\Imports;

use App\V2\Mukhtar;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class MukhtarsImport implements ToCollection
{
    private string $currentQada = '';
    private string $currentVillage = '';
    private int $sortOrder = 0;

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

        foreach ($rows as $index => $row) {
            $col1 = trim($row[0] ?? ''); // village OR section header
            $col2 = trim($row[1] ?? ''); // neighborhood
            $col3 = trim($row[2] ?? ''); // full name
            $col4 = trim($row[3] ?? ''); // votes (عدد الأصوات)
            $col5 = trim($row[4] ?? ''); // position (مرشح عن)
            $col6 = trim($row[5] ?? ''); // phone (الجوال)
            $col8 = trim($row[7] ?? ''); // رقم الانتساب

            // Row 0: governorate title — skip
            if ($index === 0) continue;

            // Row 1: قضاء name — capture
            if ($index === 1) {
                if ($col1 !== '') {
                    $this->currentQada = self::$qadaMap[$col1] ?? $col1;
                }
                continue;
            }

            // Row 2: header row — skip
            if ($index === 2) continue;

            // Mid-file qada section header: col1 non-empty, col3 empty, col5 empty
            if ($col1 !== '' && $col3 === '' && $col5 === '') {
                $this->currentQada = self::$qadaMap[$col1] ?? $col1;
                $this->currentVillage = '';
                continue;
            }

            // Skip rows with no name
            if ($col3 === '') continue;

            // Update village when col1 has value
            if ($col1 !== '') {
                $this->currentVillage = $col1;
                $this->sortOrder = 0;
            }

            if ($this->currentVillage === '') continue;

            // Validate position
            $position = in_array($col5, self::$validPositions) ? $col5 : 'مختار';

            // Phone: null if empty or #N/A
            $phone = ($col6 === '' || $col6 === '#N/A') ? null : $col6;

            // is_mountasib: true if رقم الانتساب is non-empty
            $isMountasib = $col8 !== '';

            $votes = (int) str_replace(['٬', ',', ' '], '', $col4);

            Mukhtar::create([
                'qada'         => $this->currentQada,
                'village_name' => $this->currentVillage,
                'neighborhood' => $col2 ?: null,
                'full_name'    => $col3,
                'position'     => $position,
                'votes'        => $votes,
                'phone'        => $phone,
                'is_mountasib' => $isMountasib,
                'sort_order'   => ++$this->sortOrder,
            ]);
        }
    }

    // Scans the file for every قضاء it references (row 2 + any mid-file
    // section headers) and deletes that قضاء's existing rows before the
    // real import pass inserts the new ones — so re-uploading an updated
    // file for a قضاء that's already in the DB replaces it cleanly instead
    // of duplicating it, while قضاء's not present in this file are
    // untouched.
    private function deleteExistingQadas(Collection $rows): void
    {
        $qadas = [];
        $current = '';

        foreach ($rows as $index => $row) {
            $col1 = trim($row[0] ?? '');
            $col3 = trim($row[2] ?? '');
            $col5 = trim($row[4] ?? '');

            if ($index === 1) {
                if ($col1 !== '') {
                    $current = self::$qadaMap[$col1] ?? $col1;
                }
            } elseif ($index > 1 && $col1 !== '' && $col3 === '' && $col5 === '') {
                $current = self::$qadaMap[$col1] ?? $col1;
            } else {
                continue;
            }

            if ($current !== '') {
                $qadas[$current] = true;
            }
        }

        if (!empty($qadas)) {
            Mukhtar::whereIn('qada', array_keys($qadas))->delete();
        }
    }
}
