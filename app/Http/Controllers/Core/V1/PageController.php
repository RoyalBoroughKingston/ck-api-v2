<?php

namespace App\Http\Controllers\Core\V1;

use App\Events\EndpointHit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Page\DestroyRequest;
use App\Http\Requests\Page\IndexRequest;
use App\Http\Requests\Page\ShowRequest;
use App\Http\Requests\Page\StoreRequest;
use App\Http\Requests\Page\UpdateRequest;
use App\Http\Resources\PageResource;
use App\Http\Responses\ResourceDeleted;
use App\Models\Page;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PageController extends Controller
{
    /**
     * PageController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:api')->except('index', 'show');
    }

    /**
     * Display a listing of the resource.
     *
     * @param \App\Http\Requests\Page\IndexRequest $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(IndexRequest $request)
    {
        $orderByCol = (new Page())->getLftName();
        $baseQuery = Page::query()
            ->with('parent')
            ->orderBy($orderByCol);

        if (!$request->user('api') || !$request->user('api')->isGlobalAdmin()) {
            $baseQuery->where('enabled', true);
        }

        $pages = QueryBuilder::for($baseQuery)
            ->allowedFilters([
                AllowedFilter::exact('id'),
                AllowedFilter::exact('parent_id', 'parent_uuid'),
                AllowedFilter::exact('page_type'),
                'title',
            ])
            ->allowedSorts($orderByCol, 'title')
            ->defaultSort($orderByCol)
            ->get();

        event(EndpointHit::onRead($request, 'Viewed all information pages'));

        return PageResource::collection($pages);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \App\Http\Requests\Page\StoreRequest $request
     * @return \App\Http\Resources\OrganisationResource
     */
    public function store(StoreRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $parent = $request->filled('parent_id') ? Page::find($request->parent_id) : null;
            $page = Page::make(
                [
                    'title' => $request->input('title'),
                    'content' => $request->input('content'),
                    'page_type' => $request->input('page_type', Page::PAGE_TYPE_INFORMATION),
                ],
                $parent
            );

            // Update relationships
            $page->updateParent($parent ? $parent->id : null)
                ->updateStatus($request->input('enabled'))
                ->updateOrder($request->input('order'))
                ->updateImage($request->input('image_file_id'))
                ->updateCollections($request->input('collections'));

            // Update model so far
            $page->save();

            $page->load('parent', 'children', 'collectionCategories', 'collectionPersonas');

            event(EndpointHit::onCreate($request, "Created page [{$page->id}]", $page));

            return new PageResource($page);
        });
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Http\Requests\Page\ShowRequest $request
     * @param \App\Models\Page $page
     * @return \App\Http\Resources\OrganisationResource
     */
    public function show(ShowRequest $request, Page $page)
    {
        $baseQuery = Page::query()
            ->with(['parent', 'children', 'collectionCategories', 'collectionPersonas'])
            ->where('id', $page->id);

        $page = QueryBuilder::for($baseQuery)
            ->firstOrFail();

        event(EndpointHit::onRead($request, "Viewed page [{$page->id}]", $page));

        return new pageResource($page);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \App\Http\Requests\Page\UpdateRequest $request
     * @param \App\Models\Page $page
     * @return \App\Http\Resources\OrganisationResource
     */
    public function update(UpdateRequest $request, Page $page)
    {
        return DB::transaction(function () use ($request, $page) {
            // Core fields
            $page->title = $request->input('title', $page->title);
            $page->page_type = $request->input('page_type', $page->page_type);
            if ($request->filled('content')) {
                $page->content = $request->input('content', $page->content);
            }

            // Update relationships
            $page->updateParent($request->has('parent_id') ? $request->parent_id : false)
                ->updateStatus($request->input('enabled'))
                ->updateOrder($request->input('order'))
                ->updateImage($request->has('image_file_id') ? $request->image_file_id : $page->image_file_id)
                ->updateCollections($request->input('collections'));

            // Update model so far
            $page->save();

            event(EndpointHit::onUpdate($request, "Updated page [{$page->id}]", $page));

            return new pageResource($page->fresh(['parent', 'children', 'collectionCategories', 'collectionPersonas']));
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Http\Requests\Organisation\DestroyRequest $request
     * @param \App\Models\Page $page
     * @return \Illuminate\Http\Response
     */
    public function destroy(DestroyRequest $request, Page $page)
    {
        return DB::transaction(function () use ($request, $page) {
            event(EndpointHit::onDelete($request, "Deleted page [{$page->id}]", $page));

            $page->delete();

            return new ResourceDeleted('page');
        });
    }
}
