<?php

namespace App\Http\Filters\Service;

use App\Models\Service;
use Spatie\QueryBuilder\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class HasPermissionFilter implements Filter
{
    public function __invoke(Builder $query, $value, string $property): Builder
    {
        $user = request()->user('api');
        if (!$user || !$user->isGlobalAdmin()) {
            $serviceIds = $user
            ? $user->services()->pluck(table(Service::class, 'id'))->toArray()
            : [];
            $query->whereIn(table(Service::class, 'id'), $serviceIds);
        }
        return $query;
    }
}
