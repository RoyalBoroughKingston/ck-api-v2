<?php

declare(strict_types=1);

namespace App\Search\ElasticSearch;

use App\Contracts\SearchCriteriaQuery;

interface QueryBuilderInterface
{
    const ORDER_RELEVANCE = 'relevance';
    const ORDER_DISTANCE = 'distance';

    public function build(SearchCriteriaQuery $query, int $page = null, int $perPage = null): array;
}
