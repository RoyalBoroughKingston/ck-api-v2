<?php

declare(strict_types=1);

namespace App\Search\ElasticSearch;

use App\Models\Page;
use App\Search\PageCriteriaQuery;

class PageQueryBuilder
{
    /**
     * @var array
     */
    protected $esQuery;

    public function __construct()
    {
        $this->esQuery = [
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
        ];
    }

    public function build(PageCriteriaQuery $query, int $page = null, int $perPage = null): array
    {
        $page = page($page);
        $perPage = per_page($perPage);

        $this->applyFrom($page, $perPage);
        $this->applySize($perPage);

        if ($query->hasQuery()) {
            $this->applyQuery($query->getQuery());
        }

        $this->applyStatus(Page::ENABLED);

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

    protected function applyStatus(bool $enabled): void
    {
        $this->addFilter('enabled', $enabled);
    }

    protected function applyQuery(string $query): void
    {
        $this->addMatch('title', $query, 3);
        $this->addMatch('content.introduction.title', $query, 2);
        $this->addMatch('content.introduction.content', $query);
        $this->addMatch('content.about.title', $query, 2);
        $this->addMatch('content.about.content', $query);
        $this->addMatch('content.info_pages.title', $query, 2);
        $this->addMatch('content.info_pages.content', $query);
        $this->addMatch('content.collections.title', $query, 2);
        $this->addMatch('content.collections.content', $query);
        $this->addMatch('collection_categories', $query);
        $this->addMatch('collection_personas', $query);

        if (empty($this->query['query']['bool']['must']['bool']['minimum_should_match'])) {
            $this->query['query']['bool']['must']['bool']['minimum_should_match'] = 1;
        }
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
        $this->esQuery['query']['bool']['must']['bool']['should'][] = [
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
        $this->esQuery['query']['bool']['must']['bool']['should'][] = [
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
        $this->esQuery['query']['bool']['filter']['bool']['must'][] = [
            $type => [
                $field => $value,
            ],
        ];
    }
}
