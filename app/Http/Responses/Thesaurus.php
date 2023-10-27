<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class Thesaurus implements Responsable
{
    /**
     * @var array
     */
    protected $thesaurus;

    /**
     * Thesaurus constructor.
     */
    public function __construct(array $thesaurus)
    {
        $this->thesaurus = $thesaurus;
    }

    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse(Request $request): Response
    {
        return response()->json(['data' => $this->thesaurus]);
    }
}
