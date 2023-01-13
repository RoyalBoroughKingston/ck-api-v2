<?php

declare(strict_types=1);

namespace App\Search\ElasticSearch;

use App\Models\Service;
use App\Support\Coordinate;
use App\Models\SearchHistory;
use App\Models\ServiceLocation;
use App\Contracts\EloquentMapper;
use Illuminate\Pagination\Paginator;
use App\Http\Resources\ServiceResource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use ElasticScoutDriverPlus\Decorators\SearchResult;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceEloquentMapper implements EloquentMapper
{
    public function paginate(array $esQuery, int $page = null, int $perPage = null): AnonymousResourceCollection
    {
        dump($esQuery);
        $page = page($page);
        $perPage = per_page($perPage);

        $response = Service::searchQuery($esQuery)
            ->size($perPage)
            ->from(($page - 1) * $perPage)
            ->execute();

        dump('Hits', $response->hits());
        dump('Models', $response->models());
        dump('Documents', $response->documents());

        $this->logMetrics($esQuery, $response);

        /**
         * Order the fetched service locations by distance.
         * @todo Potential solution to the order nested locations in Elasticsearch: https://stackoverflow.com/a/43440405
         */
        $isOrderedByDistance = isset($esQuery['sort']);
        $services = $isOrderedByDistance? $this->orderServicesByLocation($esQuery, $response->models()) : $response->models();

        // If paginated, then create a new pagination instance.
        $services = new LengthAwarePaginator(
            $services,
            $response->total(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );

        return ServiceResource::collection($services);
    }

    public function logMetrics(array $esQuery, SearchResult $response): void
    {
        $query = $esQuery['function_score'];
        if (isset($esQuery['sort'])) {
            $query['sort'] = $esQuery['sort'];
        }

        SearchHistory::create([
            'query' => $query,
            'count' => $response->total(),
        ]);
    }

    protected function orderServicesByLocation(array $esQuery, Collection $services): Collection
    {
        return $services->each(function (Service $service) use ($esQuery) {
            $service->serviceLocations = $service->serviceLocations->sortBy(
                function (ServiceLocation $serviceLocation) use ($esQuery) {
                    $location = $esQuery['sort'][0]['_geo_distance']['service_locations.location'];
                    $location = new Coordinate($location['lat'], $location['lon']);

                    return $location->distanceFrom($serviceLocation->location->toCoordinate());
                }
            );
        });
    }
}
