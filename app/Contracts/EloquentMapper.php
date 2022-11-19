<?php

namespace App\Contracts;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

interface EloquentMapper
{
    /**
     * Performs the search on the model and returns a paginated collection response.
     *
     * @param array $esQuery
     * @return AnonymousResourceCollection
     */
    public function paginate(array $esQuery): AnonymousResourceCollection;
}
