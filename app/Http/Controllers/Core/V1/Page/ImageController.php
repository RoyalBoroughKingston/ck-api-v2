<?php

namespace App\Http\Controllers\Core\V1\Page;

use App\Events\EndpointHit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Page\Image\ShowRequest;
use App\Models\File;
use App\Models\Page;

class ImageController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @param \App\Http\Requests\Page\Image\ShowRequest $request
     * @param \App\Models\Page $page
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     * @return \Illuminate\Http\Response
     */
    public function __invoke(ShowRequest $request, Page $page)
    {
        event(EndpointHit::onRead($request, "Viewed image for information page [{$page->id}]", $page));

        // Get the image file associated.
        $file = File::findOrFail($page->image_file_id);

        // Return the file, or placeholder if the file is null.
        return $file->resizedVersion($request->max_dimension);
    }
}
