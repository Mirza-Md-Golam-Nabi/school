<?php

namespace App\Actions\QuestionBank;

use App\Models\Classes;
use App\Models\Section;

class BuildQuestionBankClassListAction
{
    /**
     * Question Bank-এ স্কুলের পরীক্ষার ফর্মে যে ক্লাস ও সেকশন দেখাবে: চালু ক্লাসগুলো
     * ক্রম অনুযায়ী, প্রতিটার চালু সেকশনসহ (ক্লাসে সেকশন না থাকলে ফাঁকা)। নাম ইংরেজি ও বাংলা দুটোই,
     * যাতে Question Bank দর্শকের ভাষায় দেখাতে পারে।
     *
     * @return list<array{id: int, name: string, name_bn: ?string, sections: list<array{id: int, name: string}>}>
     */
    public function handle(): array
    {
        return Classes::query()
            ->active()
            ->with(['sections' => fn ($sections) => $sections->active()->orderBy('name')])
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->map(fn (Classes $class): array => [
                'id' => $class->id,
                'name' => $class->name,
                'name_bn' => $class->name_bn,
                'sections' => $class->has_section
                    ? $class->sections->map(fn (Section $section): array => ['id' => $section->id, 'name' => $section->name])->values()->all()
                    : [],
            ])
            ->values()
            ->all();
    }
}
