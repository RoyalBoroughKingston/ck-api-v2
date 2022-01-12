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
use App\Models\File;
use App\Models\Page;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\Filter;
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
                Filter::exact('id'),
                Filter::exact('parent_id', 'parent_uuid'),
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
            $page = Page::create(
                [
                    'title' => $request->title,
                    'content' => sanitize_markdown($request->content),
                    'image_file_id' => $request->image_file_id,
                ],
                $request->parent_id ? Page::find($request->parent_id) : null
            );

            if ($request->filled('order')) {
                $nextSibling = $page->siblingAtIndex($request->order)->first();

                $page->insertBeforeNode($nextSibling);
            }

            if ($request->filled('image_file_id')) {
                /** @var \App\Models\File $file */
                $file = File::findOrFail($request->image_file_id)->assigned();

                // Create resized version for common dimensions.
                foreach (config('ck.cached_image_dimensions') as $maxDimension) {
                    $file->resizedVersion($maxDimension);
                }
            }

            $page->load('parent', 'children');

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
            ->with(['parent', 'children'])
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
            $page->content = $request->has('content') ?
            sanitize_markdown($request->input('content')) :
            $page->content;

            // Attach to parent and inherit disabled if set
            if ($request->input('parent_id', $page->parent_id) !== $page->parent_id) {
                $parent = Page::find($request->input('parent_id'));
                if (!$parent->enabled) {
                    $page->enabled = $parent->enabled;
                    Page::whereIn('id', $page->descendants->pluck('id'))
                        ->update(['enabled' => $parent->enabled]);
                }
                $page->appendToNode($parent);
            }

            // Order
            if ($request->has('order')) {
                $siblingAtIndex = $page->siblingAtIndex($request->order)->first();

                $siblingAtIndex->getLft() > $page->getLft() ?
                $page->afterNode($siblingAtIndex) :
                $page->beforeNode($siblingAtIndex);
            }

            // Disable cascades into children, but enable does not
            $enabled = $request->input('enabled', $page->enabled);
            if (!$enabled && $page->enabled) {
                Page::whereIn('id', $page->descendants->pluck('id'))
                    ->update(['enabled' => $enabled]);
            }
            $page->enabled = $enabled;

            // Update model so far
            $page->save();

            // Image File
            if ($request->input('image_file_id', $page->image_file_id) !== $page->image_file_id) {
                $currentImage = $page->image;

                if ($request->input('image_file_id')) {
                    /** @var \App\Models\File $file */
                    $file = File::findOrFail($request->image_file_id)->assigned();

                    // Create resized version for common dimensions.
                    foreach (config('ck.cached_image_dimensions') as $maxDimension) {
                        $file->resizedVersion($maxDimension);
                    }
                }

                $page->update([
                    'image_file_id' => $request->input('image_file_id'),
                ]);

                if ($currentImage) {
                    $currentImage->deleteFromDisk();
                    $currentImage->delete();
                }
            }

            event(EndpointHit::onUpdate($request, "Updated page [{$page->id}]", $page));

            return new pageResource($page->fresh(['parent', 'children']));
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
