<?php

namespace App\Queue;

use Illuminate\Queue\SqsQueue as BaseSqsQueue;

class SqsQueue extends BaseSqsQueue
{
    /**
     * Get the queue or return the default.
     */
    public function getQueue(?string $queue): string
    {
        $queue = $queue ?: $this->default;

        return filter_var($queue, FILTER_VALIDATE_URL) === false
            ? $this->prefix.$queue : $queue;
    }
}
