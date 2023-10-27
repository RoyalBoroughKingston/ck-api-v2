<?php

namespace App\Observers;

use App\Models\Report;

class ReportObserver
{
    /**
     * Handle the organisation "deleted" event.
     */
    public function deleted(Report $report)
    {
        $report->file->delete();
    }
}
