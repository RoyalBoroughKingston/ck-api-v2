<?php

declare(strict_types=1);

namespace App\Search\ElasticSearch;

use App\Models\Service;
use App\Models\Taxonomy;
use App\Models\Collection;
use App\Support\Coordinate;
use InvalidArgumentException;
use App\Search\ServiceCriteriaQuery;

class ServiceQueryBuilder
{
    const ORDER_RELEVANCE = 'relevance';

    const ORDER_DISTANCE = 'distance';

    /**
     * @var array
     */
    protected $esQuery;

    public function __construct()
    {
        $this->esQuery = [
            'query' => [
                'function_score' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                'bool' => [
                                    'should' => [],
                                ],
                            ],
                            'filter' => [
                                'bool' => [
                                    'must' => []
                                ]
                            ],
                        ],
                    ],
                    'functions' => [
                        [
                            'field_value_factor' => [
                                'field' => 'score',
                                'missing' => 1,
                                'modifier' => 'ln1p',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function build(ServiceCriteriaQuery $query, int $page = null, int $perPage = null): array
    {
        $page = page($page);
        $perPage = per_page($perPage);

        $this->applyFrom($page, $perPage);
        $this->applySize($perPage);
        $this->applyStatus(Service::STATUS_ACTIVE);

        if ($query->hasQuery()) {
            $this->applyQuery($query->getQuery());
        }

        if ($query->hasCategories()) {
            $this->applyCategories($query->getCategories());
        }

        if ($query->hasPersonas()) {
            $this->applyPersonas($query->getPersonas());
        }

        if ($query->hasWaitTime()) {
            $this->applyWaitTime($query->getWaitTime());
        }

        if ($query->hasIsFree()) {
            $this->applyIsFree($query->getIsFree());
        }

        if ($query->hasEligibilities()) {
            $this->applyEligibilities($query->getEligibilities());
        }

        if ($query->hasLocation()) {
            $this->applyLocation($query->getLocation(), $query->getDistance());

            if ($query->hasOrder()) {
                $this->applyOrder($query->getOrder(), $query->getLocation());
            }
        }

        // dump($this->esQuery);

        return $this->esQuery;
    }

    protected function applyFrom(int $page, int $perPage): void
    {
        $this->esQuery['from'] = ($page - 1) * $perPage;
    }

    protected function applySize(int $perPage): void
    {
        $this->esQuery['size'] = $perPage;
    }

    protected function applyStatus(string $status): void
    {
        $this->addFilter('status', $status);
    }

    protected function applyQuery(string $query): void
    {
        $this->addMatch('name', $query, 3);
        $this->addMatch('organisation_name', $query, 3);
        $this->addMatch('intro', $query, 2);
        $this->addMatch('description', $query, 1.5);
        $this->addMatch('taxonomy_categories', $query);

        if (empty($this->query['query']['function_score']['query']['bool']['must']['bool']['minimum_should_match'])) {
            $this->query['query']['function_score']['query']['bool']['must']['bool']['minimum_should_match'] = 1;
        }
    }

    protected function applyCategories(array $categorySlugs): void
    {
        $categoryNames = Collection::query()
            ->whereIn('slug', $categorySlugs)
            ->pluck('name')
            ->all();

        $this->addFilter('collection_categories', $categoryNames);
    }

    protected function applyPersonas(array $personaSlugs): void
    {
        $personaNames = Collection::query()
            ->whereIn('slug', $personaSlugs)
            ->pluck('name')
            ->all();

        $this->addFilter('collection_personas', $personaNames);
    }

    /**
     * @param string $waitTime
     * @return null
     */
    protected function applyWaitTime(string $waitTime): void
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

        $this->addFilter('wait_time', $criteria);
    }

    protected function applyIsFree(bool $isFree): void
    {
        $this->addFilter('is_free', $isFree);
    }

    protected function applyEligibilities(array $eligibilityNames): void
    {
        $eligibilities = Taxonomy::whereIn('name', $eligibilityNames)->get();
        $eligibilityIds = $eligibilities->pluck('id')->all();

        foreach (Taxonomy::serviceEligibility()->children as $serviceEligibilityType) {
            if ($serviceEligibilityTypeOptionIds = $serviceEligibilityType->filterDescendants($eligibilityIds)) {
                $serviceEligibilityTypeNames = $eligibilities->filter(function ($eligibility) use ($serviceEligibilityTypeOptionIds) {
                    return in_array($eligibility->id, $serviceEligibilityTypeOptionIds);
                })->pluck('name')->all();

                $serviceEligibilityTypeAllName = $serviceEligibilityType->name . ' All';

                $this->addFilter('service_eligibilities.keyword', array_merge($serviceEligibilityTypeNames, [$serviceEligibilityTypeAllName]));

                if (empty($this->esQuery['query']['function_score']['query']['bool']['must']['bool']['minimum_should_match'])) {
                    $this->esQuery['query']['function_score']['query']['bool']['must']['bool']['minimum_should_match'] = 1;
                } else {
                    $this->esQuery['query']['function_score']['query']['bool']['must']['bool']['minimum_should_match']++;
                }

                foreach ($serviceEligibilityTypeNames as $serviceEligibilityTypeName) {
                    $this->esQuery['query']['function_score']['query']['bool']['must']['bool']['should'][] = [
                        'term' => [
                            'service_eligibilities.keyword' => [
                                'value' => $serviceEligibilityTypeName,
                            ],
                        ],
                    ];
                }

                $this->addMatch('service_eligibilities', $serviceEligibilityTypeAllName, 0);
            }
        }
    }

    protected function applyLocation(Coordinate $coordinate, ?int $distance): void
    {
        // Add filter for listings within a search distance miles radius, or national.
        $this->esQuery['query']['function_score']['query']['bool']['filter']['bool']['must'][] = [
            'nested' => [
                'path' => 'service_locations',
                'query' => [
                    'geo_distance' => [
                        'distance' => $distance? $distance . 'mi' : config('local.search_distance') . 'mi',
                        'service_locations.location' => $coordinate->toArray(),
                    ],
                ],
            ],
        ];

        // Apply scoring for favouring results closer to the coordinate.
        $this->esQuery['query']['function_score']['functions'][] = [
            'gauss' => [
                'service_locations.location' => [
                    'origin' => $coordinate->toArray(),
                    'scale' => '1mi',
                ],
            ],
        ];
    }

    protected function applyOrder(string $order, Coordinate $coordinate): void
    {
        if ($order === static::ORDER_DISTANCE) {
            $this->esQuery['sort'] = [
                [
                    '_geo_distance' => [
                        'service_locations.location' => $coordinate->toArray(),
                        'nested_path' => 'service_locations',
                    ],
                ],
            ];
        }
    }

    /**
     * Add a match query
     *
     * @param string $field
     * @param string $term
     * @param int $boost
     * @param mixed $fuzziness
     * @return null
     */
    protected function addMatch(string $field, string $term, $boost = 1, $fuzziness = 'AUTO'): void
    {
        $this->esQuery['query']['function_score']['query']['bool']['must']['bool']['should'][] = [
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
     * Add a match_phrase query
     * @param string $field
     * @param string $term
     * @param int $boost
     * @return void
     */
    protected function addMatchPhrase(string $field, string $term, $boost = 1): void
    {
        $this->esQuery['query']['function_score']['query']['bool']['must']['bool']['should'][] = [
            'match_phrase' => [
                $field => [
                    'query' => $term,
                    'boost' => $boost,
                ],
            ],
        ];
    }

    /**
     * Add a filter
     *
     * @param string $field
     * @param mixed $value
     * @return void
     **/
    public function addFilter(string $field, $value): void
    {
        $type = is_array($value)? 'terms' : 'term';
        $this->esQuery['query']['function_score']['query']['bool']['filter']['bool']['must'][] = [
            $type => [
                $field => $value,
            ],
        ];
    }
}
