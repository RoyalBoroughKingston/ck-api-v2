<?php

namespace App\Contracts;

use ElasticScoutDriverPlus\Decorators\SearchResult;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

interface EloquentMapper
{
    /**
     * Performs the search on the model and returns a paginated collection response.
     *
     * @param  array  $esQuery
     * @param  int  $page
     * @param  int  $perPage
     * @return AnonymousResourceCollection
     */
    public function paginate(array $esQuery, int $page, int $perPage): AnonymousResourceCollection;

    /**
     * Log the query and the result summary
     *
     * @param array $esQuery
     * @param SearchResult $response
     * @return void
     */
    public function logMetrics(array $esQuery, SearchResult $response): void;
}
