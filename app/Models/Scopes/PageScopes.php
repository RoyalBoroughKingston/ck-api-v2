<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;

trait PageScopes
{
    /**
     * Get the InformationPage sibling at given index.
     *
     * @param  int  $index
     */
    public function scopeSiblingAtIndex(Builder $query, $index): Builder
    {
        return $this->siblingsAndSelf()->defaultOrder()->offset($index)->limit(1);
    }

    /**
     * Get the Landing Page descendants.
     *
     * @param  string  $uuid
     */
    public function scopePageDescendants(Builder $query, $uuid): Builder
    {
        $descendantIds = static::descendantsOf($uuid)->pluck('id');

        return $query->whereIn('id', $descendantIds)->orderBy('_lft');
    }
}
