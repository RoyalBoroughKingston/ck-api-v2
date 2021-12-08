<?php

namespace App\Http\Requests\InformationPage;

use App\Models\File;
use App\Models\InformationPage;
use App\Rules\FileIsMimeType;
use App\Rules\FileIsPendingAssignment;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if ($this->user()->isGlobalAdmin()) {
            return true;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'title' => ['required', 'string', 'min:1', 'max:255'],
            'content' => ['required', 'string', 'min:1', 'max:500'],
            'order' => [
                'required',
                'integer',
                'min:1',
                'max:' . (InformationPage::find($this->parent_id)->children->count()),
            ],
            'enabled' => ['required', 'boolean'],
            'parent_id' => ['string', 'exists:information_pages,id'],
            'image_file_id' => [
                'exists:files,id',
                new FileIsMimeType(File::MIME_TYPE_PNG),
                new FileIsPendingAssignment(),
            ],
        ];
    }
}
