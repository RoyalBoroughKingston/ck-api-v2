<?php

namespace App\Search;

use App\Contracts\PageSearch;
use App\Http\Resources\PageResource;
use App\Models\Page;
use App\Models\SearchHistory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class ElasticsearchPageSearch implements PageSearch
{
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
                                'enabled' => Page::ENABLED,
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
            'sort' => [
                '_score',
            ],
        ];
    }

    /**
     * @param string $term
     * @return \App\Search\ElasticsearchPageSearch
     */
    public function applyQuery(string $term): PageSearch
    {
        $should = &$this->query['query']['bool']['must']['bool']['should'];

        $should[] = $this->match('title', $term, 3);
        $should[] = $this->match('content.introduction.title', $term, 2);
        $should[] = $this->match('content.introduction.copy', $term);
        $should[] = $this->match('content.about.title', $term, 2);
        $should[] = $this->match('content.about.copy', $term);
        $should[] = $this->match('content.info_pages.title', $term, 2);
        $should[] = $this->match('content.info_pages.copy', $term);
        $should[] = $this->match('content.collections.title', $term, 2);
        $should[] = $this->match('content.collections.copy', $term);
        $should[] = $this->match('collection_categories', $term);
        $should[] = $this->match('collection_personas', $term);

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
     * @param string $order
     * @param \App\Support\Coordinate|null $location
     * @return \App\Search\ElasticsearchPageSearch
     */
    public function applyOrder(string $order): PageSearch
    {
        return $this;
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

        $response = Page::searchRaw($this->query);

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

        $response = Page::searchRaw($this->query);
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

        // Get all of the ID's for the pages from the hits.
        $pageIds = collect($hits)->map->_id->toArray();

        // Implode the page ID's so we can sort by them in database.
        $pageIdsImploded = implode("','", $pageIds);
        $pageIdsImploded = "'$pageIdsImploded'";

        // Create the query to get the pages, and keep ordering from Elasticsearch.
        $pages = Page::query()
            ->whereIn('id', $pageIds)
            ->orderByRaw("FIELD(id,$pageIdsImploded)")
            ->get();

        // If paginated, then create a new pagination instance.
        if ($paginate) {
            $pages = new LengthAwarePaginator(
                $pages,
                $response['hits']['total']['value'],
                config('local.pagination_results'),
                $page,
                ['path' => Paginator::resolveCurrentPath()]
            );
        }

        return PageResource::collection($pages);
    }

    /**
     * @param array $response
     * @return \App\Search\ElasticsearchPageSearch
     */
    protected function logMetrics(array $response): PageSearch
    {
        SearchHistory::create([
            'query' => $this->query,
            'count' => $response['hits']['total']['value'],
        ]);

        return $this;
    }
}
