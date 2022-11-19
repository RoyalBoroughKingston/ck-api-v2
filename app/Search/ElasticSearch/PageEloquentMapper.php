<?php

declare(strict_types=1);

namespace App\Search\ElasticSearch;

use App\Models\Page;
use App\Models\SearchHistory;
use App\Contracts\EloquentMapper;
use App\Http\Resources\PageResource;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PageEloquentMapper implements EloquentMapper
{
    public function paginate(array $esQuery): AnonymousResourceCollection
    {
        $response = Page::searchRaw($esQuery);

        $this->logMetrics($esQuery, $response);

        // Extract the hits from the array.
        $hits = $response['hits']['hits'];

        // Get all of the ID's for the pages from the hits.
        $pageIds = collect($hits)->map->_id->toArray();

        // Implode the service ID's so we can sort by them in database.
        $pageIdsImploded = implode("','", $pageIds);
        $pageIdsImploded = "'$pageIdsImploded'";

        // Create the query to get the pages, and keep ordering from Elasticsearch.
        $pages = Page::query()
            ->whereIn('id', $pageIds)
            ->orderByRaw("FIELD(id,$pageIdsImploded)")
            ->get();

        // If paginated, then create a new pagination instance.
        $pages = new LengthAwarePaginator(
            $pages,
            $response['hits']['total']['value'],
            $esQuery['size'],
            ($esQuery['from'] / $esQuery['size']) + 1,
            ['path' => Paginator::resolveCurrentPath()]
        );

        return PageResource::collection($pages);
    }

    protected function logMetrics(array $esQuery, array $response): void
    {
        SearchHistory::create([
            'query' => $esQuery,
            'count' => $response['hits']['total']['value'],
        ]);
    }
}
