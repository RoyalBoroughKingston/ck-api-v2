<?php

declare(strict_types=1);

namespace App\Search\ElasticSearch;

use Carbon\Carbon;
use App\Models\Collection;
use App\Support\Coordinate;
use Illuminate\Support\Arr;
use App\Contracts\QueryBuilder;
use App\Search\SearchCriteriaQuery;

class EventQueryBuilder extends ElasticsearchQueryBuilder implements QueryBuilder
{
    /**
     * @var array
     */
    protected $esQuery;

    public function __construct()
    {
        $this->esQuery = [
            'function_score' => [
                'query' => [
                    'bool' => [
                        'must' => [],
                        'should' => [],
                        'filter' => [],
                    ],
                ],
                'functions' => [],
            ],
        ];

        $this->mustPath = 'function_score.query.bool.must';
        $this->shouldPath = 'function_score.query.bool.should';
        $this->filterPath = 'function_score.query.bool.filter';
    }

    public function build(SearchCriteriaQuery $query): array
    {
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

        return $this->esQuery;
    }

    protected function applyQuery(string $query): void
    {
        $this->addMatch('title', $query, $this->shouldPath, 3);
        $this->addMatch('organisation_name', $query, $this->shouldPath, 3);
        $this->addMatch('intro', $query, $this->shouldPath, 2);
        $this->addMatch('description', $query, $this->shouldPath, 1.5);
        $this->addMatch('taxonomy_categories', $query, $this->shouldPath);

        $this->addMinimumShouldMatch();
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
     * @param  string  $startsAfter
     */
    public function applyStartsAfter(?string $startsAfter): void
    {
        if ($startsAfter) {
            $filters = Arr::get($this->esQuery, $this->filterPath);
            $filters[] = [
                'range' => [
                    'start_date' => ['gte' => Carbon::parse($startsAfter)->toDateTimeLocalString()],
                ],
            ];
            Arr::set($this->esQuery, $this->filterPath, $filters);
        }
    }

    /**
     * @param  string  $endsBefore
     */
    public function applyEndsBefore(?string $endsBefore): void
    {
        $filters = Arr::get($this->esQuery, $this->filterPath);
        if ($endsBefore) {
            $filters[] = [
                'range' => [
                    'end_date' => ['lte' => Carbon::parse($endsBefore)->toDateTimeLocalString()],
                ],
            ];
        } else {
            $filters[] = [
                'range' => [
                    'end_date' => ['gte' => 'now/d'],
                ],
            ];
        }
        Arr::set($this->esQuery, $this->filterPath, $filters);
    }

    protected function applyLocation(Coordinate $coordinate, ?int $distance): void
    {
        $filters = Arr::get($this->esQuery, $this->filterPath);
        // Add filter for listings within a search distance miles radius.
        $filters[] = [
            'nested' => [
                'path' => 'event_location',
                'query' => [
                    'geo_distance' => [
                        'distance' => $distance ? $distance.'mi' : config('local.search_distance').'mi',
                        'event_location.location' => $coordinate->toArray(),
                    ],
                ],
            ],
        ];
        Arr::set($this->esQuery, $this->filterPath, $filters);

        // Apply scoring for favouring results closer to the coordinate.
        $this->esQuery['function_score']['functions'][] = [
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
        $filters = Arr::get($this->esQuery, $this->filterPath);
        $filters[] = [
            'nested' => [
                'path' => 'event_location',
                'query' => [
                    'term' => [
                        'event_location.has_wheelchair_access' => $hasWheelchairAccess,
                    ],
                ],
            ],
        ];
        Arr::set($this->esQuery, $this->filterPath, $filters);
    }

    protected function applyHasInductionLoop(bool $hasInductionLoop): void
    {
        $filters = Arr::get($this->esQuery, $this->filterPath);
        $filters[] = [
            'nested' => [
                'path' => 'event_location',
                'query' => [
                    'term' => [
                        'event_location.has_induction_loop' => $hasInductionLoop,
                    ],
                ],
            ],
        ];
        Arr::set($this->esQuery, $this->filterPath, $filters);
    }

    protected function applyHasAccessibleToilet(bool $hasAccessibleToilet): void
    {
        $filters = Arr::get($this->esQuery, $this->filterPath);
        $filters[] = [
            'nested' => [
                'path' => 'event_location',
                'query' => [
                    'term' => [
                        'event_location.has_accessible_toilet' => $hasAccessibleToilet,
                    ],
                ],
            ],
        ];
        Arr::set($this->esQuery, $this->filterPath, $filters);
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

    protected function addMinimumShouldMatch()
    {
        $bool = Arr::get($this->esQuery, 'function_score.query.bool');
        if (empty($bool['minimum_should_match'])) {
            $bool['minimum_should_match'] = 1;
        } else {
            $bool['minimum_should_match']++;
        }
        Arr::set($this->esQuery, 'function_score.query.bool', $bool);
    }
}
