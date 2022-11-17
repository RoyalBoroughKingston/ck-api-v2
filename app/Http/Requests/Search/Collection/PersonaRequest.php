<?php

namespace App\Http\Requests\Search\Collection;

use App\Models\Collection;
use App\Rules\CollectionExists;
use Illuminate\Foundation\Http\FormRequest;

class PersonaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'persona' => [
                'required',
                new CollectionExists(Collection::TYPE_PERSONA),
                'string',
                'min:1',
                'max:255',
            ],
        ];
    }
}
