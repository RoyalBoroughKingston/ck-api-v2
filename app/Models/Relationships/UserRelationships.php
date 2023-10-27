<?php

namespace App\Models\Relationships;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Audit;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\Service;
use App\Models\StatusUpdate;
use App\Models\UpdateRequest;
use App\Models\User;
use App\Models\UserRole;

trait UserRelationships
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, (new UserRole())->getTable())->distinct();
    }

    /**
     * This returns a collection of the roles assigned to the user
     * ordered by the highest role first.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function orderedRoles(): BelongsToMany
    {
        $sql = (new User())->getHighestRoleOrderSql();

        return $this->roles()->orderByRaw($sql['sql'], $sql['bindings']);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function updateRequests(): HasMany
    {
        return $this->hasMany(UpdateRequest::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function actionedUpdateRequests(): HasMany
    {
        return $this->hasMany(UpdateRequest::class, 'actioning_user_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function audits(): HasMany
    {
        return $this->hasMany(Audit::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function statusUpdated(): HasMany
    {
        return $this->hasMany(StatusUpdate::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function organisations(): BelongsToMany
    {
        return $this->belongsToMany(Organisation::class, table(UserRole::class))
            ->distinct();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, table(UserRole::class))
            ->distinct();
    }
}
