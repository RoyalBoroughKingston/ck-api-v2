<?php

namespace App\Http\Controllers\Core\V1\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\Collection\PersonaRequest;
use App\Search\ElasticSearch\CollectionPersonaQueryBuilder;
use App\Search\ElasticSearch\ServiceEloquentMapper;
use App\Search\ServiceCriteriaQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CollectionPersonaController extends Controller
{
    /**
     * @param \App\Http\Requests\Search\Collection\PersonaRequest $request
     * @param \App\Search\ServiceCriteriaQuery $criteria
     * @param \App\Search\ElasticSearch\CollectionPersonaQueryBuilder $builder
     * @param \App\Search\ElasticSearch\ServiceEloquentMapper $mapper
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function __invoke(
        PersonaRequest $request,
        ServiceCriteriaQuery $criteria,
        CollectionPersonaQueryBuilder $builder,
        ServiceEloquentMapper $mapper
    ): AnonymousResourceCollection {
        $criteria->setPersonas([$request->input('persona')]);

        $query = $builder->build(
            $criteria,
            $request->input('page'),
            $request->input('per_page')
        );

        return $mapper->paginate($query);
    }
}
