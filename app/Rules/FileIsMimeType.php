<?php

namespace App\Rules;

use App\Models\File;
use Illuminate\Contracts\Validation\Rule;

class FileIsMimeType implements Rule
{
    /**
     * @var array
     */
    protected $mimeTypes;

    /**
     * FileIsMimeType constructor.
     */
    public function __construct(string ...$mimeTypes)
    {
        $this->mimeTypes = $mimeTypes;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $fileId
     * @return bool
     */
    public function passes(string $attribute, $fileId): bool
    {
        return in_array(
            File::findOrFail($fileId)->mime_type,
            $this->mimeTypes
        );
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        $mimeTypes = implode(', ', $this->mimeTypes);

        return "The :attribute must be of type $mimeTypes.";
    }
}
