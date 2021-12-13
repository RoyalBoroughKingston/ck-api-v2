<?php

namespace App\Http\Requests\InformationPage;

use App\Models\File;
use App\Models\InformationPage;
use App\Models\Role;
use App\Models\UserRole;
use App\Rules\FileIsMimeType;
use App\Rules\FileIsPendingAssignment;
use App\Rules\UserHasRole;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
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
        InformationPage::findOrFail($this->parent_id)->children->count() :
        ($this->information_page->parent ?
            $this->information_page->siblingsAndSelf()->count() :
            InformationPage::whereIsRoot()->count());
        return [
            'title' => ['string', 'min:1', 'max:255'],
            'content' => ['string', 'min:1', 'max:500'],
            'order' => [
                'integer',
                'min:0',
                'max:' . $maxOrder,
            ],
            'enabled' => [
                'boolean',
                new UserHasRole(
                    $this->user('api'),
                    new UserRole([
                        'user_id' => $this->user('api')->id,
                        'role_id' => Role::globalAdmin()->id,
                    ]),
                    $this->information_page->enabled
                ),
            ],
            'parent_id' => ['string', 'exists:information_pages,id'],
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
