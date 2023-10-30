<?php

namespace App\Rules;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Contracts\Validation\Rule;

class UserHasRole implements Rule
{
    /**
     * @var \App\Models\User
     */
    protected $user;

    /**
     * @var \App\Models\Role
     */
    protected $userRole;

    /**
     * @var mixed
     */
    protected $originalValue;

    /**
     * Create a new rule instance.
     *
     * @param mixed $originalValue
     */
    public function __construct(User $user, UserRole $userRole, $originalValue)
    {
        $this->user = $user;
        $this->userRole = $userRole;
        $this->originalValue = $originalValue;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param mixed $value
     */
    public function passes(string $attribute, $value): bool
    {
        if ($this->originalValue === $value) {
            return true;
        }

        switch ($this->userRole->role->name) {
            case Role::NAME_SERVICE_WORKER:
                return $this->user->isServiceWorker($this->userRole->service);
            case Role::NAME_SERVICE_ADMIN:
                return $this->user->isServiceWorker($this->userRole->service);
            case Role::NAME_ORGANISATION_ADMIN:
                return $this->user->isOrganisationAdmin($this->userRole->organisation);
            case Role::NAME_CONTENT_ADMIN:
                return $this->user->isContentAdmin();
            case Role::NAME_GLOBAL_ADMIN:
                return $this->user->isGlobalAdmin();
            case Role::NAME_SUPER_ADMIN:
                return $this->user->isSuperAdmin();
            default:
                return false;
        }
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'You are not authorised to update the :attribute field.';
    }
}
