<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use Illuminate\Contracts\Support\Responsable;
use App\Docs\OpenApi;

class DocsController extends Controller
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(): View
    {
        return view('docs.index');
    }

    /**
     * Parse the specified YAML file through Blade.
     *
     * @return \Illuminate\Contracts\Support\Responsable
     *
     * @throws \Throwable
     */
    public function show(): Responsable
    {
        return OpenApi::create();
    }
}
