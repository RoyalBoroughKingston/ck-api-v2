<?php

namespace App\Http\Requests\Service\Logo;

use App\Http\Requests\ImageFormRequest;

class ShowRequest extends ImageFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function extraRules(): array
    {
        return [
            'update_request_id' => ['exists:update_requests,id'],
        ];
    }
}
