<?php

namespace App\Http\Controllers\Core\V1\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\Collection\CategoryRequest;
use App\Search\ElasticSearch\CollectionCategoryQueryBuilder;
use App\Search\ElasticSearch\ServiceEloquentMapper;
use App\Search\SearchCriteriaQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CollectionCategoryController extends Controller
{
    /**
     * @param  \App\Http\Requests\Search\Collection\CategoryRequest  $request
     * @param  \App\Search\SearchCriteriaQuery  $criteria
     * @param  \App\Search\ElasticSearch\CollectionCategoryQueryBuilder  $builder
     * @param  \App\Search\ElasticSearch\ServiceEloquentMapper  $mapper
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function __invoke(
        CategoryRequest $request,
        SearchCriteriaQuery $criteria,
        CollectionCategoryQueryBuilder $builder,
        ServiceEloquentMapper $mapper
    ): AnonymousResourceCollection {
        $criteria->setCategories([$request->input('category')]);

        return $mapper->paginate(
            $builder->build($criteria),
            $request->input('page'),
            $request->input('per_page')
        );
    }
}
