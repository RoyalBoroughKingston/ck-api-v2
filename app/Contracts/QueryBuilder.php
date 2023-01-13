<?php

namespace App\Contracts;

use App\Search\SearchCriteriaQuery;

interface QueryBuilder
{
    /**
     * Build the search query.
     *
     * @param  App\Search\SearchCriteriaQuery  $query
     * @return array
     */
    public function build(SearchCriteriaQuery $query): array;
}
