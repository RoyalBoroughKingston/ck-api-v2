<?php

declare(strict_types=1);

namespace App\Http\Controllers\Core\V1\Search;

use App\Contracts\PageSearch;
use App\Http\Requests\Search\Pages\Request;

class PageController
{
    /**
     * @param \App\Contracts\PageSearch $search
     * @param \App\Http\Requests\Search\Pages\Request $request
     * @return \Illuminate\Http\Pages\Json\AnonymousPageCollection
     */
    public function __invoke(PageSearch $search, Request $request)
    {
        // Apply query.
        if ($request->has('query')) {
            $search->applyQuery($request->input('query'));
        }

        // Apply order.
        $search->applyOrder('relevance');

        // Perform the search.
        return $search->paginate($request->page, $request->per_page);
    }
}
