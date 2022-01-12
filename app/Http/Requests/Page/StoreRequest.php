<?php

namespace App\Http\Requests\Page;

use App\Models\File;
use App\Models\Page;
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
        $maxOrder = $this->parent_id ?
        Page::findOrFail($this->parent_id)->children->count() :
        Page::whereIsRoot()->count();

        return [
            'title' => ['required', 'string', 'min:1', 'max:255'],
            'content' => ['required', 'string', 'min:1', 'max:3000'],
            'order' => [
                'integer',
                'min:0',
                'max:' . $maxOrder,
            ],
            'parent_id' => ['sometimes', 'nullable', 'string', 'exists:pages,id'],
            'image_file_id' => [
                'sometimes',
                'nullable',
                'exists:files,id',
                new FileIsMimeType(File::MIME_TYPE_PNG, File::MIME_TYPE_JPG),
                new FileIsPendingAssignment(),
            ],
        ];
    }
}
