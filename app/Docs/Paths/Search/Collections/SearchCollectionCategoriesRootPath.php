<?php

namespace App\Docs\Paths\Search\Collections;

use App\Docs\Operations\Search\Collections\StoreSearchCollectionCategoryOperation;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\PathItem;

class SearchCollectionCategoriesRootPath extends PathItem
{
    /**
     * @param string|null $objectId
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     * @return static
     */
    public static function create(string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->route('/search/collections/categories')
            ->operations(
                StoreSearchCollectionCategoryOperation::create()
            );
    }
}
