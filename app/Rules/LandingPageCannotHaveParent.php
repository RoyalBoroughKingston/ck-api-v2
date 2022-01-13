<?php

namespace App\Rules;

use App\Models\Page;
use Illuminate\Contracts\Validation\Rule;

class LandingPageCannotHaveParent implements Rule
{
    /**
     * Parent ID
     *
     * @var String
     **/
    protected $parentId = null;

    /**
     * Create a new rule instance.
     *
     * @param \App\Models\Page $page
     */
    public function __construct($parentId)
    {
        $this->parentId = $parentId;
    }
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return $value === Page::PAGE_TYPE_INFORMATION || ($value === Page::PAGE_TYPE_LANDING && is_null($this->parentId));
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Cannot set :attribute to ' . Page::PAGE_TYPE_LANDING . ' when the page has a parent';
    }
}
