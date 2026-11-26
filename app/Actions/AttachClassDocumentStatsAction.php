<?php

namespace App\Actions;

use App\Actions\Concerns\GroupsRowsByStudentClass;
use App\Models\AdmitCard;
use App\Models\Classes;
use App\Models\Marksheet;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class AttachClassDocumentStatsAction
{
    use GroupsRowsByStudentClass;

    /**
     * Count each class's admit cards or marksheets as generated/pending in one
     * grouped query, and attach them as {$statsAttribute} (a collection with
     * only the non-zero 'generated'/'pending' keys), {$totalAttribute} and
     * pending_count.
     *
     * @param  Collection<int, Classes>  $classes
     * @param  class-string<AdmitCard|Marksheet>  $documentClass
     * @return Collection<int, Classes>
     */
    public function handle(Collection $classes, string $documentClass, string $statsAttribute, string $totalAttribute): Collection
    {
        $countsByClass = $this->forStudentsInClasses(
            $documentClass::query()->selectRaw('is_generated, COUNT(*) as count'),
            $classes->modelKeys(),
        )
            ->groupBy('class_id', 'is_generated')
            ->get()
            ->groupBy('class_id');

        return $classes->each(function (Classes $class) use ($countsByClass, $statsAttribute, $totalAttribute): void {
            /** @var SupportCollection<string, int> $stats */
            $stats = $countsByClass->get($class->id, collect())
                ->mapWithKeys(fn (AdmitCard|Marksheet $row): array => [
                    ($row->is_generated ? 'generated' : 'pending') => (int) $row->count,
                ]);

            $class->{$statsAttribute} = $stats;
            $class->{$totalAttribute} = $stats->sum();
            $class->pending_count = $stats->get('pending', 0);
        });
    }
}
