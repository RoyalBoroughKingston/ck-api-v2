<?php

declare(strict_types=1);

namespace App\Search\ElasticSearch;

use App\Contracts\QueryBuilder;
use App\Models\Page;
use App\Search\SearchCriteriaQuery;
use ElasticScoutDriverPlus\Builders\SearchRequestBuilder;
use Illuminate\Support\Arr;

class PageQueryBuilder extends ElasticsearchQueryBuilder implements QueryBuilder
{
    public function __construct()
    {
        $this->esQuery = [
            'bool' => [
                'must' => [],
                'should' => [],
                'filter' => [],
            ],
        ];

        $this->mustPath = 'bool.must';
        $this->shouldPath = 'bool.should';
        $this->filterPath = 'bool.filter';
    }

    public function build(SearchCriteriaQuery $query, int $page = null, int $perPage = null): SearchRequestBuilder
    {
        if ($query->hasQuery()) {
            $this->applyQuery($query->getQuery());
        }

        $this->applyStatus(Page::ENABLED);

        return Page::searchQuery($this->esQuery)
            ->size($perPage)
            ->from(($page - 1) * $perPage);
    }

    protected function applyStatus(bool $enabled): void
    {
        $this->addFilter('enabled', $enabled);
    }

    protected function applyQuery(string $query): void
    {
        $this->addMatch('title', $query, $this->shouldPath, 3);
        $this->addMatch('content.introduction.title', $query, $this->shouldPath, 2);
        $this->addMatch('content.introduction.content', $query, $this->shouldPath);
        $this->addMatch('content.about.title', $query, $this->shouldPath, 2);
        $this->addMatch('content.about.content', $query, $this->shouldPath);
        $this->addMatch('content.info_pages.title', $query, $this->shouldPath, 2);
        $this->addMatch('content.info_pages.content', $query, $this->shouldPath);
        $this->addMatch('content.collections.title', $query, $this->shouldPath, 2);
        $this->addMatch('content.collections.content', $query, $this->shouldPath);
        $this->addMatch('collection_categories', $query, $this->shouldPath);
        $this->addMatch('collection_personas', $query, $this->shouldPath);

        $this->addMinimumShouldMatch();
    }

    protected function addMinimumShouldMatch()
    {
        $bool = Arr::get($this->esQuery, 'bool');
        if (empty($bool['minimum_should_match'])) {
            $bool['minimum_should_match'] = 1;
        } else {
            $bool['minimum_should_match']++;
        }
        Arr::set($this->esQuery, 'bool', $bool);
    }
}
