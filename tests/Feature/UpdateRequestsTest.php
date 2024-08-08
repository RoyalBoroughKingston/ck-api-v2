<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use App\Models\Audit;
use App\Models\Service;
use App\Models\Location;
use App\Models\Offering;
use App\Models\Taxonomy;
use App\Models\UserRole;
use App\Models\UsefulInfo;
use App\Events\EndpointHit;
use App\Models\SocialMedia;
use App\Models\Organisation;
use App\Models\UpdateRequest;
use Illuminate\Http\Response;
use Laravel\Passport\Passport;
use App\Models\ServiceLocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

class UpdateRequestsTest extends TestCase
{
    const BASE64_ENCODED_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==';

    /*
     * List all the update requests.
     */

    /**
     * @test
     */
    public function guest_cannot_list_them(): void
    {
        $response = $this->json('GET', '/core/v1/update-requests');

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function service_worker_cannot_list_them(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);

        Passport::actingAs($user);
        $response = $this->json('GET', '/core/v1/update-requests');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function service_admin_cannot_list_them(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($user);
        $response = $this->json('GET', '/core/v1/update-requests');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function organisation_admin_cannot_list_them(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);
        $response = $this->json('GET', '/core/v1/update-requests');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function global_admin_cannot_list_them(): void
    {
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);
        $response = $this->json('GET', '/core/v1/update-requests');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function super_admin_can_list_them(): void
    {
        $organisation = Organisation::factory()->create();
        $orgAdminUser = User::factory()->create()->makeOrganisationAdmin($organisation);
        $location = Location::factory()->create();
        $updateRequest = $location->updateRequests()->create([
            'user_id' => $orgAdminUser->id,
            'data' => [
                'address_line_1' => $this->faker->streetAddress(),
                'address_line_2' => null,
                'address_line_3' => null,
                'city' => $this->faker->city(),
                'county' => 'West Yorkshire',
                'postcode' => $this->faker->postcode(),
                'country' => 'United Kingdom',
                'accessibility_info' => null,
            ],
        ]);

        $globalAdminUser = User::factory()->create()->makeSuperAdmin();

        Passport::actingAs($globalAdminUser);
        $response = $this->json('GET', '/core/v1/update-requests');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'id' => $updateRequest->id,
            'user_id' => $orgAdminUser->id,
            'actioning_user_id' => null,
            'updateable_type' => UpdateRequest::EXISTING_TYPE_LOCATION,
            'updateable_id' => $location->id,
            'data' => [
                'address_line_1' => $updateRequest->data['address_line_1'],
                'address_line_2' => null,
                'address_line_3' => null,
                'city' => $updateRequest->data['city'],
                'county' => 'West Yorkshire',
                'postcode' => $updateRequest->data['postcode'],
                'country' => 'United Kingdom',
                'accessibility_info' => null,
            ],
        ]);
    }

