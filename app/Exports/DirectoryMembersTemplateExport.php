<?php

namespace App\Exports;

use App\V2\BusinessType;
use Maatwebsite\Excel\Concerns\FromArray;

class DirectoryMembersTemplateExport implements FromArray
{
    public function array(): array
    {
        $categories = BusinessType::orderBy('name')->pluck('name')->implode(' / ');

        return [
            ['الفئة', 'الاسم', 'الاختصاص', 'الهاتف', 'الدولة', 'المحافظة', 'القضاء', 'البلدة', 'رقم النقابة', 'الترتيب (اختياري)'],
            ["مثال: {$categories}", 'محمد الأمين', 'مهندس مدني', '+96170123456', 'لبنان', 'جبل لبنان', 'المتن', 'بعبدا', '12345', '1'],
        ];
    }
}
