<?php

namespace App\Rules;

use App\Models\Service;
use App\Models\User;
use Illuminate\Contracts\Validation\Rule;

class CanUpdateServiceStatus implements Rule
{
    /**
     * @var \App\Models\User
     */
    protected $user;

    /**
     * @var \App\Models\Service
     */
    protected $service;

    /**
     * Create a new rule instance.
     *
     * @param \App\Models\User $user
     * @param \App\Models\Service $service
     */
    public function __construct(User $user, Service $service)
    {
        $this->user = $user;
        $this->service = $service;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string $attribute
     * @param  mixed $status
     * @return bool
     */
    public function passes($attribute, $status)
    {
        if (!is_string($status)) {
            return false;
        }

        if ($this->service->status === $status) {
            return true;
        }

        return $this->user->isGlobalAdmin();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'You are not authorised to update the service status.';
    }
}