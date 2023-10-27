<?php

namespace App\Providers;

use Illuminate\Queue\QueueManager;
use App\Queue\Connectors\SqsConnector;
use Illuminate\Queue\QueueServiceProvider as BaseQueueServiceProvider;

class QueueServiceProvider extends BaseQueueServiceProvider
{
    /**
     * Register the Amazon SQS queue connector.
     */
    protected function registerSqsConnector(QueueManager $manager)
    {
        $manager->addConnector('sqs', function () {
            return new SqsConnector();
        });
    }
}
