<?php

namespace App\Emails\PasswordReset;

use App\Emails\Email;

class UserEmail extends Email
{
    /**
     * @return string
     */
    protected function getTemplateId(): string
    {
        return config('ck.notifications_template_ids.password_reset.email');
    }

    /**
     * @return string|null
     */
    protected function getReference(): ?string
    {
        return null;
    }

    /**
     * @return string|null
     */
    protected function getReplyTo(): ?string
    {
        return null;
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return '';
    }
}
