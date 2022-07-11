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
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        // Immediately fail if the value is not an array.
        if (!is_array($value)) {
            return false;
        }

        if ($value['type'] === 'copy') {
            if (!isset($value['value']) || !is_string($value['value']) || mb_strlen($value['value']) === 0) {
                $this->message = 'Invalid format for copy content block';

                return false;
            }
        }
        if ($value['type'] === 'cta') {
            if ((!isset($value['title']) || !is_string($value['title']) || mb_strlen($value['title']) === 0)) {
                $this->message = 'Call to action title is required';

                return false;
            }
            if (!isset($value['description']) || !is_string($value['description']) || mb_strlen($value['description']) === 0) {
                $this->message = 'Call to action description is required';

                return false;
            }
            if ((!empty($value['url']) && empty($value['buttonText'])) || (empty($value['url']) && !empty($value['buttonText']))) {
                $this->message = 'Call to action with a link requires both the URL and the button text';

                return false;
            }
            if (!empty($value['url']) && filter_var($value['url'], FILTER_VALIDATE_URL) === false) {
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
    public function message()
    {
        return $this->message;
    }
}
