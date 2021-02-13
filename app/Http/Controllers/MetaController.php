<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Exception;

class MetaController extends Controller
{
    public function exception()
    {
        throw new Exception('Test exception.');
    }
}
