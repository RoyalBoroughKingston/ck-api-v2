<?php

namespace App\Contracts;

use App\Search\SearchCriteriaQuery;

interface QueryBuilder
{
    /**
     * Build the search query.
     *
     * @param  App\Search\SearchCriteriaQuery  $query
     * @param  int  $page
     * @param  int  $perPage
     * @return array
     */
    public function build(SearchCriteriaQuery $query, int $page, int $perPage): array;
}
