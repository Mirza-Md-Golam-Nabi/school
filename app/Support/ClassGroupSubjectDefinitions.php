<?php

namespace App\Support;

use App\Enums\SubjectType;

/**
 * Default subject-to-group attachment mapping for the primary and secondary
 * levels, shared by the `attach:subject` artisan command. A `group`
 * of null means the subject applies to every group (and to non-grouped
 * classes, e.g. primary and Class 6-8, which have no groups at all).
 */
class ClassGroupSubjectDefinitions
{
    /**
     * The groups referenced by secondary()'s mapping, in a stable order —
     * used to create the Group rows and link them to Class 9-10.
     *
     * @var array<int, string>
     */
    public const SECONDARY_GROUPS = ['Science', 'Commerce', 'Humanities'];

    /**
     * Group => subject_type => subject names, for the secondary level's
     * grouped classes (Class 9-10 only — Class 6-8 have no groups).
     *
     * @return array<int, array{group: ?string, subject_type: SubjectType, subjects: array<int, string>}>
     */
    public static function secondary(): array
    {
        return [
            [
                'group' => null,
                'subject_type' => SubjectType::Compulsory,
                'subjects' => [
                    'Bangla 1st Paper',
                    'Bangla 2nd Paper',
                    'English 1st Paper',
                    'English 2nd Paper',
                    'Mathematics',
                    'Religious Studies',
                    'Information & Communication Technology',
                ],
            ],
            [
                'group' => 'Science',
                'subject_type' => SubjectType::Compulsory,
                'subjects' => [
                    'Physics',
                    'Chemistry',
                    'Bangladesh and Global Studies',
                ],
            ],
            [
                'group' => 'Science',
                'subject_type' => SubjectType::Optional,
                'subjects' => [
                    'Biology',
                    'Higher Mathematics',
                ],
            ],
            [
                'group' => 'Commerce',
                'subject_type' => SubjectType::Compulsory,
                'subjects' => [
                    'Accounting',
                    'Business Entrepreneurship',
                    'Finance and Banking',
                    'General Science',
                ],
            ],
            [
                'group' => 'Humanities',
                'subject_type' => SubjectType::Compulsory,
                'subjects' => [
                    'History of Bangladesh and World Civilization',
                    'Civics and Citizenship',
                    'Geography and Environment',
                    'General Science',
                ],
            ],
            [
                'group' => null,
                'subject_type' => SubjectType::Optional,
                'subjects' => [
                    'Agricultural Education',
                    'Home Science',
                    'Economics',
                ],
            ],
        ];
    }

    /**
     * Compulsory subjects for Class 6-8 — these classes have no groups, so
     * every subject applies to every student (group = null).
     *
     * @return array<int, array{group: ?string, subject_type: SubjectType, subjects: array<int, string>}>
     */
    public static function classSixToEight(): array
    {
        return [
            [
                'group' => null,
                'subject_type' => SubjectType::Compulsory,
                'subjects' => [
                    'Bangla 1st Paper',
                    'Bangla 2nd Paper',
                    'English 1st Paper',
                    'English 2nd Paper',
                    'Mathematics',
                    'Religious Studies',
                    'Information & Communication Technology',
                    'General Science',
                    'Agricultural Education',
                ],
            ],
        ];
    }

    /**
     * Compulsory subjects for the primary level (Class 1-5) — no groups
     * apply here either, so every subject applies to every student.
     *
     * @return array<int, array{group: ?string, subject_type: SubjectType, subjects: array<int, string>}>
     */
    public static function primary(): array
    {
        return [
            [
                'group' => null,
                'subject_type' => SubjectType::Compulsory,
                'subjects' => [
                    'Bangla',
                    'English',
                    'Mathematics',
                    'Bangladesh and Global Studies',
                    'Science',
                    'Religious Studies',
                ],
            ],
        ];
    }
}
