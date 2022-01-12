<?php

namespace App\Models;

use App\Models\Mutators\PageMutators;
use App\Models\Relationships\PageRelationships;
use App\Models\Scopes\PageScopes;
use Kalnoy\Nestedset\NodeTrait;

class Page extends Model
{
    use PageRelationships, PageMutators, PageScopes, NodeTrait;

    const DISABLED = false;

    const ENABLED = true;

    const PARENT_KEY = 'parent_uuid';

    /**
     * Attributes that need to be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'content' => 'array',
        'enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Enable the Page.
     *
     * @return \App\Models\Page
     */
    public function enable()
    {
        $this->enabled = static::ENABLED;

        return $this;
    }

    /**
     * Disable the Page.
     *
     * @return \App\Models\Page
     */
    public function disable()
    {
        $this->enabled = static::DISABLED;

        return $this;
    }

    /**
     * Get the parent id key name.
     *
     * @return string
     */
    public function getParentIdName()
    {
        return static::PARENT_KEY;
    }
}
