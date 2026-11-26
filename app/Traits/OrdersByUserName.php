<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * For profile models that hold a user_id (teacher, staff ...): sorts their
 * rows by the linked user's name in the database, then by id so rows with the
 * same name keep a stable order.
 */
trait OrdersByUserName
{
    public function scopeOrderedByUserName(Builder $query): Builder
    {
        return $query
            ->orderBy(
                User::query()
                    ->select('name')
                    ->whereColumn('users.id', $this->qualifyColumn('user_id'))
                    ->limit(1)
            )
            ->orderBy($this->qualifyColumn('id'));
    }
}
