<?php

namespace App\Models;

use App\Models\Mutators\PageMutators;
use App\Models\Relationships\PageRelationships;
use App\Models\Scopes\PageScopes;
use Kalnoy\Nestedset\NodeTrait;

class Page extends Model
{
    use PageRelationships;
    use PageMutators;
    use PageScopes;
    use NodeTrait;

    const DISABLED = false;

    const ENABLED = true;

    const PARENT_KEY = 'parent_uuid';

    const PAGE_TYPE_INFORMATION = 'information';

    const PAGE_TYPE_LANDING = 'landing';

    /**
     * Attributes that need to be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'content' => 'array',
        'enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'page_type' => self::PAGE_TYPE_INFORMATION,
    ];

    /**
     * Enable the Page.
     *
     * @return \App\Models\Page
     */
    public function enable()
    {
        $this->enabled = static::ENABLED;

        return $this;
    }

    /**
     * Disable the Page.
     *
     * @return \App\Models\Page
     */
    public function disable()
    {
        $this->enabled = static::DISABLED;

        return $this;
    }

    /**
     * Get the parent id key name.
     *
     * @return string
     */
    public function getParentIdName()
    {
        return static::PARENT_KEY;
    }

    /**
     * Set the page_type to 'landing'.
     *
     * @return \App\Models\Page
     */
    public function asLandingPage(): self
    {
        $this->page_type = static::PAGE_TYPE_LANDING;

        return $this;
    }

    /**
     * Set the page_type to 'information'.
     *
     * @return \App\Models\Page
     */
    public function asInformationPage(): self
    {
        $this->page_type = static::PAGE_TYPE_INFORMATION;

        return $this;
    }

    /**
     * Inherit the status (if disabled) of a parent (if exists)
     * and pass on to descendants (if disabled).
     * Children do not inherit enabled status, but must be enabled individually.
     *
     * @param mixed $status
     * @return \App\Models\Page
     */
    public function updateStatus($status): self
    {
        if ($this->parent && $this->parent->enabled === self::DISABLED) {
            $this->enabled = self::DISABLED;
        } elseif (!is_null($status)) {
            $this->enabled = $status;
        }

        if ($this->enabled === self::DISABLED) {
            self::whereIn('id', $this->descendants->pluck('id'))
                ->update(['enabled' => self::DISABLED]);
        }

        $this->save();

        return $this;
    }

    /**
     * Update the parent relationship.
     *
     * @param string $parentId
     * @return \App\Models\Page
     */
    public function updateParent($parentId = false): self
    {
        // If parent_id is null save as root node
        if (is_null($parentId)) {
            $this->saveAsRoot();
        } elseif ($parentId && $parentId !== $this->parent_uuid) {
            Page::find($parentId)->appendNode($this);
        }

        return $this;
    }

    /**
     * Update the sibling order for the page.
     *
     * @param int $order
     * @return \App\Models\Page
     */
    public function updateOrder($order): self
    {
        if (!is_null($order)) {
            $siblingAtIndex = $this->siblingAtIndex($order)->first();
            $this->beforeOrAfterNode($siblingAtIndex, $siblingAtIndex->getLft() > $this->getLft());
        }

        return $this;
    }

    /**
     * Update the image relationship.
     *
     * @param string $imageId
     * @return \App\Models\Page
     */
    public function updateImage($imageId)
    {
        if ($imageId !== $this->image_file_id) {
            $currentImage = $this->image;

            if ($imageId) {
                /** @var \App\Models\File $file */
                $file = File::findOrFail($imageId)->assigned();

                // Create resized version for common dimensions.
                foreach (config('local.cached_image_dimensions') as $maxDimension) {
                    $file->resizedVersion($maxDimension);
                }
                $this->image()->associate($file);
            } else {
                $this->image()->dissociate();
            }

            $this->save();

            if ($currentImage) {
                $currentImage->deleteFromDisk();
                $currentImage->delete();
            }
        }

        return $this;
    }

    /**
     * Update the collections relationship.
     *
     * @param array $collectionIds
     * @param mixed $collections
     * @return \App\Models\Page
     */
    public function updateCollections($collections)
    {
        if (is_array($collections)) {
            $this->collections()->sync($collections);
        }

        return $this;
    }
}
