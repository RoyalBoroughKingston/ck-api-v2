<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class UkPhoneNumber implements Rule
{
    /**
     * @var string|null
     */
    protected $message;

    /**
     * UkPhoneNumber constructor.
     */
    public function __construct(string $message = null)
    {
        $this->message = $message;
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
        // Immediately fail if the value is not a string.
        if (! is_string($value)) {
            return false;
        }

        $matches = preg_match('/^0([1-6][0-9]{8,10}|7[0-9]{9})$/', $value);

        return $matches === 1;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->message ?? 'The :attribute must be a valid UK phone number.';
    }
}
