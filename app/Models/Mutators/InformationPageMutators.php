<?php

namespace App\Models\Mutators;

trait InformationPageMutators
{
    /**
     * Specify parent id attribute mutator
     */
    public function setParentAttribute($value)
    {
        $this->setParentIdAttribute($value);
    }
}
