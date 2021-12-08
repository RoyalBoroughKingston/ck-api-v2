<?php

namespace App\Models;

use App\Models\Mutators\InformationPageMutators;
use App\Models\Relationships\InformationPageRelationships;
use App\Models\Scopes\InformationPageScopes;

class InformationPage extends Model
{
    use InformationPageRelationships,
    InformationPageMutators,
        InformationPageScopes;

    const DISABLED = false;
    const ENABLED = true;

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
}
