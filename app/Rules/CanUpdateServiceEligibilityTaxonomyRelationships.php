<?php

namespace App\Rules;

use App\Models\Service;
use App\Models\Taxonomy;
use App\Models\User;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Arr;

class CanUpdateServiceEligibilityTaxonomyRelationships implements Rule
{
    /**
     * @var \App\Models\User
     */
    protected $user;

    /**
     * @var \App\TaxonomyRelationships\HasTaxonomyRelationships
     */
    protected $model;

    /**
     * Create a new rule instance.
     *
     * @param \App\Models\User $user
     * @param \App\TaxonomyRelationships\HasTaxonomyRelationships $model
     */
    public function __construct(User $user, Service $model)
    {
        $this->user = $user;
        $this->model = $model;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        // Immediately fail if the value is not an array of strings.
        if (!is_array($value)) {
            return false;
        }

        // Allow changing of taxonomies if service admin.
        if (
            ($this->model instanceof Service && $this->user->isServiceAdmin($this->model))
        ) {
            return true;
        }

        // Only pass if the taxonomies remain unchanged.
        $existingTaxonomyIds = $this->model
            ->taxonomies()
            ->pluck(table(Taxonomy::class, 'id'))
            ->toArray();
        $existingTaxonomies = Arr::sort($existingTaxonomyIds);
        $newTaxonomies = Arr::sort($value);
        $taxonomiesUnchanged = $existingTaxonomies === $newTaxonomies;

        return $taxonomiesUnchanged;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'You are not authorised to update this ' . class_basename($this->model) . '\'s category taxonomies.';
    }
}
