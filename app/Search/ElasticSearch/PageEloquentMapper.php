<?php

declare(strict_types=1);

namespace App\Search\ElasticSearch;

use App\Contracts\EloquentMapper;
use App\Http\Resources\PageResource;
use App\Models\Page;
use App\Models\SearchHistory;
use ElasticScoutDriverPlus\Decorators\SearchResult;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class PageEloquentMapper implements EloquentMapper
{
    public function paginate(array $esQuery, int $page = null, int $perPage = null): AnonymousResourceCollection
    {
        $page = page($page);
        $perPage = per_page($perPage);

        $response = Page::searchQuery($esQuery)
            ->size($perPage)
            ->from(($page - 1) * $perPage)
            ->execute();

        $this->logMetrics($esQuery, $response);

        // If paginated, then create a new pagination instance.
        $pages = new LengthAwarePaginator(
            $response->models(),
            $response->total(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );

        return PageResource::collection($pages);
    }

    public function logMetrics(array $esQuery, SearchResult $response): void
    {
        SearchHistory::create([
            'query' => $esQuery,
            'count' => $response->total(),
        ]);
    }
}
