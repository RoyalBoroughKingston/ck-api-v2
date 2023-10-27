<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class PageContent implements Rule
{
    /**
     * The error message to return.
     *
     * @var string
     */
    protected $message;

    /**
     * The page type: landing, information, etc.
     *
     * @var string
     */
    protected $pageType;

    /**
     * @param  mixed  $pageType
     */
    public function __construct($pageType = 'information')
    {
        $this->pageType = $pageType;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes(string $attribute, $value): bool
    {
        // Immediately fail if the value is not an array.
        if (! is_array($value)) {
            return false;
        }

        if ($value['type'] === 'copy') {
            if (! array_key_exists('value', $value)) {
                $this->message = 'Invalid format for content';

                return false;
            }
            if (($this->pageType === 'landing' && mb_strpos($attribute, 'introduction') && empty($value['value']))) {
                $this->message = 'Page content is required for introduction';

                return false;
            }
        }
        if ($value['type'] === 'cta') {
            if (empty($value['title']) || ! is_string($value['title'])) {
                $this->message = 'Call to action title is required';

                return false;
            }
            if (empty($value['description']) || ! is_string($value['description'])) {
                $this->message = 'Call to action description is required';

                return false;
            }
            if ((! empty($value['url']) && empty($value['buttonText'])) || (empty($value['url']) && ! empty($value['buttonText']))) {
                $this->message = 'Call to action with a link requires both the URL and the button text';

                return false;
            }
            if (! empty($value['url']) && filter_var($value['url'], FILTER_VALIDATE_URL) === false) {
                $this->message = 'Call to action link must be a valid URL';

                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return $this->message;
    }
}
