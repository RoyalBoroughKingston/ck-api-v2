<?php

namespace App\Emails\OrganisationSignUpFormApproved;

use App\Emails\Email;

class NotifySubmitterEmail extends Email
{
    /**
     * @return string
     */
    protected function getTemplateId(): string
    {
        return config('gov_uk_notify.notifications_template_ids.organisation_sign_up_form_approved.notify_submitter.email');
    }

    /**
     * @return string
     */
    public function getSubject(): string
    {
        return 'Organisation Sign Up Form Approved';
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return 'Pending to be sent. Content will be filled once sent.';
    }
}
