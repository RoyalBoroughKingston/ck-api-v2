<?php

namespace App\Search;

<<<<<<< HEAD:app/Search/ElasticsearchServiceSearch.php
use App\Contracts\ServiceSearch;
=======
use App\Contracts\PageSearch;
>>>>>>> fcb21b09... Created entities for page search:app/Search/ElasticsearchPageSearch.php
use App\Http\Resources\ServiceResource;
use App\Models\Collection as CollectionModel;
use App\Models\SearchHistory;
use App\Models\Service;
use App\Models\ServiceLocation;
use App\Models\Taxonomy;
use App\Support\Coordinate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use InvalidArgumentException;

<<<<<<< HEAD:app/Search/ElasticsearchServiceSearch.php
class ElasticsearchServiceSearch implements ServiceSearch
=======
class ElasticsearchPageSearch implements PageSearch
>>>>>>> fcb21b09... Created entities for page search:app/Search/ElasticsearchPageSearch.php
{
    const MILES = 'mi';
    const YARDS = 'yd';
    const FEET = 'ft';
    const INCHES = 'in';
    const KILOMETERS = 'km';
    const METERS = 'm';
    const CENTIMETERS = 'cm';
    const MILLIMETERS = 'mm';
    const NAUTICAL_MILES = 'nmi';

    /**
     * @var array
     */
    protected $query;

    /**
     * Search constructor.
     */
    public function __construct()
    {
        $this->query = [
            'from' => 0,
            'size' => config('local.pagination_results'),
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'term' => [
                                'status' => Service::STATUS_ACTIVE,
                            ],
                        ],
                    ],
                    'must' => [
                        'bool' => [
                            'should' => [
                                //
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param string $term
     * @return \App\Search\ElasticsearchPageSearch
     */
<<<<<<< HEAD:app/Search/ElasticsearchServiceSearch.php
    public function applyQuery(string $term): ServiceSearch
=======
    public function applyQuery(string $term): PageSearch
>>>>>>> fcb21b09... Created entities for page search:app/Search/ElasticsearchPageSearch.php
    {
        $should = &$this->query['query']['bool']['must']['bool']['should'];

        $should[] = $this->match('name', $term, 3);
        $should[] = $this->match('organisation_name', $term, 3);
        $should[] = $this->match('intro', $term, 2);
        $should[] = $this->matchPhrase('description', $term, 1.5);
        $should[] = $this->match('taxonomy_categories', $term);

        if (empty($this->query['query']['bool']['must']['bool']['minimum_should_match'])) {
            $this->query['query']['bool']['must']['bool']['minimum_should_match'] = 1;
        }

        return $this;
    }

    /**
     * @param string $field
     * @param string $term
     * @param int $boost
     * @param mixed $fuzziness
     * @return array
     */
    protected function match(string $field, string $term, int $boost = 1, $fuzziness = 'AUTO'): array
    {
        return [
            'match' => [
                $field => [
                    'query' => $term,
                    'boost' => $boost,
                    'fuzziness' => $fuzziness,
                ],
            ],
        ];
    }

    /**
     * @param string $field
     * @param string $term
     * @param int $boost
     * @return array
     */
    protected function matchPhrase(string $field, string $term, int $boost = 1): array
    {
        return [
            'match_phrase' => [
                $field => [
                    'query' => $term,
                    'boost' => $boost,
                ],
            ],
        ];
    }

    /**
     * @param string $category
     * @return \App\Search\ElasticsearchPageSearch
     */
<<<<<<< HEAD:app/Search/ElasticsearchServiceSearch.php
    public function applyCategory(string $category): ServiceSearch
=======
    public function applyCategory(string $category): PageSearch
>>>>>>> fcb21b09... Created entities for page search:app/Search/ElasticsearchPageSearch.php
    {
        $categoryModel = CollectionModel::query()
            ->with('taxonomies')
            ->categories()
            ->where('name', $category)
            ->firstOrFail();

        $should = &$this->query['query']['bool']['must']['bool']['should'];

        foreach ($categoryModel->taxonomies as $taxonomy) {
            $should[] = $this->match('taxonomy_categories', $taxonomy->name);
        }

        $this->query['query']['bool']['filter'][] = [
            'term' => [
                'collection_categories' => $category,
            ],
        ];

        return $this;
    }

    /**
     * @param string $persona
     * @return \App\Search\ElasticsearchPageSearch
     */
<<<<<<< HEAD:app/Search/ElasticsearchServiceSearch.php
    public function applyPersona(string $persona): ServiceSearch
=======
    public function applyPersona(string $persona): PageSearch
>>>>>>> fcb21b09... Created entities for page search:app/Search/ElasticsearchPageSearch.php
    {
        $categoryModel = CollectionModel::query()
            ->with('taxonomies')
            ->personas()
            ->where('name', $persona)
            ->firstOrFail();

        $should = &$this->query['query']['bool']['must']['bool']['should'];

        foreach ($categoryModel->taxonomies as $taxonomy) {
            $should[] = $this->match('taxonomy_categories', $taxonomy->name);
        }

        $this->query['query']['bool']['filter'][] = [
            'term' => [
                'collection_personas' => $persona,
            ],
        ];

        return $this;
    }

    /**
     * @param string $waitTime
     * @return \App\Contracts\Search
     */
<<<<<<< HEAD:app/Search/ElasticsearchServiceSearch.php
    public function applyWaitTime(string $waitTime): ServiceSearch
=======
    public function applyWaitTime(string $waitTime): PageSearch
>>>>>>> fcb21b09... Created entities for page search:app/Search/ElasticsearchPageSearch.php
    {
        if (!Service::waitTimeIsValid($waitTime)) {
            throw new InvalidArgumentException("The wait time [$waitTime] is not valid");
        }

        $criteria = [];

        switch ($waitTime) {
            case Service::WAIT_TIME_ONE_WEEK:
                $criteria[] = Service::WAIT_TIME_ONE_WEEK;
                break;
            case Service::WAIT_TIME_TWO_WEEKS:
                $criteria[] = Service::WAIT_TIME_ONE_WEEK;
                $criteria[] = Service::WAIT_TIME_TWO_WEEKS;
                break;
            case Service::WAIT_TIME_THREE_WEEKS:
                $criteria[] = Service::WAIT_TIME_ONE_WEEK;
                $criteria[] = Service::WAIT_TIME_TWO_WEEKS;
                $criteria[] = Service::WAIT_TIME_THREE_WEEKS;
                break;
            case Service::WAIT_TIME_MONTH:
                $criteria[] = Service::WAIT_TIME_ONE_WEEK;
                $criteria[] = Service::WAIT_TIME_TWO_WEEKS;
                $criteria[] = Service::WAIT_TIME_THREE_WEEKS;
                $criteria[] = Service::WAIT_TIME_MONTH;
                break;
            case Service::WAIT_TIME_LONGER:
                $criteria[] = Service::WAIT_TIME_ONE_WEEK;
                $criteria[] = Service::WAIT_TIME_TWO_WEEKS;
                $criteria[] = Service::WAIT_TIME_THREE_WEEKS;
                $criteria[] = Service::WAIT_TIME_MONTH;
                $criteria[] = Service::WAIT_TIME_LONGER;
                break;
        }

        $this->query['query']['bool']['filter'][] = [
            'terms' => [
                'wait_time' => $criteria,
            ],
        ];

        return $this;
    }

    /**
     * @param bool $isFree
     * @return \App\Contracts\Search
     */
<<<<<<< HEAD:app/Search/ElasticsearchServiceSearch.php
    public function applyIsFree(bool $isFree): ServiceSearch
=======
    public function applyIsFree(bool $isFree): PageSearch
>>>>>>> fcb21b09... Created entities for page search:app/Search/ElasticsearchPageSearch.php
    {
        $this->query['query']['bool']['filter'][] = [
            'term' => [
                'is_free' => $isFree,
            ],
        ];

        return $this;
    }

    /**
     * @param string $order
     * @param \App\Support\Coordinate|null $location
     * @return \App\Search\ElasticsearchPageSearch
     */
<<<<<<< HEAD:app/Search/ElasticsearchServiceSearch.php
    public function applyOrder(string $order, Coordinate $location = null): ServiceSearch
=======
    public function applyOrder(string $order, Coordinate $location = null): PageSearch
>>>>>>> fcb21b09... Created entities for page search:app/Search/ElasticsearchPageSearch.php
    {
        if ($order === static::ORDER_DISTANCE) {
            $this->query['sort'] = [
                [
                    '_geo_distance' => [
                        'service_locations.location' => $location->toArray(),
                        'nested_path' => 'service_locations',
                    ],
                ],
            ];
        }

        return $this;
    }

    /**
     * @param \App\Support\Coordinate $location
     * @param int $radius
     * @return \App\Contracts\Search
     */
<<<<<<< HEAD:app/Search/ElasticsearchServiceSearch.php
    public function applyRadius(Coordinate $location, int $radius): ServiceSearch
=======
    public function applyRadius(Coordinate $location, int $radius): PageSearch
>>>>>>> fcb21b09... Created entities for page search:app/Search/ElasticsearchPageSearch.php
    {
        $this->query['query']['bool']['filter'][] = [
            'nested' => [
                'path' => 'service_locations',
                'query' => [
                    'geo_distance' => [
                        'distance' => $this->distance($radius),
                        'service_locations.location' => $location->toArray(),
                    ],
                ],
            ],
        ];

        return $this;
    }

<<<<<<< HEAD:app/Search/ElasticsearchServiceSearch.php
    public function applyEligibilities(array $eligibilityNames): ServiceSearch
=======
    public function applyEligibilities(array $eligibilityNames): PageSearch
>>>>>>> fcb21b09... Created entities for page search:app/Search/ElasticsearchPageSearch.php
    {
        $eligibilities = Taxonomy::whereIn('name', $eligibilityNames)->get();
        $eligibilityIds = $eligibilities->pluck('id')->all();

        foreach (Taxonomy::serviceEligibility()->children as $serviceEligibilityType) {
            if ($serviceEligibilityTypeOptionIds = $serviceEligibilityType->filterDescendants($eligibilityIds)) {
                $serviceEligibilityTypeNames = $eligibilities->filter(function ($eligibility) use ($serviceEligibilityTypeOptionIds) {
                    return in_array($eligibility->id, $serviceEligibilityTypeOptionIds);
                })->pluck('name')->all();

                $serviceEligibilityTypeAllName = $serviceEligibilityType->name . ' All';

                $this->query['query']['bool']['filter'][] = [
                    'terms' => [
                        'service_eligibilities.keyword' => array_merge($serviceEligibilityTypeNames, [$serviceEligibilityTypeAllName]),
                    ],
                ];

                if (empty($this->query['query']['bool']['must']['bool']['minimum_should_match'])) {
                    $this->query['query']['bool']['must']['bool']['minimum_should_match'] = 1;
                } else {
                    $this->query['query']['bool']['must']['bool']['minimum_should_match']++;
                }

                foreach ($serviceEligibilityTypeNames as $serviceEligibilityTypeName) {
                    $this->query['query']['bool']['must']['bool']['should'][] = [
                        'term' => [
                            'service_eligibilities.keyword' => [
                                'value' => $serviceEligibilityTypeName,
                            ],
                        ],
                    ];
                }

                $this->query['query']['bool']['must']['bool']['should'][] = [
                    'match' => [
                        'service_eligibilities' => [
                            'query' => $serviceEligibilityTypeAllName,
                            'boost' => 0,
                        ],
                    ],
                ];
            }
        }

        return $this;
    }

    /**
     * @param int $distance
     * @param string $units
     * @return string
     */
    protected function distance(int $distance, string $units = self::MILES): string
    {
        return $distance . $units;
    }

    /**
     * Returns the underlying query. Only intended for use in testing.
     *
     * @return array
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * @param int|null $page
     * @param int|null $perPage
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function paginate(int $page = null, int $perPage = null): AnonymousResourceCollection
    {
        $page = page($page);
        $perPage = per_page($perPage);

        $this->query['from'] = ($page - 1) * $perPage;
        $this->query['size'] = $perPage;

        $response = Service::searchRaw($this->query);

        $this->logMetrics($response);

        return $this->toResource($response, true, $page);
    }

    /**
     * @param int|null $perPage
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function get(int $perPage = null): AnonymousResourceCollection
    {
        $this->query['size'] = per_page($perPage);

        $response = Service::searchRaw($this->query);
        $this->logMetrics($response);

        return $this->toResource($response, false);
    }

    /**
     * @param array $response
     * @param bool $paginate
     * @param int|null $page
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    protected function toResource(array $response, bool $paginate = true, int $page = null)
    {
        // Extract the hits from the array.
        $hits = $response['hits']['hits'];

        // Get all of the ID's for the services from the hits.
        $serviceIds = collect($hits)->map->_id->toArray();

        // Implode the service ID's so we can sort by them in database.
        $serviceIdsImploded = implode("','", $serviceIds);
        $serviceIdsImploded = "'$serviceIdsImploded'";

        // Check if the query has been ordered by distance.
        $isOrderedByDistance = isset($this->query['sort']);

        // Create the query to get the services, and keep ordering from Elasticsearch.
        $services = Service::query()
            ->with('serviceLocations.location')
            ->whereIn('id', $serviceIds)
            ->orderByRaw("FIELD(id,$serviceIdsImploded)")
            ->get();

        // Order the fetched service locations by distance.
        // TODO: Potential solution to the order nested locations in Elasticsearch: https://stackoverflow.com/a/43440405
        if ($isOrderedByDistance) {
            $services = $this->orderServicesByLocation($services);
        }

        // If paginated, then create a new pagination instance.
        if ($paginate) {
            $services = new LengthAwarePaginator(
                $services,
                $response['hits']['total']['value'],
                config('local.pagination_results'),
                $page,
                ['path' => Paginator::resolveCurrentPath()]
            );
        }

        return ServiceResource::collection($services);
    }

    /**
     * @param array $response
     * @return \App\Search\ElasticsearchPageSearch
     */
<<<<<<< HEAD:app/Search/ElasticsearchServiceSearch.php
    protected function logMetrics(array $response): ServiceSearch
=======
    protected function logMetrics(array $response): PageSearch
>>>>>>> fcb21b09... Created entities for page search:app/Search/ElasticsearchPageSearch.php
    {
        SearchHistory::create([
            'query' => $this->query,
            'count' => $response['hits']['total']['value'],
        ]);

        return $this;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection $services
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function orderServicesByLocation(Collection $services): Collection
    {
        return $services->each(function (Service $service) {
            $service->serviceLocations = $service->serviceLocations->sortBy(function (ServiceLocation $serviceLocation) {
                $location = $this->query['sort'][0]['_geo_distance']['service_locations.location'];
                $location = new Coordinate($location['lat'], $location['lon']);

                return $location->distanceFrom($serviceLocation->location->toCoordinate());
            });
        });
    }
}
