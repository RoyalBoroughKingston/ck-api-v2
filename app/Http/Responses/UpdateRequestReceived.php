<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use App\Models\UpdateRequest;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Response;

class UpdateRequestReceived implements Responsable
{
    /**
     * @var \App\Models\UpdateRequest
     */
    protected $updateRequest;

    /**
     * @var int
     */
    protected $code;

    /**
     * UpdateRequestReceived constructor.
     */
    public function __construct(UpdateRequest $updateRequest, int $code = Response::HTTP_OK)
    {
        $this->updateRequest = $updateRequest;
        $this->code = $code;
    }

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function toResponse(Request $request): Response
    {
        return response()->json([
            'message' => $this->updateRequest->isApproved() ? __('updates.pre-approved') : __('updates.pending', ['appname' => config('app.name', 'Laravel')]),
            'id' => $this->updateRequest->id,
            'data' => $this->updateRequest->getUpdateable()->getData(
                $this->updateRequest->data
            ),
        ], $this->code);
    }
}
