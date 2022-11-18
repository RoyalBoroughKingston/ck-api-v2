<?php

declare(strict_types=1);

namespace App\Search\ElasticSearch;

use App\Models\Collection;
use App\Search\EventCriteriaQuery;
use App\Support\Coordinate;
use Carbon\Carbon;

class EventQueryBuilder
{
    const ORDER_DISTANCE = 'distance';

    const ORDER_START = 'start_date';

    const ORDER_END = 'end_date';

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
                            'filter' => [],
                        ],
                    ],
                    'functions' => [],
                ],
            ],
        ];
    }

    public function build(EventCriteriaQuery $query, int $page = null, int $perPage = null): array
    {
        $page = page($page);
        $perPage = per_page($perPage);

        $this->applyFrom($page, $perPage);
        $this->applySize($perPage);

        if ($query->hasQuery()) {
            $this->applyQuery($query->getQuery());
        }

        if ($query->hasCategories()) {
            $this->applyCategories($query->getCategories());
        }

        if ($query->hasIsFree()) {
            $this->applyIsFree($query->getIsFree());
        }

        if ($query->hasIsVirtual()) {
            $this->applyIsVirtual($query->getIsVirtual());
        }

        if ($query->hasHasWheelchairAccess()) {
            $this->applyHasWheelchairAccess($query->getHasWheelchairAccess());
        }

        if ($query->hasHasInductionLoop()) {
            $this->applyHasInductionLoop($query->getHasInductionLoop());
        }

        if ($query->hasHasAccessibleToilet()) {
            $this->applyHasAccessibleToilet($query->getHasAccessibleToilet());
        }

        if ($query->hasStartsAfter()) {
            $this->applyStartsAfter($query->getStartsAfter());
        }

        $this->applyEndsBefore($query->getEndsBefore());

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

    protected function applyQuery(string $query): void
    {
        $this->addMatch('title', $query, 3);
        $this->addMatch('organisation_name', $query, 3);
        $this->addMatch('intro', $query, 2);
        $this->addMatch('description', $query, 1.5);
        $this->addMatch('taxonomy_categories', $query);

        if (empty($this->esQuery['query']['function_score']['query']['bool']['must']['bool']['minimum_should_match'])) {
            $this->esQuery['query']['function_score']['query']['bool']['must']['bool']['minimum_should_match'] = 1;
        }
    }

    protected function applyCategories(array $categorySlugs): void
    {
        $taxonomyNames = Collection::query()
            ->join('collection_taxonomies', 'collection_taxonomies.collection_id', '=', 'collections.id')
            ->join('taxonomies', 'collection_taxonomies.taxonomy_id', '=', 'taxonomies.id')
            ->whereIn('collections.slug', $categorySlugs)
            ->pluck('taxonomies.name')
            ->all();

        foreach ($taxonomyNames as $taxonomyName) {
            $this->addMatch('taxonomy_categories', $taxonomyName);
        }

        $categoryNames = Collection::query()
            ->whereIn('slug', $categorySlugs)
            ->pluck('name')
            ->all();

        $this->addFilter('collection_categories', $categoryNames);
    }

    protected function applyIsFree(bool $isFree): void
    {
        $this->addFilter('is_free', $isFree);
    }

    protected function applyIsVirtual(bool $isVirtual): void
    {
        $this->addFilter('is_virtual', $isVirtual);
    }

    /**
     * @param string $startsAfter
     */
    public function applyStartsAfter(?string $startsAfter): void
    {
        if ($startsAfter) {
            $this->esQuery['query']['function_score']['query']['bool']['filter'][] = [
                'range' => [
                    'start_date' => ['gte' => Carbon::parse($startsAfter)->toDateTimeLocalString()],
                ],
            ];
        }
    }

    /**
     * @param string $endsBefore
     */
    public function applyEndsBefore(?string $endsBefore): void
    {
        if ($endsBefore) {
            $this->esQuery['query']['function_score']['query']['bool']['filter'][] = [
                'range' => [
                    'end_date' => ['lte' => Carbon::parse($endsBefore)->toDateTimeLocalString()],
                ],
            ];
        } else {
            $this->esQuery['query']['function_score']['query']['bool']['filter'][] = [
                'range' => [
                    'end_date' => ['gte' => 'now/d'],
                ],
            ];
        }
    }

    protected function applyLocation(Coordinate $coordinate, ?int $distance): void
    {
        // Add filter for listings within a search distance miles radius.
        $this->esQuery['query']['function_score']['query']['bool']['filter'][] = [
            'nested' => [
                'path' => 'event_location',
                'query' => [
                    'geo_distance' => [
                        'distance' => $distance ? $distance . 'mi' : config('local.search_distance') . 'mi',
                        'event_location.location' => $coordinate->toArray(),
                    ],
                ],
            ],
        ];

        // Apply scoring for favouring results closer to the coordinate.
        $this->esQuery['query']['function_score']['functions'][] = [
            'gauss' => [
                'event_location.location' => [
                    'origin' => $coordinate->toArray(),
                    'scale' => '1mi',
                ],
            ],
        ];
    }

    protected function applyHasWheelchairAccess(bool $hasWheelchairAccess): void
    {
        $this->esQuery['query']['function_score']['query']['bool']['filter'][] = [
            'nested' => [
                'path' => 'event_location',
                'query' => [
                    'term' => [
                        'event_location.has_wheelchair_access' => $hasWheelchairAccess,
                    ],
                ],
            ],
        ];
    }

    protected function applyHasInductionLoop(bool $hasInductionLoop): void
    {
        $this->esQuery['query']['function_score']['query']['bool']['filter'][] = [
            'nested' => [
                'path' => 'event_location',
                'query' => [
                    'term' => [
                        'event_location.has_induction_loop' => $hasInductionLoop,
                    ],
                ],
            ],
        ];
    }

    protected function applyHasAccessibleToilet(bool $hasAccessibleToilet): void
    {
        $this->esQuery['query']['function_score']['query']['bool']['filter'][] = [
            'nested' => [
                'path' => 'event_location',
                'query' => [
                    'term' => [
                        'event_location.has_accessible_toilet' => $hasAccessibleToilet,
                    ],
                ],
            ],
        ];
    }

    protected function applyOrder(string $order, Coordinate $coordinate): void
    {
        $this->esQuery['sort'] = [];
        if ($order === static::ORDER_DISTANCE) {
            $this->esQuery['sort'][] = [
                '_geo_distance' => [
                    'event_location.location' => $coordinate->toArray(),
                    'nested_path' => 'event_location',
                ],
            ];
        } elseif ($order === static::ORDER_START) {
            $this->esQuery['sort'][] = [
                'start_date' => [
                    'order' => 'asc',
                ],
            ];
        } elseif ($order === static::ORDER_END) {
            $this->esQuery['sort'][] = [
                'end_date' => [
                    'order' => 'asc',
                ],
            ];
        }

        $this->esQuery['sort'][] = '_score';
    }

    /**
     * Add a match query.
     *
     * @param string $field
     * @param string $term
     * @param int $boost
     * @param mixed $fuzziness
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
     * Add a match_phrase query.
     * @param string $field
     * @param string $term
     * @param int $boost
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
     * Add a filter.
     *
     * @param string $field
     * @param mixed $value
     */
    public function addFilter(string $field, $value): void
    {
        $type = is_array($value) ? 'terms' : 'term';
        $this->esQuery['query']['function_score']['query']['bool']['filter'][] = [
            $type => [
                $field => $value,
            ],
        ];
    }
}
