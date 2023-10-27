<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Arr;

class Synonyms implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  mixed  $value
     */
    public function passes(string $attribute, $value): bool
    {
        foreach ($value as $synonym) {
            if (! is_string($synonym)) {
                return false;
            }
        }

        if (preg_match('/\s/', Arr::last($value))) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'The last synonym must be a single word.';
    }
}
