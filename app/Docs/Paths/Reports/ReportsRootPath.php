<?php

namespace App\Docs\Paths\Reports;

use App\Docs\Operations\Reports\IndexReportOperation;
use App\Docs\Operations\Reports\StoreReportOperation;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\PathItem;

class ReportsRootPath extends PathItem
{
    /**
     * @param  string|null  $objectId
     * @return static
     *
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     */
    public static function create(string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->route('/reports')
            ->operations(
                IndexReportOperation::create(),
                StoreReportOperation::create()
            );
    }
}
