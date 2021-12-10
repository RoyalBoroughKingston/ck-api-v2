<?php

namespace App\Http\Controllers\Core\V1\InformationPage;

use App\Events\EndpointHit;
use App\Http\Controllers\Controller;
use App\Http\Requests\InformationPage\Image\ShowRequest;
use App\Models\File;
use App\Models\InformationPage;

class ImageController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @param \App\Http\Requests\InformationPage\Image\ShowRequest $request
     * @param \App\Models\InformationPage $informationPage
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     * @return \Illuminate\Http\Response
     */
    public function __invoke(ShowRequest $request, InformationPage $informationPage)
    {
        event(EndpointHit::onRead($request, "Viewed image for information page [{$informationPage->id}]", $informationPage));

        // Get the image file associated.
        $file = File::findOrFail($informationPage->image_file_id);

        // Return the file, or placeholder if the file is null.
        return $file->resizedVersion($request->max_dimension);
    }
}
