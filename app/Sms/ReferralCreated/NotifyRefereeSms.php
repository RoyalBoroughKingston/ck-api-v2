<?php

namespace App\Sms\ReferralCreated;

use App\Sms\Sms;

class NotifyRefereeSms extends Sms
{
    /**
     * @return string
     */
    protected function getTemplateId(): string
    {
        return config('gov_uk_notify.notifications_template_ids.referral_created.notify_referee.sms');
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return 'sms.referral.created.notify_referee';
    }
}
