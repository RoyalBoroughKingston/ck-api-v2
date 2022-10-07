<?php

namespace App\Emails\OrganisationSignUpFormReceived;

use App\Emails\Email;

class NotifyGlobalAdminEmail extends Email
{
    /**
     * @return string
     */
    protected function getTemplateId(): string
    {
        return config('gov_uk_notify.notifications_template_ids.organisation_sign_up_form_received.notify_global_admin.email');
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return <<<'EOT'
Hello,

An organisation sign up form has been submitted for ((ORGANISATION_NAME)).

Please review the request below before approving/rejecting:
((REQUEST_URL))

Regards,
Connected Places Team
EOT;
    }

    /**
     * @return string
     */
    public function getSubject(): string
    {
        return 'Organisation Sign Up Form Submitted';
    }
}
