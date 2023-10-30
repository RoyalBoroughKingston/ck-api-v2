<?php

namespace App\Rules;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Contracts\Validation\Rule;

class IsOrganisationAdmin implements Rule
{
    /**
     * @var \App\Models\User
     */
    protected $user;

    /**
     * Create a new rule instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

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

        $organisation = Organisation::query()->find($value);

        return $organisation ? $this->user->isOrganisationAdmin($organisation) : false;
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'The :attribute field must contain an ID for an organisation you are an organisation admin for.';
    }
}
