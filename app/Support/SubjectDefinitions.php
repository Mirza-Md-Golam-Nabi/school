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
            ['name' => 'Science', 'code' => 'PRI-SCI', 'has_practical' => true],
            ['name' => 'Religious Studies', 'code' => 'PRI-REL'],
            ['name' => 'ICT', 'code' => 'PRI-ICT', 'has_practical' => true],
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
            // Compulsory for every group
            ['name' => 'Bangla', 'code' => 'SEC-BAN'],
            ['name' => 'English', 'code' => 'SEC-ENG'],
            ['name' => 'Mathematics', 'code' => 'SEC-MAT'],
            ['name' => 'Science', 'code' => 'SEC-SCI', 'has_practical' => true],
            ['name' => 'ICT', 'code' => 'SEC-ICT', 'has_practical' => true],
            ['name' => 'Bangladesh and Global Studies', 'code' => 'SEC-BGS'],
            ['name' => 'Religious Studies', 'code' => 'SEC-REL'],
            ['name' => 'Physical Education and Health', 'code' => 'SEC-PEH', 'has_mcq' => false],
            ['name' => 'Career Education', 'code' => 'SEC-CAR', 'has_mcq' => false],

            // Science group (optional)
            ['name' => 'Physics', 'code' => 'SEC-PHY', 'has_practical' => true],
            ['name' => 'Chemistry', 'code' => 'SEC-CHE', 'has_practical' => true],
            ['name' => 'Biology', 'code' => 'SEC-BIO', 'has_practical' => true],
            ['name' => 'Higher Mathematics', 'code' => 'SEC-HMT'],

            // Humanities group (optional)
            ['name' => 'History of Bangladesh and World Civilization', 'code' => 'SEC-HIS'],
            ['name' => 'Civics and Good Governance', 'code' => 'SEC-CIV'],
            ['name' => 'Economics', 'code' => 'SEC-ECO'],
            ['name' => 'Geography and Environment', 'code' => 'SEC-GEO'],

            // Business Studies group (optional)
            ['name' => 'Accounting', 'code' => 'SEC-ACC'],
            ['name' => 'Business Entrepreneurship', 'code' => 'SEC-BEN'],
            ['name' => 'Finance and Banking', 'code' => 'SEC-FIB'],
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
            ['name' => 'Bangla', 'code' => 'COL-BAN'],
            ['name' => 'English', 'code' => 'COL-ENG'],
            ['name' => 'ICT', 'code' => 'COL-ICT', 'has_practical' => true],

            // Science group
            ['name' => 'Physics', 'code' => 'COL-PHY', 'has_practical' => true],
            ['name' => 'Chemistry', 'code' => 'COL-CHE', 'has_practical' => true],
            ['name' => 'Biology', 'code' => 'COL-BIO', 'has_practical' => true],
            ['name' => 'Higher Mathematics', 'code' => 'COL-HMT'],

            // Humanities group
            ['name' => 'Civics and Good Governance', 'code' => 'COL-CIV'],
            ['name' => 'Economics', 'code' => 'COL-ECO'],
            ['name' => 'History', 'code' => 'COL-HIS'],
            ['name' => 'Islamic History and Culture', 'code' => 'COL-IHC'],
            ['name' => 'Logic', 'code' => 'COL-LOG'],
            ['name' => 'Sociology', 'code' => 'COL-SOC'],
            ['name' => 'Social Work', 'code' => 'COL-SWK'],
            ['name' => 'Geography', 'code' => 'COL-GEO'],
            ['name' => 'Statistics', 'code' => 'COL-STA'],
            ['name' => 'Home Management and Family Living', 'code' => 'COL-HMF'],

            // Business Studies group
            ['name' => 'Accounting', 'code' => 'COL-ACC'],
            ['name' => 'Business Organization and Management', 'code' => 'COL-BOM'],
            ['name' => 'Finance, Banking and Insurance', 'code' => 'COL-FBI'],
            ['name' => 'Production Management and Marketing', 'code' => 'COL-PMM'],
        ];
    }
}
