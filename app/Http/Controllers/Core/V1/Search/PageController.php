<?php

declare(strict_types=1);

namespace App\Http\Controllers\Core\V1\Search;

use App\Http\Requests\Search\Page\Request;
use App\Search\ElasticSearch\PageEloquentMapper;
use App\Search\ElasticSearch\PageQueryBuilder;
use App\Search\PageCriteriaQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PageController
{
    /**
     * @param \App\Http\Requests\Search\Page\Request $request
     * @param \App\Search\PageCriteriaQuery $criteria
     * @param \App\Search\ElasticSearch\PageQueryBuilder $builder
     * @param \App\Search\ElasticSearch\PageEloquentMapper $mapper
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function __invoke(
        Request $request,
        PageCriteriaQuery $criteria,
        PageQueryBuilder $builder,
        PageEloquentMapper $mapper
    ): AnonymousResourceCollection {
        // Apply query.
        if ($request->has('query')) {
            $criteria->setQuery($request->input('query'));
        }

        $query = $builder->build(
            $criteria,
            $request->input('page'),
            $request->input('per_page')
        );

        // Perform the search.
        return $mapper->paginate($query);
    }
}
