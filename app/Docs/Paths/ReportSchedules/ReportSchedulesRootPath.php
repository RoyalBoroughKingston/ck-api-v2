<?php

namespace App\Docs\Paths\ReportSchedules;

use App\Docs\Operations\ReportSchedules\IndexReportScheduleOperation;
use App\Docs\Operations\ReportSchedules\StoreReportScheduleOperation;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\PathItem;

class ReportSchedulesRootPath extends PathItem
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
            ->route('/report-schedules')
            ->operations(
                IndexReportScheduleOperation::create(),
                StoreReportScheduleOperation::create()
            );
    }
}
