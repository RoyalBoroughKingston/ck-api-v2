<?php

namespace App\Contracts;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

interface PageSearch
{
    const ORDER_RELEVANCE = 'relevance';

    /**
     * @param string $term
     * @return \App\Contracts\PageSearch
     */
    public function applyQuery(string $term): PageSearch;

    /**
     * @param string $order
     * @param \App\Support\Coordinate|null $location
     * @return \App\Contracts\PageSearch
     */
    public function applyOrder(string $order): PageSearch;

    /**
     * Returns the underlying query. Only intended for use in testing.
     *
     * @return array
     */
    public function getQuery(): array;

    /**
     * @param int|null $page
     * @param int|null $perPage
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function paginate(int $page = null, int $perPage = null): AnonymousResourceCollection;

    /**
     * @param int|null $perPage
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function get(int $perPage = null): AnonymousResourceCollection;
}
