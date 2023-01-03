<?php

declare(strict_types=1);

namespace App\Search\ElasticSearch;

use Illuminate\Support\Arr;

abstract class ElasticsearchQueryBuilder
{
    const ORDER_RELEVANCE = 'relevance';

    const ORDER_DISTANCE = 'distance';

    const ORDER_START = 'start_date';

    const ORDER_END = 'end_date';

    /**
     * Elasticsearch query array.
     *
     * @var array
     */
    protected $esQuery;

    /**
     * Path to add match and match_phrase queries.
     *
     * @var array
     */
    protected $matchPath;

    /**
     * Path to add filter queries.
     *
     * @var array
     */
    protected $filterPath;

    /**
     * Add a from limit.
     *
     * @param  int  $page
     * @param  int  $perPage
     */
    protected function applyFrom(int $page, int $perPage): void
    {
        $this->esQuery['from'] = ($page - 1) * $perPage;
    }

    /**
     * Add a size limit.
     *
     * @param  int  $perPage
     */
    protected function applySize(int $perPage): void
    {
        $this->esQuery['size'] = $perPage;
    }

    /**
     * Add a match query.
     *
     * @param  string  $field
     * @param  string  $term
     * @param  int  $boost
     * @param  mixed  $fuzziness
     */
    protected function addMatch(string $field, string $term, $boost = 1, $fuzziness = 'AUTO'): void
    {
        $matches = Arr::get($this->esQuery, $this->matchPath);
        $matches[] = [
            'match' => [
                $field => [
                    'query' => $term,
                    'boost' => $boost,
                    'fuzziness' => $fuzziness,
                ],
            ],
        ];
        Arr::set($this->esQuery, $this->matchPath, $matches);
    }

    /**
     * Add a match_phrase query.
     *
     * @param  string  $field
     * @param  string  $term
     * @param  int  $boost
     */
    protected function addMatchPhrase(string $field, string $term, $boost = 1): void
    {
        $matches = Arr::get($this->esQuery, $this->matchPath);
        $matches[] = [
            'match_phrase' => [
                $field => [
                    'query' => $term,
                    'boost' => $boost,
                ],
            ],
        ];
        Arr::set($this->esQuery, $this->matchPath, $matches);
    }

    /**
     * Add a filter.
     *
     * @param  string  $field
     * @param  mixed  $value
     */
    public function addFilter(string $field, $value): void
    {
        $filters = Arr::get($this->esQuery, $this->filterPath);
        $type = is_array($value) ? 'terms' : 'term';
        $filters[] = [
            $type => [
                $field => $value,
            ],
        ];
        Arr::set($this->esQuery, $this->filterPath, $filters);
    }
}
