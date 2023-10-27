<?php

namespace App\Rules;

use App\Models\UpdateRequest;
use Illuminate\Contracts\Validation\Rule;

class UserEmailNotInPendingSignupRequest implements Rule
{
    /**
     * @var string|null
     */
    protected $message;

    /**
     * Create a new rule instance.
     *
     * @param  \App\Models\User|null  $excludedUser
     */
    public function __construct(string $message = null)
    {
        $this->message = $message;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  mixed  $email
     */
    public function passes(string $attribute, $email): bool
    {
        if (! is_string($email)) {
            return false;
        }

        return UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::NEW_TYPE_ORGANISATION_SIGN_UP_FORM)
            ->where('data->user->email', $email)
            ->doesntExist();
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return $this->message ?? 'This email address exists for a user pending approval.';
    }
}
