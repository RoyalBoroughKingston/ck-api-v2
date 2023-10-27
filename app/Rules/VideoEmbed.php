<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Str;

class VideoEmbed implements Rule
{
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

        $validDomains = [
            'https://www.youtube.com',
            'https://player.vimeo.com',
            'https://vimeo.com',
        ];

        return Str::startsWith($value, $validDomains);
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'The :attribute must be provided by either YouTube or Vimeo.';
    }
}
