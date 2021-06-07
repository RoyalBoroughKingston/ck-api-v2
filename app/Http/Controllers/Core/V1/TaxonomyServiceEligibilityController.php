<?php

namespace App\Http\Controllers\Core\V1;

use App\Events\EndpointHit;
use App\Http\Controllers\Controller;
use App\Http\Requests\TaxonomyServiceEligibility\IndexRequest;
use App\Http\Resources\TaxonomyCategoryResource;
use App\Models\Taxonomy;
use Spatie\QueryBuilder\QueryBuilder;

class TaxonomyServiceEligibilityController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param \App\Http\Requests\TaxonomyServiceEligibility\IndexRequest $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(IndexRequest $request)
    {
        $baseQuery = Taxonomy::query()
            ->topLevelServiceEligibilities()
            ->with('children.children.children.children.children.children')
            ->orderBy('order');

        $serviceEligibilities = QueryBuilder::for($baseQuery)
            ->get();

        event(EndpointHit::onRead($request, 'Viewed all taxonomy service eligibilities'));

        return TaxonomyCategoryResource::collection($serviceEligibilities);
    }
}
