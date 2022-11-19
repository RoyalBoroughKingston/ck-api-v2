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
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventEloquentMapper implements EloquentMapper
{
    public function paginate(array $esQuery): AnonymousResourceCollection
    {
        $response = Event::searchRaw($esQuery);

        $this->logMetrics($esQuery, $response);

        // Extract the hits from the array.
        $hits = $response['hits']['hits'];

        // Get all of the ID's for the events from the hits.
        $eventIds = collect($hits)->map->_id->toArray();

        // Implode the service ID's so we can sort by them in database.
        $eventIdsImploded = implode("','", $eventIds);
        $eventIdsImploded = "'$eventIdsImploded'";

        // Check if the query has been ordered by distance.
        $sorts = array_map(function ($order) {
            return is_array($order) ? array_keys($order)[0] : $order;
        }, $esQuery['sort']?? []);
        $isOrderedByDistance = in_array('_geo_distance', $sorts);

        // Create the query to get the events, and keep ordering from Elasticsearch.
        $events = Event::query()
            ->with('location')
            ->whereIn('id', $eventIds)
            ->orderByRaw("FIELD(id,$eventIdsImploded)")
            ->get();

        // Order the fetched service locations by distance.
        // TODO: Potential solution to the order nested locations in Elasticsearch: https://stackoverflow.com/a/43440405
        if ($isOrderedByDistance) {
            $events = $this->orderEventsByLocation($esQuery, $events);
        }

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

    protected function logMetrics(array $esQuery, array $response): void
    {
        $query = $esQuery['query']['function_score'];
        if (isset($esQuery['sort'])) {
            $query['sort'] = $esQuery['sort'];
        }

        SearchHistory::create([
            'query' => $query,
            'count' => $response['hits']['total']['value'],
        ]);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection $events
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function orderEventsByLocation(array $esQuery, Collection $events): Collection
    {
        return $events->filter(function (Event $event) {
            return !$event->is_virtual;
        })
            ->sortBy(function (Event $event) use ($esQuery) {
                $location = $esQuery['sort'][0]['_geo_distance']['event_location.location'];
                $location = new Coordinate($location['lat'], $location['lon']);

                return $location->distanceFrom($event->location->toCoordinate());
            });
    }
}
