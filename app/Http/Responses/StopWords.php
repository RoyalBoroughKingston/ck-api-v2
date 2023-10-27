<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StopWords implements Responsable
{
    /**
     * @var array
     */
    protected $stopWords;

    /**
     * StopWords constructor.
     */
    public function __construct(array $stopWords)
    {
        $this->stopWords = $stopWords;
    }

    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse(Request $request): Response
    {
        return response()->json(['data' => $this->stopWords]);
    }
}
