<?php

namespace App\Rules;

use App\Models\Page;
use Illuminate\Contracts\Validation\Rule;

class InformationPageCannotHaveCollection implements Rule
{
    /**
     * Page type.
     *
     * @var string
     */
    protected $pageType;

    /**
     * Create a new rule instance.
     *
     * @param  \App\Models\Page  $page
     * @param  mixed  $pageId
     * @param  mixed  $pageType
     */
    public function __construct($pageType)
    {
        $this->pageType = $pageType;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  mixed  $value
     */
    public function passes(string $attribute, $value): bool
    {
        return $this->pageType === Page::PAGE_TYPE_LANDING || empty($value);
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'Pages of type other than '.Page::PAGE_TYPE_LANDING.' cannot have collections';
    }
}
