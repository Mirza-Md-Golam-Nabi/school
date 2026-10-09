<?php

namespace App\Traits;

use App\Support\ActivityLogLabelCache;
use Closure;
use Illuminate\Database\Eloquent\Model;
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

                $value = $values[$column];

                $values["{$column}_label"] = is_scalar($value) || $value === null
                    ? static::cachedActivityLabel(static::class."|{$column}|".var_export($value, true), fn () => $resolver($value))
                    : $resolver($value);
            }

            $activity->properties = $activity->properties->put($propertyKey, $values);
        }
    }

    /**
     * Memoizes a label lookup for the current request, so logging many rows
     * that share the same related record resolves it only once.
     */
    protected static function cachedActivityLabel(string $key, Closure $resolver): mixed
    {
        return app(ActivityLogLabelCache::class)->remember($key, $resolver);
    }

    /**
     * The `name` of a related model, looked up once per request.
     *
     * @param  class-string<Model>  $modelClass
     */
    protected static function activityNameLabel(string $modelClass, int|string|null $id): ?string
    {
        if ($id === null) {
            return null;
        }

        return static::cachedActivityLabel("name|{$modelClass}|{$id}", fn (): ?string => $modelClass::find($id)?->name);
    }
}
