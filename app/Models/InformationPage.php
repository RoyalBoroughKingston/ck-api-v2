<?php

namespace App\Models;

use App\Models\Mutators\InformationPageMutators;
use App\Models\Relationships\InformationPageRelationships;
use App\Models\Scopes\InformationPageScopes;
use Kalnoy\Nestedset\NodeTrait;

class InformationPage extends Model
{
    use
    InformationPageRelationships,
    InformationPageMutators,
    InformationPageScopes,
        NodeTrait;

    const DISABLED = false;
    const ENABLED = true;
    const PARENT_KEY = 'parent_uuid';

    /**
     * Attributes that need to be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Enable the InformationPage.
     *
     * @return \App\Models\InformationPage
     */
    public function enable()
    {
        $this->enabled = static::ENABLED;

        return $this;
    }

    /**
     * Disable the InformationPage.
     *
     * @return \App\Models\InformationPage
     */
    public function disable()
    {
        $this->enabled = static::DISABLED;

        return $this;
    }

    /**
     * Get the parent id key name.
     *
     * @return  string
     */
    public function getParentIdName()
    {
        return static::PARENT_KEY;
    }

    /**
     * Specify parent id attribute mutator
     */
    public function setParentAttribute($value)
    {
        $this->setParentIdAttribute($value);
    }
}
