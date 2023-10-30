<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class Postcode implements Rule
{
    /**
     * See https://stackoverflow.com/a/51885364/709923
     * for discussion of this regex.
     */
    const PATTERN = '/^([A-Z][A-HJ-Y]?\d[A-Z\d]? ?\d[A-Z]{2}|GIR ?0A{2})$/i';

    /**
     * Determine if the validation rule passes.
     *
     * @param mixed $value
     */
    public function passes(string $attribute, $value): bool
    {
        // Immediately fail if the value is not a string.
        if (!is_string($value)) {
            return false;
        }

        $matches = preg_match(static::PATTERN, $value);

        return $matches === 1;
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'The :attribute must be a valid postcode.';
    }
}
