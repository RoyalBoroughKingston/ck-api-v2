<?php

namespace App\Rules;

use App\Models\Taxonomy;
use Illuminate\Contracts\Validation\Rule;

class RootTaxonomyIs implements Rule
{
    /**
     * @var string
     */
    protected $rootTaxonomyName;

    /**
     * Create a new rule instance.
     */
    public function __construct(string $rootTaxonomyName)
    {
        $this->rootTaxonomyName = $rootTaxonomyName;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  mixed  $value
     */
    public function passes(string $attribute, $value): bool
    {
        // Immediately fail if the value is not a string.
        if (! is_string($value)) {
            return false;
        }

        $taxonomy = Taxonomy::query()->find($value);

        return $taxonomy ? $taxonomy->rootIsCalled($this->rootTaxonomyName) : false;
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return "The root taxonomy must be called [{$this->rootTaxonomyName}].";
    }
}
