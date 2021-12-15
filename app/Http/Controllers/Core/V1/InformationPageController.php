<?php

namespace App\Http\Controllers\Core\V1;

use App\Models\File;
use App\Events\EndpointHit;
use App\Models\InformationPage;
use Spatie\QueryBuilder\Filter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\QueryBuilder;
use App\Http\Responses\ResourceDeleted;
use App\Http\Resources\InformationPageResource;
use App\Http\Requests\InformationPage\ShowRequest;
use App\Http\Requests\InformationPage\IndexRequest;
use App\Http\Requests\InformationPage\StoreRequest;
use App\Http\Requests\InformationPage\UpdateRequest;
use App\Http\Requests\InformationPage\DestroyRequest;

class InformationPageController extends Controller
{
    /**
     * InformationPageController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:api')->except('index', 'show');
    }

    /**
     * Display a listing of the resource.
     *
     * @param \App\Http\Requests\InformationPage\IndexRequest $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(IndexRequest $request)
    {
        $orderByCol = (new InformationPage())->getLftName();
        $baseQuery = InformationPage::query()
            ->orderBy($orderByCol);

        if (!$request->user('api') || !$request->user('api')->isGlobalAdmin()) {
            $baseQuery->where('enabled', true);
        }

        $informationPages = QueryBuilder::for($baseQuery)
            ->allowedFilters([
                Filter::exact('id'),
                Filter::exact('parent_id', 'parent_uuid'),
                'title',
            ])
            ->allowedSorts($orderByCol, 'title')
            ->defaultSort($orderByCol)
            ->get();

        event(EndpointHit::onRead($request, 'Viewed all information pages'));

        return InformationPageResource::collection($informationPages);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \App\Http\Requests\InformationPage\StoreRequest $request
     * @return \App\Http\Resources\OrganisationResource
     */
    public function store(StoreRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $informationPage = InformationPage::create(
                [
                    'title' => $request->title,
                    'content' => sanitize_markdown($request->content),
                    'image_file_id' => $request->image_file_id,
                ],
                $request->parent_id ? InformationPage::find($request->parent_id) : null
            );

            if ($request->filled('order')) {
                $nextSibling = $informationPage->siblingAtIndex($request->order)->first();

                $informationPage->insertBeforeNode($nextSibling);
            }

            if ($request->filled('image_file_id')) {
                /** @var \App\Models\File $file */
                $file = File::findOrFail($request->image_file_id)->assigned();

                // Create resized version for common dimensions.
                foreach (config('local.cached_image_dimensions') as $maxDimension) {
                    $file->resizedVersion($maxDimension);
                }
            }

            $informationPage->load('parent', 'children');

            event(EndpointHit::onCreate($request, "Created information page [{$informationPage->id}]", $informationPage));

            return new InformationPageResource($informationPage);
        });
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Http\Requests\InformationPage\ShowRequest $request
     * @param \App\InformationPage $informationPage
     * @return \App\Http\Resources\OrganisationResource
     */
    public function show(ShowRequest $request, InformationPage $informationPage)
    {
        $baseQuery = InformationPage::query()
            ->with(['parent', 'children'])
            ->where('id', $informationPage->id);

        $page = QueryBuilder::for($baseQuery)
            ->firstOrFail();

        event(EndpointHit::onRead($request, "Viewed information page [{$page->id}]", $page));

        return new InformationpageResource($page);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \App\Http\Requests\InformationPage\UpdateRequest $request
     * @param \App\InformationPage $informationPage
     * @return \App\Http\Resources\OrganisationResource
     */
    public function update(UpdateRequest $request, InformationPage $informationPage)
    {
        return DB::transaction(function () use ($request, $informationPage) {
            // Core fields
            $informationPage->title = $request->input('title', $informationPage->title);
            $informationPage->content = $request->has('content') ?
            sanitize_markdown($request->input('content')) :
            $informationPage->content;

            // Parent
            if ($request->input('parent_id', $informationPage->parent_id) !== $informationPage->parent_id) {
                $parent = InformationPage::find($request->input('parent_id'));
                $informationPage->appendToNode($parent);
            }

            // Order
            if ($request->has('order')) {
                $siblingAtIndex = $informationPage->siblingAtIndex($request->order)->first();

                $siblingAtIndex->getLft() > $informationPage->getLft() ?
                $informationPage->afterNode($siblingAtIndex) :
                $informationPage->beforeNode($siblingAtIndex);
            }

            // Enabled
            $enabled = $request->input('enabled', $informationPage->enabled);
            if ($enabled != $informationPage->enabled) {
                $informationPage->enabled = $enabled;
                InformationPage::whereIn('id', $informationPage->descendants->pluck('id'))
                    ->update(['enabled' => $enabled]);
            }

            // Update model so far
            $informationPage->save();

            // Image File
            if ($request->input('image_file_id', $informationPage->image_file_id) !== $informationPage->image_file_id) {
                $currentImage = $informationPage->image;

                if ($request->input('image_file_id')) {
                    /** @var \App\Models\File $file */
                    $file = File::findOrFail($request->image_file_id)->assigned();

                    // Create resized version for common dimensions.
                    foreach (config('local.cached_image_dimensions') as $maxDimension) {
                        $file->resizedVersion($maxDimension);
                    }
                }

                $informationPage->update([
                    'image_file_id' => $request->input('image_file_id'),
                ]);

                if ($currentImage) {
                    $currentImage->deleteFromDisk();
                    $currentImage->delete();
                }
            }

            event(EndpointHit::onUpdate($request, "Updated information page [{$informationPage->id}]", $informationPage));

            return new InformationpageResource($informationPage->fresh(['parent', 'children']));
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Http\Requests\Organisation\DestroyRequest $request
     * @param \App\InformationPage $informationPage
     * @return \Illuminate\Http\Response
     */
    public function destroy(DestroyRequest $request, InformationPage $informationPage)
    {
        return DB::transaction(function () use ($request, $informationPage) {
            event(EndpointHit::onDelete($request, "Deleted information page [{$informationPage->id}]", $informationPage));

            $informationPage->delete();

            return new ResourceDeleted('information page');
        });
    }
}
