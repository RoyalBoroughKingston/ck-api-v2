<?php

namespace App\Http\Controllers\Core\V1;

use App\Models\File;
use App\Events\EndpointHit;
use Illuminate\Http\Request;
use App\Models\InformationPage;
use Spatie\QueryBuilder\Filter;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\QueryBuilder;
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

        if (! $request->user() || ! $request->user()->isGlobalAdmin()) {
            $baseQuery->where('enabled', true);
        }

        $informationPages = QueryBuilder::for($baseQuery)
            ->allowedFilters([
                Filter::exact('id'),
                Filter::exact('parent_uuid'),
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
     * @param \App\Http\Requests\Organisation\StoreRequest $request
     * @return \App\Http\Resources\OrganisationResource
     */
    public function store(StoreRequest $request)
    {
        $informationPage = InformationPage::create(
            [
                'title' => $request->title,
                'content' => sanitize_markdown($request->content),
                'image_file_id' => $request->image_file_id,
            ],
            InformationPage::find($request->parent_id)
        );

        if ($request->filled('order')) {
            $nextSibling = $informationPage->siblingAtIndex($request->order)->first();

            $informationPage->insertBeforeNode($nextSibling);
        }

        if ($request->filled('image_file_id')) {
            /** @var \App\Models\File $file */
            $file = File::findOrFail($request->image_file_id)->assigned();

            // Create resized version for common dimensions.
            foreach (config('ck.cached_image_dimensions') as $maxDimension) {
                $file->resizedVersion($maxDimension);
            }
        }

        $informationPage->load('parent', 'children');

        event(EndpointHit::onCreate($request, "Created information page [{$informationPage->id}]", $informationPage));

        return new InformationPageResource($informationPage);
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Http\Requests\Organisation\ShowRequest $request
     * @param  \App\InformationPage  $informationPage
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
     * @param \App\Http\Requests\Organisation\UpdateRequest $request
     * @param  \App\InformationPage  $informationPage
     * @return \App\Http\Resources\OrganisationResource
     */
    public function update(UpdateRequest $request, InformationPage $informationPage)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Http\Requests\Organisation\DestroyRequest $request
     * @param  \App\InformationPage  $informationPage
     * @return \Illuminate\Http\Response
     */
    public function destroy(DestroyRequest $request, InformationPage $informationPage)
    {
        //
    }
}
