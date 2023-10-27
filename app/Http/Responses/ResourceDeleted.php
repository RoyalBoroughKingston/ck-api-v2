<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
     */
    public function toResponse(Request $request): Response
    {
        return response()->json(['message' => "The {$this->resource} has been successfully deleted"]);
    }
}
