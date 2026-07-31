<?php

namespace App\Imports;

use App\V2\MunicipalityMember;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class MunicipalityMembersImport implements ToCollection
{
    private string $currentQada = '';
    private string $currentMunicipality = '';
    private string $currentVillage = '';
    private int $sortOrder = 0;

    private static array $validPositions = ['رئيس', 'نائب رئيس', 'عضو'];

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
        foreach ($rows as $index => $row) {
            $col1  = trim($row[0] ?? '');
            $col3  = trim($row[2] ?? '');
            $col5  = trim($row[4] ?? '');
            $col6  = trim($row[5] ?? '');  // عدد الأصوات
            $col7  = trim($row[6] ?? '');
            $col8  = trim($row[7] ?? '');
            $col10 = trim($row[9] ?? '');

            // Row 0 (Excel 1): governorate title — skip
            if ($index === 0) continue;

            // Row 1 (Excel 2): قضاء name — capture it, normalize to Flutter format
            if ($index === 1) {
                if ($col1 !== '') {
                    $this->currentQada = self::$qadaMap[$col1] ?? $col1;
                }
                continue;
            }

            // Row 2 (Excel 3): column headers — skip
            if ($index === 2) continue;

            // Mid-file قضاء section headers: col1 non-empty, col5 empty
            if ($col1 !== '' && $col5 === '') {
                $this->currentQada = $col1;
                $this->currentMunicipality = '';
                $this->currentVillage = '';
                continue;
            }

            // Skip rows with no member name
            if ($col5 === '') continue;

            // Update municipality if col1 has a value
            if ($col1 !== '') {
                $this->currentMunicipality = $col1;
                $this->sortOrder = 0;
            }

            // Update village if col3 has a value
            if ($col3 !== '') {
                $this->currentVillage = $col3;
            }

            // Clean up phone: treat #N/A as null
            $phone = ($col8 === '' || $col8 === '#N/A') ? null : $col8;

            // Validate position
            $position = in_array($col7, self::$validPositions) ? $col7 : 'عضو';

            // is_mountasib: true if col10 has any non-empty value
            $isMountasib = $col10 !== '';

            if ($this->currentMunicipality === '') continue;

            // Parse votes: strip Arabic thousands separator ٬ and commas
            $votes = (int) str_replace(['٬', ',', ' '], '', $col6);

            MunicipalityMember::create([
                'qada'              => $this->currentQada,
                'municipality_name' => $this->currentMunicipality,
                'village_name'      => $this->currentVillage ?: null,
                'full_name'         => $col5,
                'position'          => $position,
                'votes'             => $votes,
                'phone'             => $phone,
                'is_mountasib'      => $isMountasib,
                'sort_order'        => ++$this->sortOrder,
            ]);
        }
    }
}
