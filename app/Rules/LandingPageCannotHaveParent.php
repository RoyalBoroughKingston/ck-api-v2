<?php

namespace App\Rules;

use App\Models\Page;
use Illuminate\Contracts\Validation\Rule;

class LandingPageCannotHaveParent implements Rule
{
    /**
     * Parent ID.
     *
     * @var string
     */
    protected $parentId = null;

    /**
     * Create a new rule instance.
     *
     * @param \App\Models\Page $page
     * @param mixed $parentId
     */
    public function __construct($parentId)
    {
        $this->parentId = $parentId;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param mixed $value
     */
    public function passes(string $attribute, $value): bool
    {
        return $value === Page::PAGE_TYPE_INFORMATION || ($value === Page::PAGE_TYPE_LANDING && is_null($this->parentId));
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'Cannot set :attribute to ' . Page::PAGE_TYPE_LANDING . ' when the page has a parent';
    }
}
