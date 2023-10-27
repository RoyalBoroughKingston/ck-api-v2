<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class Slug implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes(string $attribute, $value): bool
    {
        // Immediately fail if the value is not a string.
        if (! is_string($value)) {
            return false;
        }

        $matches = preg_match('/^([a-z0-9]+[a-z0-9\-]*)*[a-z0-9]+$/', $value);

        return $matches === 1;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return 'The :attribute must be a valid slug.';
    }
}
