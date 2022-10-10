<?php

namespace App\Emails\PageFeedbackReceived;

use App\Emails\Email;

class NotifyGlobalAdminEmail extends Email
{
    /**
     * @inheritDoc
     */
    protected function getTemplateId(): string
    {
        return config('gov_uk_notify.notifications_template_ids.page_feedback_received.notify_global_admin.email');
    }

    /**
     * @inheritDoc
     */
    public function getContent(): string
    {
        return 'emails.page_feedback.received.notify_global_admin.content';
    }

    /**
     * @inheritDoc
     */
    public function getSubject(): string
    {
        return 'emails.page_feedback.received.notify_global_admin.subject';
    }
}
