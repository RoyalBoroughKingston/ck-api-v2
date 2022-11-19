<?php

namespace App\Search\ElasticSearch;

use App\Contracts\QueryBuilder;
use App\Models\Collection;
use App\Models\Service;
use App\Models\Taxonomy;
use App\Search\SearchCriteriaQuery;
use App\Support\Coordinate;
use InvalidArgumentException;

class ServiceQueryBuilder extends ElasticsearchQueryBuilder implements QueryBuilder
{
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
                                    'must' => [],
                                ],
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

        $this->matchPath = 'query.function_score.query.bool.must.bool.should';
        $this->filterPath = 'query.function_score.query.bool.filter.bool.must';
    }

    public function build(SearchCriteriaQuery $query, int $page = null, int $perPage = null): array
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

        return $this->esQuery;
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
                        'distance' => $distance ? $distance . 'mi' : config('local.search_distance') . 'mi',
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
}
