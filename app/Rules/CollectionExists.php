<?php

namespace App\Rules;

use App\Models\Collection;
use Illuminate\Contracts\Validation\Rule;

class CollectionExists implements Rule
{
    /**
     * @var string
     */
    protected $type;

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        // Immediately fail if the value is not a string.
        if (!is_string($value)) {
            return false;
        }

        return Collection::query()
            ->where('type', '=', $this->type)
            ->where('slug', '=', $value)
            ->exists();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return "The :attribute field must be a valid {$this->type} collection.";
    }
}
