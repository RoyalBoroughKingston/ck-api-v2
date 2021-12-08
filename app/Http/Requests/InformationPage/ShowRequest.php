<?php

namespace App\Http\Requests\InformationPage;

use App\Models\InformationPage;
use Illuminate\Foundation\Http\FormRequest;

class ShowRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (!$this->user() || !$this->user()->isGlobalAdmin()) {
            return InformationPage::find($this->route('information_page'))->enabled;
        }

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
            //
        ];
    }
}
