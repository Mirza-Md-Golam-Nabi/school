<?php

namespace App\Traits;

use Spatie\Activitylog\Contracts\Activity;

trait LogsRelationLabels
{
    /**
     * Map of logged foreign-key columns to a resolver that turns the raw ID
     * into a human-readable label, so activity log readers don't have to
     * cross-reference IDs manually. The resolver also receives `null` (e.g.
     * for an optional FK left unset) so it can return a meaningful label
     * like "All Groups" instead of being skipped.
     *
     * @return array<string, callable(int|string|null): (string|null)>
     */
    protected function activityLogRelationLabels(): array
    {
        return [];
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $resolvers = $this->activityLogRelationLabels();

        if ($resolvers === []) {
            return;
        }

        foreach (['attributes', 'old'] as $propertyKey) {
            $values = $activity->properties->get($propertyKey);

            if (! is_array($values)) {
                continue;
            }

            foreach ($resolvers as $column => $resolver) {
                if (! array_key_exists($column, $values)) {
                    continue;
                }

                $values["{$column}_label"] = $resolver($values[$column]);
            }

            $activity->properties = $activity->properties->put($propertyKey, $values);
        }
    }
}
