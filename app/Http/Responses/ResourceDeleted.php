<?php

namespace App\Http\Responses;

use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Contracts\Support\Responsable;

class ResourceDeleted implements Responsable
{
    /**
     * @var string
     */
    protected $resource;

    /**
     * ResourceDeleted constructor.
     */
    public function __construct(string $resource)
    {
        $this->resource = $resource;
    }

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function toResponse(Request $request): Response
    {
        return response()->json(['message' => "The {$this->resource} has been successfully deleted"]);
    }
}
