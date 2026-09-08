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
            ['name' => 'Bangla', 'code' => 'PRI-BAN'],
            ['name' => 'English', 'code' => 'PRI-ENG'],
            ['name' => 'Mathematics', 'code' => 'PRI-MAT'],
            ['name' => 'Bangladesh and Global Studies', 'code' => 'PRI-BGS'],
            ['name' => 'Science', 'code' => 'PRI-SCI'],
            ['name' => 'Religious Studies', 'code' => 'PRI-REL'],
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
                'code' => 'SEC-BAN1',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],
            [
                'name' => 'Bangla 2nd Paper',
                'code' => 'SEC-BAN2',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],
            [
                'name' => 'Mathematics',
                'code' => 'SEC-MAT',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],
            [
                'name' => 'Religious Studies',
                'code' => 'SEC-REL',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],
            // English: fully written, no MCQ and no practical.
            [
                'name' => 'English 1st Paper',
                'code' => 'SEC-ENG1',
                'has_written' => true,
                'has_mcq' => false,
                'has_practical' => false,
            ],
            [
                'name' => 'English 2nd Paper',
                'code' => 'SEC-ENG2',
                'has_written' => true,
                'has_mcq' => false,
                'has_practical' => false,
            ],
            // ICT: MCQ + practical only, no creative/written part.
            [
                'name' => 'Information & Communication Technology',
                'code' => 'SEC-ICT',
                'has_written' => false,
                'has_mcq' => true,
                'has_practical' => true,
            ],

            // Science group (optional) — compulsory subjects.
            [
                'name' => 'Physics',
                'code' => 'SEC-PHY',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => true,
            ],
            [
                'name' => 'Chemistry',
                'code' => 'SEC-CHE',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => true,
            ],
            [
                'name' => 'Bangladesh and Global Studies',
                'code' => 'SEC-BGS',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],
            // Science group — one taken as Main, the other as 4th subject (or swapped for Agricultural Education/Home Science).
            [
                'name' => 'Biology',
                'code' => 'SEC-BIO',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => true,
            ],
            [
                'name' => 'Higher Mathematics',
                'code' => 'SEC-HMT',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => true,
            ],

            // Business Studies group (optional) — compulsory subjects.
            [
                'name' => 'Accounting',
                'code' => 'SEC-ACC',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],
            [
                'name' => 'Business Entrepreneurship',
                'code' => 'SEC-BEN',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],
            [
                'name' => 'Finance and Banking',
                'code' => 'SEC-FIB',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],

            // Humanities group (optional) — compulsory subjects.
            [
                'name' => 'History of Bangladesh and World Civilization',
                'code' => 'SEC-HIS',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],
            [
                'name' => 'Civics and Citizenship',
                'code' => 'SEC-CIV',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],
            [
                'name' => 'Geography and Environment',
                'code' => 'SEC-GEO',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],

            // Shared compulsory subject for Business Studies and Humanities groups only.
            [
                'name' => 'General Science',
                'code' => 'SEC-GSC',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => false,
            ],

            // 4th subject options. Agricultural Education/Home Science are open to every
            // group; Economics is a 4th-subject choice for Business Studies/Humanities only.
            [
                'name' => 'Agricultural Education',
                'code' => 'SEC-AGR',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => true,
            ],
            [
                'name' => 'Home Science',
                'code' => 'SEC-HSC',
                'has_written' => true,
                'has_mcq' => true,
                'has_practical' => true,
            ],
            [
                'name' => 'Economics',
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
                'code' => 'COL-BAN',
            ],
            [
                'name' => 'English',
                'code' => 'COL-ENG',
            ],
            [
                'name' => 'ICT',
                'code' => 'COL-ICT',
                'has_practical' => true,
            ],

            // Science group
            [
                'name' => 'Physics',
                'code' => 'COL-PHY',
                'has_practical' => true,
            ],
            [
                'name' => 'Chemistry',
                'code' => 'COL-CHE',
                'has_practical' => true,
            ],
            [
                'name' => 'Biology',
                'code' => 'COL-BIO',
                'has_practical' => true,
            ],
            [
                'name' => 'Higher Mathematics',
                'code' => 'COL-HMT',
            ],

            // Humanities group
            [
                'name' => 'Civics and Good Governance',
                'code' => 'COL-CIV',
            ],
            [
                'name' => 'Economics',
                'code' => 'COL-ECO',
            ],
            [
                'name' => 'History',
                'code' => 'COL-HIS',
            ],
            [
                'name' => 'Islamic History and Culture',
                'code' => 'COL-IHC',
            ],
            [
                'name' => 'Logic',
                'code' => 'COL-LOG',
            ],
            [
                'name' => 'Sociology',
                'code' => 'COL-SOC',
            ],
            [
                'name' => 'Social Work',
                'code' => 'COL-SWK',
            ],
            [
                'name' => 'Geography',
                'code' => 'COL-GEO',
            ],
            [
                'name' => 'Statistics',
                'code' => 'COL-STA',
            ],
            [
                'name' => 'Home Management and Family Living',
                'code' => 'COL-HMF',
            ],

            // Business Studies group
            [
                'name' => 'Accounting',
                'code' => 'COL-ACC',
            ],
            [
                'name' => 'Business Organization and Management',
                'code' => 'COL-BOM',
            ],
            [
                'name' => 'Finance, Banking and Insurance',
                'code' => 'COL-FBI',
            ],
            [
                'name' => 'Production Management and Marketing',
                'code' => 'COL-PMM',
            ],
        ];
    }
}