    /**
     * @test
     */
    public function can_list_them_for_location(): void
    {
        $organisation = Organisation::factory()->create();
        $orgAdminUser = User::factory()->create()->makeOrganisationAdmin($organisation);

        $organisationUpdateRequest = $organisation->updateRequests()->create([
            'user_id' => $orgAdminUser->id,
            'data' => [
                'name' => 'Test Name',
                'description' => 'Lorem ipsum',
                'url' => 'https://example.com',
                'email' => 'phpunit@example.com',
                'phone' => '07700000000',
            ],
        ]);

        $location = Location::factory()->create();
        $locationUpdateRequest = $location->updateRequests()->create([
            'user_id' => $orgAdminUser->id,
            'data' => [
                'address_line_1' => $this->faker->streetAddress(),
                'address_line_2' => null,
                'address_line_3' => null,
                'city' => $this->faker->city(),
                'county' => 'West Yorkshire',
                'postcode' => $this->faker->postcode(),
                'country' => 'United Kingdom',
                'accessibility_info' => null,
            ],
        ]);

        $superAdminUser = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($superAdminUser);

        $response = $this->json('GET', "/core/v1/update-requests?filter[location_id]={$location->id}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $locationUpdateRequest->id]);
        $response->assertJsonMissing(['id' => $organisationUpdateRequest->id]);
    }

    /**
     * @test
     */
    public function audit_created_when_listed(): void
    {
        $this->fakeEvents();

        $user = User::factory()->create()->makeSuperAdmin();

        Passport::actingAs($user);
        $this->json('GET', '/core/v1/update-requests');

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user) {
            return ($event->getAction() === Audit::ACTION_READ) &&
                ($event->getUser()->id === $user->id);
        });
    }

    /**
     * @test
     */
    public function can_filter_by_entry(): void
    {
        $organisation = Organisation::factory()->create([
            'name' => 'Name with, comma',
        ]);
        $creatingUser = User::factory()->create()->makeOrganisationAdmin($organisation);
        $location = Location::factory()->create();
        $locationUpdateRequest = $location->updateRequests()->create([
            'user_id' => $creatingUser->id,
            'data' => [
                'address_line_1' => $this->faker->streetAddress(),
                'address_line_2' => null,
                'address_line_3' => null,
                'city' => $this->faker->city(),
                'county' => 'West Yorkshire',
                'postcode' => $this->faker->postcode(),
                'country' => 'United Kingdom',
                'accessibility_info' => null,
            ],
        ]);

        $organisationUpdateRequest = $organisation->updateRequests()->create([
            'user_id' => $creatingUser->id,
            'data' => [
                'name' => 'Test Name',
                'description' => 'Lorem ipsum',
                'url' => 'https://example.com',
                'email' => 'phpunit@example.com',
                'phone' => '07700000000',
            ],
        ]);

        $superAdminUser = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($superAdminUser);
        $response = $this->json('GET', "/core/v1/update-requests?filter[entry]={$organisation->name}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonMissing(['id' => $locationUpdateRequest->id]);
        $response->assertJsonFragment(['id' => $organisationUpdateRequest->id]);
    }

    /**
     * @test
     */
    public function getFilterUpdateRequestsByTypeAsSuperAdmin200(): void
    {
        $organisation = Organisation::factory()->create([
            'name' => 'Name with, comma',
        ]);
        $creatingUser = User::factory()->create()->makeOrganisationAdmin($organisation);

        $location = Location::factory()->create();
        $locationUpdateRequest = $location->updateRequests()->create([
            'user_id' => $creatingUser->id,
            'data' => [
                'address_line_1' => $this->faker->streetAddress(),
                'address_line_2' => null,
                'address_line_3' => null,
                'city' => $this->faker->city(),
                'county' => 'West Yorkshire',
                'postcode' => $this->faker->postcode(),
                'country' => 'United Kingdom',
                'accessibility_info' => null,
            ],
        ]);

        $organisationUpdateRequest = $organisation->updateRequests()->create([
            'user_id' => $creatingUser->id,
            'data' => [
                'name' => 'Test Name',
                'description' => 'Lorem ipsum',
                'url' => 'https://example.com',
                'email' => 'phpunit@example.com',
                'phone' => '07700000000',
            ],
        ]);

        $superAdminUser = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($superAdminUser);
        $response = $this->json('GET', '/core/v1/update-requests?filter[type]=' . UpdateRequest::EXISTING_TYPE_ORGANISATION);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonMissing(['id' => $locationUpdateRequest->id]);
        $response->assertJsonFragment(['id' => $organisationUpdateRequest->id]);
    }

    /**
     * @test
     */
    public function can_sort_by_entry(): void
    {
        $location = Location::factory()->create([
            'address_line_1' => 'Entry A',
        ]);
        $organisation = Organisation::factory()->create([
            'name' => 'Entry B',
        ]);
        $creatingUser = User::factory()->create()->makeOrganisationAdmin($organisation);
        $locationUpdateRequest = $location->updateRequests()->create([
            'user_id' => $creatingUser->id,
            'data' => [
                'address_line_1' => 'Entry A',
                'address_line_2' => null,
                'address_line_3' => null,
                'city' => $this->faker->city(),
                'county' => 'West Yorkshire',
                'postcode' => $this->faker->postcode(),
                'country' => 'United Kingdom',
                'accessibility_info' => null,
            ],
        ]);

        $organisationUpdateRequest = $organisation->updateRequests()->create([
            'user_id' => $creatingUser->id,
            'data' => [
                'name' => 'Entry B',
                'description' => 'Lorem ipsum',
                'url' => 'https://example.com',
                'email' => 'phpunit@example.com',
                'phone' => '07700000000',
            ],
        ]);

        Passport::actingAs(User::factory()->create()->makeSuperAdmin());
        $response = $this->json('GET', '/core/v1/update-requests?sort=-entry');
        $data = $this->getResponseContent($response);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals($locationUpdateRequest->id, $data['data'][1]['id']);
        $this->assertEquals($organisationUpdateRequest->id, $data['data'][0]['id']);
    }

    /*
     * Get a specific update request.
     */

    /**
     * @test
     */
    public function guest_cannot_view_one(): void
    {
        $serviceLocation = ServiceLocation::factory()->create();
        $updateRequest = $serviceLocation->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => ['name' => 'Test Name'],
        ]);

        $response = $this->json('GET', "/core/v1/update-requests/{$updateRequest->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function service_worker_cannot_view_one(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);
        Passport::actingAs($user);

        $serviceLocation = ServiceLocation::factory()->create();
        $updateRequest = $serviceLocation->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => ['name' => 'Test Name'],
        ]);

        $response = $this->json('GET', "/core/v1/update-requests/{$updateRequest->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function service_admin_can_view_one(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);
        Passport::actingAs($user);

        $serviceLocation = ServiceLocation::factory()->create();
        $updateRequest = $serviceLocation->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => ['name' => 'Test Name'],
        ]);

        $response = $this->json('GET', "/core/v1/update-requests/{$updateRequest->id}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'id' => $updateRequest->id,
            'user_id' => $updateRequest->user_id,
            'actioning_user_id' => null,
            'updateable_type' => UpdateRequest::EXISTING_TYPE_SERVICE_LOCATION,
            'updateable_id' => $serviceLocation->id,
            'data' => ['name' => 'Test Name'],
        ]);
    }

    /**
     * @test
     */
    public function organisation_admin_can_view_one(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);
        Passport::actingAs($user);

        $serviceLocation = ServiceLocation::factory()->create();
        $updateRequest = $serviceLocation->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => ['name' => 'Test Name'],
        ]);

        $response = $this->json('GET', "/core/v1/update-requests/{$updateRequest->id}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'id' => $updateRequest->id,
            'user_id' => $updateRequest->user_id,
            'actioning_user_id' => null,
            'updateable_type' => UpdateRequest::EXISTING_TYPE_SERVICE_LOCATION,
            'updateable_id' => $serviceLocation->id,
            'data' => ['name' => 'Test Name'],
        ]);
    }

    /**
     * @test
     */
    public function global_admin_can_view_one(): void
    {
        $user = User::factory()->create()->makeGlobalAdmin();
        Passport::actingAs($user);

        $serviceLocation = ServiceLocation::factory()->create();
        $updateRequest = $serviceLocation->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => ['name' => 'Test Name'],
        ]);

        $response = $this->json('GET', "/core/v1/update-requests/{$updateRequest->id}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'id' => $updateRequest->id,
            'user_id' => $updateRequest->user_id,
            'actioning_user_id' => null,
            'updateable_type' => UpdateRequest::EXISTING_TYPE_SERVICE_LOCATION,
            'updateable_id' => $serviceLocation->id,
            'data' => ['name' => 'Test Name'],
        ]);
    }

    /**
     * @test
     */
    public function super_admin_can_view_one(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $serviceLocation = ServiceLocation::factory()->create();
        $updateRequest = $serviceLocation->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => ['name' => 'Test Name'],
        ]);

        $response = $this->json('GET', "/core/v1/update-requests/{$updateRequest->id}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'id' => $updateRequest->id,
            'user_id' => $updateRequest->user_id,
            'actioning_user_id' => null,
            'updateable_type' => UpdateRequest::EXISTING_TYPE_SERVICE_LOCATION,
            'updateable_id' => $serviceLocation->id,
            'data' => ['name' => 'Test Name'],
        ]);
    }

    /**
     * @test
     */
    public function audit_created_when_viewed(): void
    {
        $this->fakeEvents();

        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $serviceLocation = ServiceLocation::factory()->create();
        $updateRequest = $serviceLocation->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => ['name' => 'Test Name'],
        ]);

        $this->json('GET', "/core/v1/update-requests/{$updateRequest->id}");

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $updateRequest) {
            return ($event->getAction() === Audit::ACTION_READ) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $updateRequest->id);
        });
    }

    /*
     * Delete a specific update request.
     */

    /**
     * @test
     */
    public function guest_cannot_delete_one(): void
    {
        $serviceLocation = ServiceLocation::factory()->create();
        $updateRequest = $serviceLocation->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => ['name' => 'Test Name'],
        ]);

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/reject", ['message' => 'Rejection Message']);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function service_worker_cannot_delete_one(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);
        Passport::actingAs($user);

        $serviceLocation = ServiceLocation::factory()->create();
        $updateRequest = $serviceLocation->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => ['name' => 'Test Name'],
        ]);

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/reject", ['message' => 'Rejection Message']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function service_admin_cannot_delete_one(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);
        Passport::actingAs($user);

        $serviceLocation = ServiceLocation::factory()->create();
        $updateRequest = $serviceLocation->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => ['name' => 'Test Name'],
        ]);

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/reject", ['message' => 'Rejection Message']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function organisation_admin_cannot_delete_one(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);
        Passport::actingAs($user);

        $serviceLocation = ServiceLocation::factory()->create();
        $updateRequest = $serviceLocation->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => ['name' => 'Test Name'],
        ]);

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/reject", ['message' => 'Rejection Message']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function global_admin_cannot_delete_one(): void
    {
        $user = User::factory()->create()->makeGlobalAdmin();
        Passport::actingAs($user);

        $serviceLocation = ServiceLocation::factory()->create();
        $updateRequest = $serviceLocation->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => ['name' => 'Test Name'],
        ]);

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/reject", ['message' => 'Rejection Message']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function super_admin_cannot_delete_one_without_a_rejection_message(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $serviceLocation = ServiceLocation::factory()->create();
        $updateRequest = $serviceLocation->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => ['name' => 'Test Name'],
        ]);

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/reject");

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'id' => $updateRequest->id,
            'actioning_user_id' => null,
        ]);
    }

    /**
     * @test
     */
    public function super_admin_can_delete_one_with_a_rejection_message(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $serviceLocation = ServiceLocation::factory()->create();
        $updateRequest = $serviceLocation->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => ['name' => 'Test Name'],
        ]);

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/reject", ['message' => 'Rejection Message']);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertSoftDeleted((new UpdateRequest())->getTable(), [
            'id' => $updateRequest->id,
            'actioning_user_id' => $user->id,
        ]);
    }

    /**
     * @test
     */
    public function super_admin_can_delete_one_for_an_organisation_signup_form_with_a_rejection_message(): void
    {
        Queue::fake();

        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        /** @var \App\Models\UpdateRequest $updateRequest */
        $updateRequest = UpdateRequest::create([
            'updateable_type' => UpdateRequest::NEW_TYPE_ORGANISATION_SIGN_UP_FORM,
            'data' => [
                'user' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john.doe@example.com',
                    'phone' => '07700000000',
                    'password' => 'P@55w0rd.',
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
                    'url' => 'https://example.com',
                    'contact_name' => 'Foo Bar',
                    'contact_phone' => '01130000000',
                    'contact_email' => 'foo.bar@example.com',
                    'useful_infos' => [
                        [
                            'title' => 'Did you know?',
                            'description' => 'Lorem ipsum',
                            'order' => 1,
                        ],
                    ],
                    'offerings' => [
                        [
                            'offering' => 'Weekly club',
                            'order' => 1,
                        ],
                    ],
                    'social_medias' => [
                        [
                            'type' => SocialMedia::TYPE_INSTAGRAM,
                            'url' => 'https://www.instagram.com/ayupdigital',
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/reject", ['message' => 'Rejection Message']);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertSoftDeleted((new UpdateRequest())->getTable(), [
            'id' => $updateRequest->id,
            'actioning_user_id' => $user->id,
        ]);
    }

    /**
     * @test
     */
    public function audit_created_when_deleted(): void
    {
        $this->fakeEvents();

        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $serviceLocation = ServiceLocation::factory()->create();
        $updateRequest = $serviceLocation->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => ['name' => 'Test Name'],
        ]);

        $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/reject", ['message' => 'Rejection Message']);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $updateRequest) {
            return ($event->getAction() === Audit::ACTION_DELETE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $updateRequest->id);
        });
    }

    /*
     * Approve a specific update request.
     */

    /**
     * @test
     */
    public function guest_cannot_approve_one_for_service_location(): void
    {
        $serviceLocation = ServiceLocation::factory()->create();
        $updateRequest = $serviceLocation->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => ['name' => 'Test Name'],
        ]);

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/approve");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function service_worker_cannot_approve_one_for_service_location(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);
        Passport::actingAs($user);

        $serviceLocation = ServiceLocation::factory()->create();
        $updateRequest = $serviceLocation->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => ['name' => 'Test Name'],
        ]);

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/approve");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function service_admin_cannot_approve_one_for_service_location(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);
        Passport::actingAs($user);

        $serviceLocation = ServiceLocation::factory()->create();
        $updateRequest = $serviceLocation->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => ['name' => 'Test Name'],
        ]);

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/approve");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function organisation_admin_cannot_approve_one_for_service_location(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);
        Passport::actingAs($user);

        $serviceLocation = ServiceLocation::factory()->create();
        $updateRequest = $serviceLocation->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => ['name' => 'Test Name'],
        ]);

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/approve");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function global_admin_cannot_approve_one_for_service_location(): void
    {
        $user = User::factory()->create()->makeGlobalAdmin();
        Passport::actingAs($user);

        $serviceLocation = ServiceLocation::factory()->create();
        $updateRequest = $serviceLocation->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => ['name' => 'Test Name'],
        ]);

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/approve");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function super_admin_can_approve_one_for_service_location(): void
    {
        $now = Date::now();
        Date::setTestNow($now);

        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $serviceLocation = ServiceLocation::factory()->create();
        $updateRequest = $serviceLocation->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => [
                'name' => 'Test Name',
                'regular_opening_hours' => [],
                'holiday_opening_hours' => [],
            ],
        ]);

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/approve");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'id' => $updateRequest->id,
            'actioning_user_id' => $user->id,
            'approved_at' => $now->toDateTimeString(),
        ]);
        $this->assertDatabaseHas(
            (new ServiceLocation())->getTable(),
            ['id' => $serviceLocation->id, 'name' => 'Test Name']
        );
    }

    /**
     * @test
     */
    public function super_admin_can_approve_one_for_organisation(): void
    {
        $now = Date::now();
        Date::setTestNow($now);

        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $organisation = Organisation::factory()->create();
        $updateRequest = $organisation->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => [
                'slug' => 'ayup-digital',
                'name' => 'Ayup Digital',
                'description' => $this->faker->paragraph(),
                'url' => $this->faker->url(),
                'email' => $this->faker->safeEmail(),
                'phone' => random_uk_phone(),
            ],
        ]);

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/approve");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'id' => $updateRequest->id,
            'actioning_user_id' => $user->id,
            'approved_at' => $now->toDateTimeString(),
        ]);
        $this->assertDatabaseHas((new Organisation())->getTable(), [
            'id' => $organisation->id,
            'slug' => $updateRequest->data['slug'],
            'name' => $updateRequest->data['name'],
            'description' => $updateRequest->data['description'],
            'url' => $updateRequest->data['url'],
            'email' => $updateRequest->data['email'],
            'phone' => $updateRequest->data['phone'],
        ]);
    }

    /**
     * @test
     */
    public function super_admin_can_approve_one_for_location(): void
    {
        $now = Date::now();
        Date::setTestNow($now);

        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $location = Location::factory()->create();
        $updateRequest = $location->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => [
                'address_line_1' => $this->faker->streetAddress(),
                'address_line_2' => null,
                'address_line_3' => null,
                'city' => $this->faker->city(),
                'county' => 'West Yorkshire',
                'postcode' => $this->faker->postcode(),
                'country' => 'United Kingdom',
                'accessibility_info' => null,
                'has_wheelchair_access' => false,
                'has_induction_loop' => false,
            ],
        ]);

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/approve");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'id' => $updateRequest->id,
            'actioning_user_id' => $user->id,
            'approved_at' => $now->toDateTimeString(),
        ]);
        $this->assertDatabaseHas((new Location())->getTable(), [
            'id' => $location->id,
            'address_line_1' => $updateRequest->data['address_line_1'],
            'address_line_2' => $updateRequest->data['address_line_2'],
            'address_line_3' => $updateRequest->data['address_line_3'],
            'city' => $updateRequest->data['city'],
            'county' => $updateRequest->data['county'],
            'postcode' => $updateRequest->data['postcode'],
            'country' => $updateRequest->data['country'],
            'accessibility_info' => $updateRequest->data['accessibility_info'],
            'has_wheelchair_access' => $updateRequest->data['has_wheelchair_access'],
            'has_induction_loop' => $updateRequest->data['has_induction_loop'],
        ]);
    }

    /**
     * @test
     */
    public function super_admin_can_approve_one_for_service(): void
    {
        $now = Date::now();
        Date::setTestNow($now);

        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $service = Service::factory()->create();
        $service->serviceTaxonomies()->create([
            'taxonomy_id' => Taxonomy::category()->children()->firstOrFail()->id,
        ]);
        $updateRequest = $service->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => [
                'slug' => $service->slug,
                'name' => 'Test Name',
                'type' => $service->type,
                'status' => $service->status,
                'intro' => $service->intro,
                'description' => $service->description,
                'wait_time' => $service->wait_time,
                'is_free' => $service->is_free,
                'fees_text' => $service->fees_text,
                'fees_url' => $service->fees_url,
                'testimonial' => $service->testimonial,
                'video_embed' => $service->video_embed,
                'url' => $service->url,
                'contact_name' => $service->contact_name,
                'contact_phone' => $service->contact_phone,
                'contact_email' => $service->contact_email,
                'show_referral_disclaimer' => $service->show_referral_disclaimer,
                'referral_method' => $service->referral_method,
                'referral_button_text' => $service->referral_button_text,
                'referral_email' => $service->referral_email,
                'referral_url' => $service->referral_url,
                'useful_infos' => [],
                'social_medias' => [],
                'category_taxonomies' => $service->taxonomies()->pluck('taxonomies.id')->toArray(),
            ],
        ]);

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/approve");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'id' => $updateRequest->id,
            'actioning_user_id' => $user->id,
            'approved_at' => $now->toDateTimeString(),
        ]);
        $this->assertDatabaseHas((new Service())->getTable(), [
            'id' => $service->id,
            'name' => 'Test Name',
        ]);
    }

    /**
     * @test
     */
    public function super_admin_can_approve_one_for_new_service(): void
    {
        $now = Date::now();
        Date::setTestNow($now);

        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        //Given an organisation admin is logged in
        Passport::actingAs($user);

        $imagePayload = [
            'is_private' => false,
            'mime_type' => 'image/png',
            'alt_text' => 'image description',
            'file' => 'data:image/png;base64,' . self::BASE64_ENCODED_PNG,
        ];

        $response = $this->json('POST', '/core/v1/files', $imagePayload);
        $logoImage = $this->getResponseContent($response, 'data');

        $response = $this->json('POST', '/core/v1/files', $imagePayload);
        $galleryImage1 = $this->getResponseContent($response, 'data');

        $response = $this->json('POST', '/core/v1/files', $imagePayload);
        $galleryImage2 = $this->getResponseContent($response, 'data');

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_INACTIVE,
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
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'logo_file_id' => $logoImage['id'],
            'social_medias' => [],
            'gallery_items' => [
                [
                    'file_id' => $galleryImage1['id'],
                ],
                [
                    'file_id' => $galleryImage2['id'],
                ],
            ],
            'tags' => [],
            'category_taxonomies' => [],
        ];

        //When they create a service
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment($payload);

        //Then an update request should be created for the new service
        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::NEW_TYPE_SERVICE_ORG_ADMIN)
            ->where('updateable_id', null)
            ->firstOrFail();

        $superAdminUser = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($superAdminUser);

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/approve");

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'id' => $updateRequest->id,
            'actioning_user_id' => $superAdminUser->id,
            'approved_at' => $now,
        ]);

        $this->assertNotEmpty(Service::all());
        $this->assertEquals(1, Service::all()->count());
    }

    /**
     * @test
     */
    public function putApproveAndEditNewServiceUpdateRequestAsSuperAdmin200(): void
    {
        $now = Date::now();
        Date::setTestNow($now);

        $organisation = Organisation::factory()->create();
        $taxonomy = Taxonomy::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();

        //Given an organisation admin is logged in
        Passport::actingAs($user);

        $imagePayload = [
            'is_private' => false,
            'mime_type' => 'image/png',
            'alt_text' => 'image description',
            'file' => 'data:image/png;base64,' . self::BASE64_ENCODED_PNG,
        ];

        $response = $this->json('POST', '/core/v1/files', $imagePayload);
        $logoImage = $this->getResponseContent($response, 'data');

        $response = $this->json('POST', '/core/v1/files', $imagePayload);
        $galleryImage1 = $this->getResponseContent($response, 'data');

        $response = $this->json('POST', '/core/v1/files', $imagePayload);
        $galleryImage2 = $this->getResponseContent($response, 'data');

        $payload = [
            'organisation_id' => $organisation->id,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'type' => Service::TYPE_SERVICE,
            'status' => Service::STATUS_ACTIVE,
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
            'cqc_location_id' => $this->faker->numerify('#-#########'),
            'show_referral_disclaimer' => false,
            'referral_method' => Service::REFERRAL_METHOD_NONE,
            'referral_button_text' => null,
            'referral_email' => null,
            'referral_url' => null,
            'ends_at' => null,
            'useful_infos' => [
                [
                    'title' => 'Did you know?',
                    'description' => 'Lorem ipsum',
                    'order' => 1,
                ],
            ],
            'offerings' => [
                [
                    'offering' => 'Weekly club',
                    'order' => 1,
                ],
            ],
            'logo_file_id' => $logoImage['id'],
            'social_medias' => [],
            'gallery_items' => [
                [
                    'file_id' => $galleryImage1['id'],
                ],
                [
                    'file_id' => $galleryImage2['id'],
                ],
            ],
            'tags' => [],
            'category_taxonomies' => [
                $taxonomy->id,
            ],
        ];

        //When they create a service
        $response = $this->json('POST', '/core/v1/services', $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment($payload);

        //Then an update request should be created for the new service
        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::NEW_TYPE_SERVICE_GLOBAL_ADMIN)
            ->where('updateable_id', null)
            ->firstOrFail();

        $superAdminUser = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($superAdminUser);

        // Call approve endpoint with action edit flag
        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/approve?action=edit");

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'id' => $updateRequest->id,
            'actioning_user_id' => $superAdminUser->id,
            'approved_at' => $now,
        ]);

        $this->assertNotEmpty(Service::all());
        $this->assertEquals(1, Service::all()->count());

        // Service should be disabled
        $this->assertDatabaseHas('services', [
            'slug' => 'test-service',
            'status' => Service::STATUS_INACTIVE,
        ]);
    }

    /**
     * @test
     */
    public function super_admin_can_approve_one_for_organisation_sign_up_form(): void
    {
        $now = Date::now();
        Date::setTestNow($now);

        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        /** @var \App\Models\UpdateRequest $updateRequest */
        $updateRequest = UpdateRequest::create([
            'updateable_type' => UpdateRequest::NEW_TYPE_ORGANISATION_SIGN_UP_FORM,
            'data' => [
                'user' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john.doe@example.com',
                    'phone' => '07700000000',
                    'password' => 'P@55w0rd.',
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
                    'url' => 'https://example.com',
                    'contact_name' => 'Foo Bar',
                    'contact_phone' => '01130000000',
                    'contact_email' => 'foo.bar@example.com',
                    'useful_infos' => [
                        [
                            'title' => 'Did you know?',
                            'description' => 'Lorem ipsum',
                            'order' => 1,
                        ],
                    ],
                    'offerings' => [
                        [
                            'offering' => 'Weekly club',
                            'order' => 1,
                        ],
                    ],
                    'social_medias' => [
                        [
                            'type' => SocialMedia::TYPE_INSTAGRAM,
                            'url' => 'https://www.instagram.com/ayupdigital',
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/approve");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'id' => $updateRequest->id,
            'actioning_user_id' => $user->id,
            'approved_at' => $now->toDateTimeString(),
        ]);
        $this->assertDatabaseHas((new User())->getTable(), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '07700000000',
        ]);
        $this->assertDatabaseHas((new Organisation())->getTable(), [
            'slug' => 'test-org',
            'name' => 'Test Org',
            'description' => 'Test description',
            'url' => 'http://test-org.example.com',
            'email' => 'info@test-org.example.com',
            'phone' => '07700000000',
        ]);
        $this->assertDatabaseHas((new Service())->getTable(), [
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
            'url' => 'https://example.com',
            'contact_name' => 'Foo Bar',
            'contact_phone' => '01130000000',
            'contact_email' => 'foo.bar@example.com',
        ]);
        $this->assertDatabaseHas((new UsefulInfo())->getTable(), [
            'title' => 'Did you know?',
            'description' => 'Lorem ipsum',
            'order' => 1,
        ]);
        $this->assertDatabaseHas((new Offering())->getTable(), [
            'offering' => 'Weekly club',
            'order' => 1,
        ]);
        $this->assertDatabaseHas((new SocialMedia())->getTable(), [
            'type' => SocialMedia::TYPE_INSTAGRAM,
            'url' => 'https://www.instagram.com/ayupdigital',
        ]);
    }

    /**
     * @test
     */
    public function super_admin_can_approve_one_for_organisation_sign_up_form_without_service(): void
    {
        $now = Date::now();
        Date::setTestNow($now);

        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        /** @var \App\Models\UpdateRequest $updateRequest */
        $updateRequest = UpdateRequest::create([
            'updateable_type' => UpdateRequest::NEW_TYPE_ORGANISATION_SIGN_UP_FORM,
            'data' => [
                'user' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john.doe@example.com',
                    'phone' => '07700000000',
                    'password' => 'P@55w0rd.',
                ],
                'organisation' => [
                    'slug' => 'test-org',
                    'name' => 'Test Org',
                    'description' => 'Test description',
                    'url' => 'http://test-org.example.com',
                    'email' => 'info@test-org.example.com',
                    'phone' => '07700000000',
                ],
            ],
        ]);

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/approve");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'id' => $updateRequest->id,
            'actioning_user_id' => $user->id,
            'approved_at' => $now->toDateTimeString(),
        ]);
        $this->assertDatabaseHas((new User())->getTable(), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '07700000000',
        ]);
        $this->assertDatabaseHas((new Organisation())->getTable(), [
            'slug' => 'test-org',
            'name' => 'Test Org',
            'description' => 'Test description',
            'url' => 'http://test-org.example.com',
            'email' => 'info@test-org.example.com',
            'phone' => '07700000000',
        ]);

        $user = User::where('email', 'john.doe@example.com')->first();
        $organisation = Organisation::where('email', 'info@test-org.example.com')->first();

        $this->assertDatabaseHas((new UserRole())->getTable(), [
            'user_id' => $user->id,
            'organisation_id' => $organisation->id,
            'role_id' => Role::organisationAdmin()->id,
        ]);
    }

    /**
     * @test
     */
    public function super_admin_can_approve_one_for_organisation_sign_up_form_with_existing_organisation(): void
    {
        $now = Date::now();
        Date::setTestNow($now);

        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $organisation = Organisation::factory()->create();

        /** @var \App\Models\UpdateRequest $updateRequest */
        $updateRequest = UpdateRequest::create([
            'updateable_type' => UpdateRequest::NEW_TYPE_ORGANISATION_SIGN_UP_FORM,
            'data' => [
                'user' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john.doe@example.com',
                    'phone' => '07700000000',
                    'password' => 'P@55w0rd.',
                ],
                'organisation' => [
                    'id' => $organisation->id,
                ],
            ],
        ]);

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/approve");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'id' => $updateRequest->id,
            'actioning_user_id' => $user->id,
            'approved_at' => $now->toDateTimeString(),
        ]);
        $this->assertDatabaseHas((new User())->getTable(), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '07700000000',
        ]);

        $user = User::where('email', 'john.doe@example.com')->first();

        $this->assertDatabaseHas((new UserRole())->getTable(), [
            'user_id' => $user->id,
            'organisation_id' => $organisation->id,
            'role_id' => Role::organisationAdmin()->id,
        ]);
    }

    /**
     * @test
     */
    public function audit_created_when_approved(): void
    {
        $this->fakeEvents();

        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $serviceLocation = ServiceLocation::factory()->create();
        $updateRequest = $serviceLocation->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => [
                'name' => 'Test Name',
                'regular_opening_hours' => [],
                'holiday_opening_hours' => [],
            ],
        ]);

        $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/approve");

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $updateRequest) {
            return ($event->getAction() === Audit::ACTION_UPDATE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $updateRequest->id);
        });
    }

    /**
     * @test
     */
    public function user_roles_correctly_updated_when_service_assigned_to_different_organisation(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $service = Service::factory()->create();
        $service->serviceTaxonomies()->create([
            'taxonomy_id' => Taxonomy::category()->children()->firstOrFail()->id,
        ]);

        $serviceAdmin = User::factory()->create()->makeServiceAdmin($service);
        $organisationAdmin = User::factory()->create()->makeOrganisationAdmin($service->organisation);

        $newOrganisation = Organisation::factory()->create();
        $newOrganisationAdmin = User::factory()->create()->makeOrganisationAdmin($newOrganisation);

        $updateRequest = $service->updateRequests()->create([
            'user_id' => $user->id,
            'data' => [
                'organisation_id' => $newOrganisation->id,
            ],
        ]);

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/approve");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing(table(UserRole::class), [
            'user_id' => $serviceAdmin->id,
            'role_id' => Role::serviceAdmin()->id,
            'service_id' => $service->id,
        ]);
        $this->assertDatabaseMissing(table(UserRole::class), [
            'user_id' => $organisationAdmin->id,
            'role_id' => Role::organisationAdmin()->id,
            'service_id' => $service->id,
        ]);
        $this->assertDatabaseHas(table(UserRole::class), [
            'user_id' => $newOrganisationAdmin->id,
            'role_id' => Role::serviceAdmin()->id,
            'service_id' => $service->id,
        ]);
    }

    /*
     * Service specific.
     */

    /**
     * @test
     */
    public function last_modified_at_is_set_to_now_when_service_updated(): void
    {
        $oldNow = Date::now()->subMonths(6);
        $newNow = Date::now();
        Date::setTestNow($newNow);

        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $service = Service::factory()->create([
            'last_modified_at' => $oldNow,
            'created_at' => $oldNow,
            'updated_at' => $oldNow,
        ]);

        $updateRequest = $service->updateRequests()->create([
            'user_id' => User::factory()->create()->id,
            'data' => [
                'name' => 'Test Name',
            ],
        ]);

        $response = $this->json('PUT', "/core/v1/update-requests/{$updateRequest->id}/approve");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas($service->getTable(), [
            'last_modified_at' => $newNow->toDateTimeString(),
        ]);
    }

    /**
     * @test
     */
    public function lastModifiedAtIsUpdatedWhenServiceUpdatedByUpdateRequest()
    {
        $oldNow = Date::now()->subMonths(6);
        $newNow = Date::now();
        Date::setTestNow($newNow);

        $service = Service::factory()->create([
            'slug' => 'test-service',
            'status' => Service::STATUS_ACTIVE,
            'last_modified_at' => $oldNow,
            'created_at' => $oldNow,
            'updated_at' => $oldNow,
        ]);
        $taxonomy = Taxonomy::factory()->create();
        $service->syncTaxonomyRelationships(new Collection([$taxonomy]));
        $user = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $payload = [
            'name' => 'Test Service',
        ];
        $response = $this->json('PUT', "/core/v1/services/{$service->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['data' => $payload]);

        $updateRequest = UpdateRequest::find($response->json('id'));

        $this->assertEquals($updateRequest->data, $payload);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas($service->getTable(), [
            'id' => $service->id,
            'last_modified_at' => $newNow->toDateTimeString(),
        ]);
    }
}
