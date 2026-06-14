<?php

namespace Database\Seeders;

use App\Enums\CountMethod;
use App\Enums\ExamConfigType;
use App\Models\Classes;
use App\Models\ExamContributeRule;
use App\Models\ExamType;
use App\Models\ExamTypeConfig;
use Illuminate\Database\Seeder;

class ExamConfigSeeder extends Seeder
{
    /**
     * name => ExamTypeConfig values
     *
     * @var array<string, array{type: ExamConfigType, count_method: CountMethod|null, best_n_count: int|null}>
     */
    private array $examTypes = [
        'Half Yearly' => [
            'type' => ExamConfigType::Main,
            'count_method' => CountMethod::All,
            'best_n_count' => null,
        ],
        'Annual' => [
            'type' => ExamConfigType::Main,
            'count_method' => CountMethod::All,
            'best_n_count' => null,
        ],
        'Tutorial' => [
            'type' => ExamConfigType::Supporting,
            'count_method' => CountMethod::BestN,
            'best_n_count' => 3,
        ],
    ];

    /**
     * source => [target => contribution_percent]
     *
     * @var array<string, array<string, int>>
     */
    private array $contributeRules = [
        'Tutorial' => ['Half Yearly' => 20, 'Annual' => 20],
        'Half Yearly' => ['Annual' => 30],
    ];

    public function run(): void
    {
        $sessionYear = (int) now()->year;

        // 1. ExamType + ExamTypeConfig
        $examTypes = collect();

        foreach ($this->examTypes as $name => $config) {
            $examType = ExamType::firstOrCreate(
                ['name' => $name],
                ['is_active' => true]
            );

            ExamTypeConfig::firstOrCreate(
                ['exam_type_id' => $examType->id],
                [
                    'type' => $config['type'],
                    'count_method' => $config['count_method'],
                    'best_n_count' => $config['best_n_count'],
                ]
            );

            $examTypes->put($name, $examType);
        }

        // 2. ExamContributeRule — per class
        $classes = Classes::whereNull('deleted_at')->get();

        foreach ($classes as $class) {
            foreach ($this->contributeRules as $sourceName => $targets) {
                $source = $examTypes->get($sourceName);

                foreach ($targets as $targetName => $percent) {
                    $target = $examTypes->get($targetName);

                    ExamContributeRule::firstOrCreate(
                        [
                            'class_id' => $class->id,
                            'source_exam_type_id' => $source->id,
                            'target_exam_type_id' => $target->id,
                            'session_year' => $sessionYear,
                        ],
                        ['contribution_percent' => $percent]
                    );
                }
            }
        }
    }
}
