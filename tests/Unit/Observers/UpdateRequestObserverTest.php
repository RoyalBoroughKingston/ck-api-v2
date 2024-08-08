<?php

namespace Tests\Unit\Observers;

use Tests\TestCase;
use App\Models\User;
use App\Models\Service;
use App\Models\Organisation;
use App\Models\UpdateRequest;
use Illuminate\Support\Facades\Queue;

class UpdateRequestObserverTest extends TestCase
{
    public function test_emails_sent_for_existing_organisation(): void
    {
        Queue::fake();

        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);
        $organisation->updateRequests()->create([
            'user_id' => $user->id,
            'data' => [
                'slug' => 'test-org',
                'name' => 'Test Org',
                'description' => 'Lorem ipsum',
                'url' => 'https://example.com',
                'email' => 'info@example.com',
                'phone' => '07700000000',
            ],
        ]);

        Queue::assertPushedOn(
            config('queue.queues.notifications', 'default'),
            \App\Emails\UpdateRequestReceived\NotifySubmitterEmail::class
        );
        Queue::assertPushed(
            \App\Emails\UpdateRequestReceived\NotifySubmitterEmail::class,
            function (\App\Emails\UpdateRequestReceived\NotifySubmitterEmail $email) {
                $this->assertArrayHasKey('SUBMITTER_NAME', $email->values);
                $this->assertArrayHasKey('RESOURCE_NAME', $email->values);
                $this->assertArrayHasKey('RESOURCE_TYPE', $email->values);

                return true;
            }
        );

        Queue::assertPushedOn(
            config('queue.queues.notifications', 'default'),
            \App\Emails\UpdateRequestReceived\NotifyGlobalAdminEmail::class
        );
        Queue::assertPushed(
            \App\Emails\UpdateRequestReceived\NotifyGlobalAdminEmail::class,
            function (\App\Emails\UpdateRequestReceived\NotifyGlobalAdminEmail $email) {
                $this->assertArrayHasKey('RESOURCE_NAME', $email->values);
                $this->assertArrayHasKey('RESOURCE_TYPE', $email->values);
                $this->assertArrayHasKey('RESOURCE_ID', $email->values);
                $this->assertArrayHasKey('REQUEST_URL', $email->values);

                return true;
            }
        );
    }

    public function test_emails_sent_for_new_organisation(): void
    {
        Queue::fake();

        UpdateRequest::create([
            'updateable_type' => UpdateRequest::NEW_TYPE_ORGANISATION_SIGN_UP_FORM,
            'data' => [
                'user' => [
                    'first_name' => $this->faker->firstName(),
                    'last_name' => $this->faker->lastName(),
                    'email' => $this->faker->safeEmail(),
                    'phone' => random_uk_phone(),
                ],
                'organisation' => [
                    'slug' => 'test-org',
                    'name' => 'Test Org',
                    'description' => 'Test description',
                    'url' => 'http://test-org.example.com',
                    'email' => 'info@test-org.example.com',
                    'phone' => '07700000000',
                ],
                'service' => [
                    'slug' => 'test-service',
                    'name' => 'Test Service',
                    'type' => Service::TYPE_SERVICE,
                    'intro' => 'This is a test intro',
                    'description' => 'Lorem ipsum',
                    'wait_time' => null,
                    'is_free' => true,
                    'fees_text' => null,
                    'fees_url' => null,
                    'testimonial' => null,
                    'video_embed' => null,
                    'url' => $this->faker->url(),
                    'contact_name' => $this->faker->name(),
                    'contact_phone' => random_uk_phone(),
                    'contact_email' => $this->faker->safeEmail(),
                    'useful_infos' => [],
                    'offerings' => [],
                    'social_medias' => [],
                ],
            ],
        ]);

        Queue::assertPushedOn(
            config('queue.queues.notifications', 'default'),
            \App\Emails\OrganisationSignUpFormReceived\NotifySubmitterEmail::class
        );
        Queue::assertPushed(
            \App\Emails\OrganisationSignUpFormReceived\NotifySubmitterEmail::class,
            function (\App\Emails\OrganisationSignUpFormReceived\NotifySubmitterEmail $email) {
                $this->assertArrayHasKey('SUBMITTER_NAME', $email->values);
                $this->assertArrayHasKey('ORGANISATION_NAME', $email->values);

                return true;
            }
        );

        Queue::assertPushedOn(
            config('queue.queues.notifications', 'default'),
            \App\Emails\OrganisationSignUpFormReceived\NotifyGlobalAdminEmail::class
        );
        Queue::assertPushed(
            \App\Emails\OrganisationSignUpFormReceived\NotifyGlobalAdminEmail::class,
            function (\App\Emails\OrganisationSignUpFormReceived\NotifyGlobalAdminEmail $email) {
                $this->assertArrayHasKey('ORGANISATION_NAME', $email->values);
                $this->assertArrayHasKey('REQUEST_URL', $email->values);

                return true;
            }
        );
    }
}
