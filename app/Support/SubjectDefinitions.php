<?php

namespace App\Support;

/**
 * Centralized default subject list per level (primary/secondary/college),
 * shared by the `create:subject` artisan command and any seeder that needs
 * the same data — so the two never drift out of sync.
 */
class SubjectDefinitions
{
    /**
     * The general subjects for the primary level (Class 1-5).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function primary(): array
    {
        return [
            [
                'name' => 'Bangla',
                'name_bn' => 'বাংলা',
                'code' => 'PRI-BAN',
            ],
            [
                'name' => 'English',
                'name_bn' => 'ইংরেজি',
                'code' => 'PRI-ENG',
            ],
            [
                'name' => 'Mathematics',
                'name_bn' => 'গণিত',
                'code' => 'PRI-MAT',
            ],
            [
                'name' => 'Bangladesh and Global Studies',
                'name_bn' => 'বাংলাদেশ ও বিশ্বপরিচয়',
                'code' => 'PRI-BGS',
            ],
            [
                'name' => 'Science',
                'name_bn' => 'বিজ্ঞান',
                'code' => 'PRI-SCI',
            ],
            [
                'name' => 'Religious Studies',
                'name_bn' => 'ধর্ম শিক্ষা',
                'code' => 'PRI-REL',
            ],
        ];
    }

    /**
     * The subjects for the secondary level (Class 6-10 / SSC) — this
     * includes the subjects compulsory for every student, plus the optional
     * subjects for the Science/Humanities/Business Studies groups. Which
     * subject is compulsory or optional for a given class/group is assigned
     * later from that class's Subjects tab — this only lists the Subject
     * records themselves.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function secondary(): array
    {
        return [
            // Compulsory for every student, every group.
            // Bangla/Mathematics/Religious Studies/etc: MCQ + Creative (CQ), no practical.
            [
                'name' => 'Bangla 1st Paper',
                'name_bn' => 'বাংলা ১ম পত্র',
                'code' => 'SEC-BAN1',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],
            [
                'name' => 'Bangla 2nd Paper',
                'name_bn' => 'বাংলা ২য় পত্র',
                'code' => 'SEC-BAN2',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],
            [
                'name' => 'Mathematics',
                'name_bn' => 'গণিত',
                'code' => 'SEC-MAT',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],
            [
                'name' => 'Religious Studies',
                'name_bn' => 'ধর্ম শিক্ষা',
                'code' => 'SEC-REL',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],
            // English: fully written, no MCQ and no practical.
            [
                'name' => 'English 1st Paper',
                'name_bn' => 'ইংরেজি ১ম পত্র',
                'code' => 'SEC-ENG1',
                'has_written' => true,
                'has_mcq' => false,
                'has_practical' => false,
            ],
            [
                'name' => 'English 2nd Paper',
                'name_bn' => 'ইংরেজি ২য় পত্র',
                'code' => 'SEC-ENG2',
                'has_written' => true,
                'has_mcq' => false,
                'has_practical' => false,
            ],
            // ICT: MCQ + practical only, no creative/written part.
            [
                'name' => 'Information & Communication Technology',
                'name_bn' => 'তথ্য ও যোগাযোগ প্রযুক্তি',
                'code' => 'SEC-ICT',
                'has_written' => false,
                'has_mcq' => true,
                'has_practical' => true,
            ],

            // Science group (optional) — compulsory subjects.
            [
                'name' => 'Physics',
                'name_bn' => 'পদার্থবিজ্ঞান',
                'code' => 'SEC-PHY',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => true,
            ],
            [
                'name' => 'Chemistry',
                'name_bn' => 'রসায়ন',
                'code' => 'SEC-CHE',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => true,
            ],
            [
                'name' => 'Bangladesh and Global Studies',
                'name_bn' => 'বাংলাদেশ ও বিশ্বপরিচয়',
                'code' => 'SEC-BGS',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],
            // Science group — one taken as Main, the other as 4th subject (or swapped for Agricultural Education/Home Science).
            [
                'name' => 'Biology',
                'name_bn' => 'জীববিজ্ঞান',
                'code' => 'SEC-BIO',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => true,
            ],
            [
                'name' => 'Higher Mathematics',
                'name_bn' => 'উচ্চতর গণিত',
                'code' => 'SEC-HMT',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => true,
            ],

            // Business Studies group (optional) — compulsory subjects.
            [
                'name' => 'Accounting',
                'name_bn' => 'হিসাববিজ্ঞান',
                'code' => 'SEC-ACC',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],
            [
                'name' => 'Business Entrepreneurship',
                'name_bn' => 'ব্যবসায় উদ্যোগ',
                'code' => 'SEC-BEN',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],
            [
                'name' => 'Finance and Banking',
                'name_bn' => 'ফিন্যান্স ও ব্যাংকিং',
                'code' => 'SEC-FIB',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],

            // Humanities group (optional) — compulsory subjects.
            [
                'name' => 'History of Bangladesh and World Civilization',
                'name_bn' => 'বাংলাদেশের ইতিহাস ও বিশ্বসভ্যতা',
                'code' => 'SEC-HIS',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],
            [
                'name' => 'Civics and Citizenship',
                'name_bn' => 'পৌরনীতি ও নাগরিকতা',
                'code' => 'SEC-CIV',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],
            [
                'name' => 'Geography and Environment',
                'name_bn' => 'ভূগোল ও পরিবেশ',
                'code' => 'SEC-GEO',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],

            // Shared compulsory subject for Business Studies and Humanities groups only.
            [
                'name' => 'General Science',
                'name_bn' => 'সাধারণ বিজ্ঞান',
                'code' => 'SEC-GSC',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],

            // 4th subject options. Agricultural Education/Home Science are open to every
            // group; Economics is a 4th-subject choice for Business Studies/Humanities only.
            [
                'name' => 'Agricultural Education',
                'name_bn' => 'কৃষিশিক্ষা',
                'code' => 'SEC-AGR',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => true,
            ],
            [
                'name' => 'Home Science',
                'name_bn' => 'গার্হস্থ্য বিজ্ঞান',
                'code' => 'SEC-HSC',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => true,
            ],
            [
                'name' => 'Economics',
                'name_bn' => 'অর্থনীতি',
                'code' => 'SEC-ECO',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],
        ];
    }

    /**
     * The subjects for the college level (Class 11-12 / HSC) — this
     * includes the compulsory subjects plus the optional subjects for the
     * Science/Humanities/Business Studies groups.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function college(): array
    {
        return [
            // Compulsory for every group
            [
                'name' => 'Bangla',
                'name_bn' => 'বাংলা',
                'code' => 'COL-BAN',
            ],
            [
                'name' => 'English',
                'name_bn' => 'ইংরেজি',
                'code' => 'COL-ENG',
            ],
            [
                'name' => 'ICT',
                'name_bn' => 'তথ্য ও যোগাযোগ প্রযুক্তি',
                'code' => 'COL-ICT',
                'has_practical' => true,
            ],

            // Science group
            [
                'name' => 'Physics',
                'name_bn' => 'পদার্থবিজ্ঞান',
                'code' => 'COL-PHY',
                'has_practical' => true,
            ],
            [
                'name' => 'Chemistry',
                'name_bn' => 'রসায়ন',
                'code' => 'COL-CHE',
                'has_practical' => true,
            ],
            [
                'name' => 'Biology',
                'name_bn' => 'জীববিজ্ঞান',
                'code' => 'COL-BIO',
                'has_practical' => true,
            ],
            [
                'name' => 'Higher Mathematics',
                'name_bn' => 'উচ্চতর গণিত',
                'code' => 'COL-HMT',
                'has_practical' => true,
            ],

            // Humanities group
            [
                'name' => 'Civics and Good Governance',
                'name_bn' => 'পৌরনীতি ও সুশাসন',
                'code' => 'COL-CIV',
            ],
            [
                'name' => 'Economics',
                'name_bn' => 'অর্থনীতি',
                'code' => 'COL-ECO',
            ],
            [
                'name' => 'History',
                'name_bn' => 'ইতিহাস',
                'code' => 'COL-HIS',
            ],
            [
                'name' => 'Islamic History and Culture',
                'name_bn' => 'ইসলামের ইতিহাস ও সংস্কৃতি',
                'code' => 'COL-IHC',
            ],
            [
                'name' => 'Logic',
                'name_bn' => 'যুক্তিবিদ্যা',
                'code' => 'COL-LOG',
            ],
            [
                'name' => 'Sociology',
                'name_bn' => 'সমাজবিজ্ঞান',
                'code' => 'COL-SOC',
            ],
            [
                'name' => 'Social Work',
                'name_bn' => 'সমাজকর্ম',
                'code' => 'COL-SWK',
            ],
            [
                'name' => 'Geography',
                'name_bn' => 'ভূগোল',
                'code' => 'COL-GEO',
            ],
            [
                'name' => 'Statistics',
                'name_bn' => 'পরিসংখ্যান',
                'code' => 'COL-STA',
            ],
            [
                'name' => 'Home Management and Family Living',
                'name_bn' => 'গৃহ ব্যবস্থাপনা ও পারিবারিক জীবন',
                'code' => 'COL-HMF',
            ],

            // Business Studies group
            [
                'name' => 'Accounting',
                'name_bn' => 'হিসাববিজ্ঞান',
                'code' => 'COL-ACC',
            ],
            [
                'name' => 'Business Organization and Management',
                'name_bn' => 'ব্যবসায় সংগঠন ও ব্যবস্থাপনা',
                'code' => 'COL-BOM',
            ],
            [
                'name' => 'Finance, Banking and Insurance',
                'name_bn' => 'ফিন্যান্স, ব্যাংকিং ও বিমা',
                'code' => 'COL-FBI',
            ],
            [
                'name' => 'Production Management and Marketing',
                'name_bn' => 'উৎপাদন ব্যবস্থাপনা ও বিপণন',
                'code' => 'COL-PMM',
            ],
        ];
    }
}
