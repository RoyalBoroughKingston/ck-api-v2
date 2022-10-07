<?php

namespace App\Emails\OrganisationSignUpFormReceived;

use App\Emails\Email;

class NotifySubmitterEmail extends Email
{
    /**
     * @return string
     */
    protected function getTemplateId(): string
    {
        return config('gov_uk_notify.notifications_template_ids.organisation_sign_up_form_received.notify_submitter.email');
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return <<<'EOT'
Hi ((SUBMITTER_NAME)),
Your request to register ((ORGANISATION_NAME)) on Connected Places has been submitted and received. A member of the admin team will review it shortly.
If you have any questions, please get in touch at hlp.admin.connect@nhs.net.
Many thanks,
Connected Places Team
EOT;
    }

    /**
     * @return string
     */
    public function getSubject(): string
    {
        return 'Connected Places Organisation Sign Up Form Submitted';
    }
}
