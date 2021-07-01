<?php

namespace App\Http\Controllers\Core\V1;

use App\Events\EndpointHit;
use App\Http\Controllers\Controller;
use App\Http\Filters\Service\HasPermissionFilter;
use App\Http\Filters\Service\OrganisationNameFilter;
use App\Http\Requests\Service\DestroyRequest;
use App\Http\Requests\Service\IndexRequest;
use App\Http\Requests\Service\ShowRequest;
use App\Http\Requests\Service\StoreRequest;
use App\Http\Requests\Service\UpdateRequest;
use App\Http\Resources\ServiceResource;
use App\Http\Responses\ResourceDeleted;
use App\Http\Responses\UpdateRequestReceived;
use App\Http\Sorts\Service\OrganisationNameSort;
use App\Models\Service;
use App\Models\UpdateRequest as UpdateRequestModel;
use App\Services\DataPersistence\ServicePersistenceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\Filter;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\Sort;

class ServiceController extends Controller
{
    /**
     * ServiceController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:api')->except('index', 'show');
    }

    /**
     * Display a listing of the resource.
     *
     * @param \App\Http\Requests\Service\IndexRequest $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(IndexRequest $request)
    {
        $baseQuery = Service::query()
            ->with(
                'usefulInfos',
                'offerings',
                'serviceGalleryItems.file',
                'taxonomies'
            )
            ->when(auth('api')->guest(), function (Builder $query) use ($request) {
                // Limit to active services if requesting user is not authenticated.
                $query->where('status', '=', Service::STATUS_ACTIVE);
            });

        $services = QueryBuilder::for($baseQuery)
            ->allowedFilters([
                Filter::exact('id'),
                Filter::exact('organisation_id'),
                'name',
                Filter::custom('organisation_name', OrganisationNameFilter::class),
                Filter::exact('status'),
                Filter::exact('referral_method'),
                Filter::custom('has_permission', HasPermissionFilter::class),
            ])
            ->allowedIncludes(['organisation'])
            ->allowedSorts([
                'name',
                Sort::custom('organisation_name', OrganisationNameSort::class),
                'status',
                'referral_method',
            ])
            ->defaultSort('name')
            ->paginate(per_page($request->per_page));

        event(EndpointHit::onRead($request, 'Viewed all services'));

        return ServiceResource::collection($services);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \App\Http\Requests\Service\StoreRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRequest $request, ServicePersistenceService $persistenceService)
    {
        $entity = $persistenceService->store($request);

        if ($entity instanceof UpdateRequestModel) {
            event(EndpointHit::onCreate($request, "Created service as update request [{$entity->id}]", $entity));

            return new UpdateRequestReceived($entity);
        }

        // Ensure conditional fields are reset if needed.
        $entity->resetConditionalFields();

        event(EndpointHit::onCreate($request, "Created service [{$entity->id}]", $entity));

        $entity->load('usefulInfos', 'offerings', 'taxonomies', 'serviceEligibilities');

        return new ServiceResource($entity);
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Http\Requests\Service\ShowRequest $request
     * @param \App\Models\Service $service
     * @return \App\Http\Resources\ServiceResource
     */
    public function show(ShowRequest $request, Service $service)
    {
        $baseQuery = Service::query()
            ->with(
                'usefulInfos',
                'offerings',
                'serviceGalleryItems.file',
                'taxonomies'
            )
            ->where('id', $service->id);

        $service = QueryBuilder::for($baseQuery)
            ->allowedIncludes(['organisation'])
            ->firstOrFail();

        event(EndpointHit::onRead($request, "Viewed service [{$service->id}]", $service));

        return new ServiceResource($service);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \App\Http\Requests\Service\UpdateRequest $request
     * @param \App\Models\Service $service
     * @return UpdateRequestReceived
     */
    public function update(UpdateRequest $request, Service $service, ServicePersistenceService $persistenceService)
    {
        $updateRequest = $persistenceService->update($request, $service);
        event(EndpointHit::onUpdate($request, "Created update request for service [{$service->id}]", $service));

        return new UpdateRequestReceived($updateRequest);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Http\Requests\Service\DestroyRequest $request
     * @param \App\Models\Service $service
     * @return \Illuminate\Http\Response
     */
    public function destroy(DestroyRequest $request, Service $service)
    {
        return DB::transaction(function () use ($request, $service) {
            event(EndpointHit::onDelete($request, "Deleted service [{$service->id}]", $service));

            $service->serviceEligibilities()->delete();

            $service->delete();

            return new ResourceDeleted('service');
        });
    }
}
