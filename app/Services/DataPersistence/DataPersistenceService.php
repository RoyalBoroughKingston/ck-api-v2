<?php

namespace App\Services\DataPersistence;

use App\Models\Model;
use Illuminate\Foundation\Http\FormRequest;

interface DataPersistenceService
{
    public function update(FormRequest $request, Model $model);

    public function store(FormRequest $request);
}
