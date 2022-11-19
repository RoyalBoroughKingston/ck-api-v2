<?php

declare(strict_types=1);

namespace App\Search\ElasticSearch;

use App\Models\Page;
use App\Contracts\QueryBuilder;
use App\Search\SearchCriteriaQuery;

class PageQueryBuilder extends ElasticsearchQueryBuilder implements QueryBuilder
{
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

        $this->matchPath = 'query.bool.must.bool.should';
        $this->filterPath = 'query.bool.filter.bool.must';
    }

    public function build(SearchCriteriaQuery $query, int $page = null, int $perPage = null): array
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
}
