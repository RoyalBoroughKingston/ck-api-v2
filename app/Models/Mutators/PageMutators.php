<?php

namespace App\Models\Mutators;

trait PageMutators
{
    /**
     * Specify parent id attribute mutator.
     * @param mixed $value
     */
    public function setParentAttribute($value)
    {
        $this->setParentIdAttribute($value);
    }

    /**
     * Get the order attribute.
     *
     * @param type name
     * @return int
     * @author
     */
    public function getOrderAttribute()
    {
        return $this->prevSiblings()->count();
    }
}
