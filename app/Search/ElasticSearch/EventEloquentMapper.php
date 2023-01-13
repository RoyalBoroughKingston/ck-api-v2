<?php

declare(strict_types=1);

namespace App\Search\ElasticSearch;

use App\Support\Coordinate;
use App\Models\SearchHistory;
use App\Contracts\EloquentMapper;
use Illuminate\Pagination\Paginator;
use App\Models\OrganisationEvent as Event;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Resources\OrganisationEventResource;
use ElasticScoutDriverPlus\Decorators\SearchResult;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventEloquentMapper implements EloquentMapper
{
    public function paginate(array $esQuery, int $page = null, int $perPage = null): AnonymousResourceCollection
    {
        $page = page($page);
        $perPage = per_page($perPage);

        $response = Event::searchQuery($esQuery)
            ->size($perPage)
            ->from(($page - 1) * $perPage)
            ->execute();

        $this->logMetrics($esQuery, $response);

        /**
         * Order the fetched service locations by distance.
         * @todo Potential solution to the order nested locations in Elasticsearch: https://stackoverflow.com/a/43440405
         */
        $sorts = array_map(function ($order) {
            return is_array($order) ? array_keys($order)[0] : $order;
        }, $esQuery['sort'] ?? []);
        $isOrderedByDistance = in_array('_geo_distance', $sorts);
        $events = $isOrderedByDistance? $this->orderEventsByLocation($esQuery, $response->models()) : $response->models();

        // If paginated, then create a new pagination instance.
        $events = new LengthAwarePaginator(
            $events,
            $response['hits']['total']['value'],
            $esQuery['size'],
            ($esQuery['from'] / $esQuery['size']) + 1,
            ['path' => Paginator::resolveCurrentPath()]
        );

        return OrganisationEventResource::collection($events);
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

    /**
     * @param  \Illuminate\Database\Eloquent\Collection  $events
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function orderEventsByLocation(array $esQuery, Collection $events): Collection
    {
        return $events->filter(function (Event $event) {
            return ! $event->is_virtual;
        })
            ->sortBy(function (Event $event) use ($esQuery) {
                $location = $esQuery['sort'][0]['_geo_distance']['event_location.location'];
                $location = new Coordinate($location['lat'], $location['lon']);

                return $location->distanceFrom($event->location->toCoordinate());
            });
    }
}
