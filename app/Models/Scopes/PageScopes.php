<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;

trait PageScopes
{
    /**
     * Get the InformationPage sibling at given index.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $index
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSiblingAtIndex(Builder $query, $index): Builder
    {
        return $this->siblingsAndSelf()->defaultOrder()->offset($index)->limit(1);
    }
}
