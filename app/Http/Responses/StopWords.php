<?php

namespace App\Http\Responses;

use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Contracts\Support\Responsable;

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
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function toResponse(Request $request): Response
    {
        return response()->json(['data' => $this->stopWords]);
    }
}
