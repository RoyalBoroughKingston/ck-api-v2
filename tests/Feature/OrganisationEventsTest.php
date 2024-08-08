<?php

namespace Tests\Feature;

use DateTime;
use Carbon\Carbon;
use Tests\TestCase;
use App\Models\File;
use App\Models\User;
use App\Models\Audit;
use App\Models\Service;
use App\Models\Location;
use App\Models\Taxonomy;
use App\Models\Collection;
use App\Events\EndpointHit;
use Carbon\CarbonImmutable;
use App\Models\Organisation;
use App\Models\UpdateRequest;
use Illuminate\Http\Response;
use Laravel\Passport\Passport;
use App\Models\OrganisationEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use App\Models\OrganisationEventTaxonomy;

class OrganisationEventsTest extends TestCase
{
    /**
     * Get all OrganisationEvents
     */

    /**
     * @test
     */
    public function listOrganisationEventsAsGuest200(): void
    {
        $organisationEvent = OrganisationEvent::factory()->withImage()->create();

        $response = $this->json('GET', '/core/v1/organisation-events');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCollection([
            'id',
            'title',
            'slug',
            'start_date',
            'end_date',
            'start_time',
            'end_time',
            'intro',
            'description',
            'image',
            'is_free',
            'fees_text',
            'fees_url',
            'organiser_name',
            'organiser_phone',
            'organiser_email',
            'organiser_url',
            'booking_title',
            'booking_summary',
            'booking_url',
            'booking_cta',
            'is_virtual',
            'location_id',
            'organisation_id',
            'category_taxonomies',
            'created_at',
            'updated_at',
        ]);

        $response->assertJsonFragment([
            'id' => $organisationEvent->id,
            'title' => $organisationEvent->title,
            'slug' => $organisationEvent->slug,
            'start_date' => $organisationEvent->start_date->toDateString(),
            'end_date' => $organisationEvent->end_date->toDateString(),
            'start_time' => $organisationEvent->start_time,
            'end_time' => $organisationEvent->end_time,
            'intro' => $organisationEvent->intro,
            'description' => $organisationEvent->description,
            'image' => [
                'id' => $organisationEvent->imageFile->id,
                'mime_type' => $organisationEvent->imageFile->mime_type,
                'alt_text' => $organisationEvent->imageFile->meta['alt_text'],
                'url' => $organisationEvent->imageFile->url(),
            ],
            'is_free' => $organisationEvent->is_free,
            'fees_text' => $organisationEvent->fees_text,
            'fees_url' => $organisationEvent->fees_url,
            'organiser_name' => $organisationEvent->organisation_name,
            'organiser_phone' => $organisationEvent->organiser_phone,
            'organiser_email' => $organisationEvent->organiser_email,
            'organiser_url' => $organisationEvent->organiser_url,
            'booking_title' => $organisationEvent->booking_title,
            'booking_summary' => $organisationEvent->booking_summary,
            'booking_url' => $organisationEvent->booking_url,
            'booking_cta' => $organisationEvent->booking_cta,
            'is_virtual' => $organisationEvent->is_virtual,
            'location_id' => $organisationEvent->location_id,
            'organisation_id' => $organisationEvent->organisation_id,
            'category_taxonomies' => [],
            'created_at' => $organisationEvent->created_at->format(CarbonImmutable::ISO8601),
            'updated_at' => $organisationEvent->updated_at->format(CarbonImmutable::ISO8601),
        ]);
    }

    /**
     * @test
     */
    public function listOrganisationEventsFilterByOrganisationAsGuest200(): void
    {
        $organisationEvent1 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 1',
            'slug' => 'organisation-event-1',
        ]);
        $organisationEvent2 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 2',
            'slug' => 'organisation-event-2',
        ]);

        $response = $this->json('GET', "/core/v1/organisation-events?filter[organisation_id]={$organisationEvent1->organisation_id}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent1->id]);
        $response->assertJsonMissing(['id' => $organisationEvent2->id]);
    }

    /**
     * @test
     */
    public function listOrganisationEventsFilterByHomepageAsGuest200(): void
    {
        $organisationEvent1 = OrganisationEvent::factory()->homepage()->create([
            'title' => 'Organisation Event 1',
            'slug' => 'organisation-event-1',
        ]);
        $organisationEvent2 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 2',
            'slug' => 'organisation-event-2',
        ]);

        $response = $this->json('GET', '/core/v1/organisation-events?filter[homepage]=1');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent1->id]);
        $response->assertJsonMissing(['id' => $organisationEvent2->id]);
    }

    /**
     * @test
     */
    public function listOrganisationEventsOnlyPastEventsAsGuest200(): void
    {
        $future = $this->faker->dateTimeBetween('+1 week', '+3 weeks')->format('Y-m-d');
        $past = $this->faker->dateTimeBetween('-1 week', '-1 day')->format('Y-m-d');
        $today = (new DateTime('now'))->format('Y-m-d');
        $endtime = $this->faker->time('H:i:s', '+1 hour');
        $starttime = $this->faker->time('H:i:s', 'now');

        $organisationEvent1 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 1',
            'slug' => 'organisation-event-1',
            'start_date' => $future,
            'end_date' => $future,
            'start_time' => $starttime,
            'end_time' => $endtime,
        ]);
        $organisationEvent2 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 2',
            'slug' => 'organisation-event-2',
            'start_date' => $past,
            'end_date' => $past,
            'start_time' => $starttime,
            'end_time' => $endtime,
        ]);
        $organisationEvent3 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 3',
            'slug' => 'organisation-event-3',
            'start_date' => $today,
            'end_date' => $today,
            'start_time' => $starttime,
            'end_time' => $endtime,
        ]);

        $response = $this->json('GET', '/core/v1/organisation-events');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent1->id]);
        $response->assertJsonMissing(['id' => $organisationEvent2->id]);
        $response->assertJsonFragment(['id' => $organisationEvent3->id]);
    }

    /**
     * @test
     */
    public function listOrganisationEventsOnlyPastEventsAsOrganisationAdmin200(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $future = $this->faker->dateTimeBetween('+1 week', '+3 weeks')->format('Y-m-d');
        $past = $this->faker->dateTimeBetween('-1 week', 'now')->format('Y-m-d');
        $today = (new DateTime('now'))->format('Y-m-d');
        $endtime = $this->faker->time('H:i:s', '+1 hour');
        $starttime = $this->faker->time('H:i:s', 'now');
        $organisationEvent1 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 1',
            'slug' => 'organisation-event-1',
            'start_date' => $future,
            'end_date' => $future,
            'start_time' => $starttime,
            'end_time' => $endtime,
        ]);
        $organisationEvent2 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 2',
            'slug' => 'organisation-event-2',
            'start_date' => $past,
            'end_date' => $past,
            'start_time' => $starttime,
            'end_time' => $endtime,
        ]);
        $organisationEvent3 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 3',
            'slug' => 'organisation-event-3',
            'start_date' => $today,
            'end_date' => $today,
            'start_time' => $starttime,
            'end_time' => $endtime,
        ]);

        $response = $this->json('GET', '/core/v1/organisation-events');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent1->id]);
        $response->assertJsonFragment(['id' => $organisationEvent2->id]);
        $response->assertJsonFragment(['id' => $organisationEvent3->id]);
    }

    /**
     * @test
     */
    public function listOrganisationEventsDateOrderDescendingAsGuest200(): void
    {
        $date1 = $this->faker->dateTimeBetween('+2 weeks', '+3 weeks')->format('Y-m-d');
        $date2 = $this->faker->dateTimeBetween('+1 week', '+2 weeks')->format('Y-m-d');
        $date3 = $this->faker->dateTimeBetween('today', '+1 week')->format('Y-m-d');
        $endtime = $this->faker->time('H:i:s', '+1 hour');
        $starttime = $this->faker->time('H:i:s', 'now');

        $organisationEvent1 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 1',
            'slug' => 'organisation-event-1',
            'start_date' => $date1,
            'end_date' => $date1,
            'start_time' => $starttime,
            'end_time' => $endtime,
        ]);
        $organisationEvent2 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 3',
            'slug' => 'organisation-event-2',
            'start_date' => $date2,
            'end_date' => $date2,
            'start_time' => $starttime,
            'end_time' => $endtime,
        ]);
        $organisationEvent3 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 3',
            'slug' => 'organisation-event-3',
            'start_date' => $date3,
            'end_date' => $date3,
            'start_time' => $starttime,
            'end_time' => $endtime,
        ]);

        $response = $this->json('GET', '/core/v1/organisation-events');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent1->id]);
        $response->assertJsonFragment(['id' => $organisationEvent2->id]);
        $response->assertJsonFragment(['id' => $organisationEvent3->id]);

        $results = $response->json('data');

        $this->assertEquals($organisationEvent3->id, $results[0]['id']);
        $this->assertEquals($organisationEvent2->id, $results[1]['id']);
        $this->assertEquals($organisationEvent1->id, $results[2]['id']);
    }

    /**
     * @test
     */
    public function listOrganisationEventsFilterByDatesAsGuest200(): void
    {
        $date1 = $this->faker->dateTimeBetween('+2 days', '+1 weeks');
        $date2 = $this->faker->dateTimeBetween('+2 week', '+3 weeks');
        $date3 = (new DateTime('now'));
        $endtime = $this->faker->time('H:i:s', '+1 hour');
        $starttime = $this->faker->time('H:i:s', 'now');

        $organisationEvent1 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 1',
            'slug' => 'organisation-event-1',
            'start_date' => $date1->format('Y-m-d'),
            'end_date' => $date1->format('Y-m-d'),
            'start_time' => $starttime,
            'end_time' => $endtime,
        ]);
        $organisationEvent2 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 2',
            'slug' => 'organisation-event-2',
            'start_date' => $date2->format('Y-m-d'),
            'end_date' => $date2->format('Y-m-d'),
            'start_time' => $starttime,
            'end_time' => $endtime,
        ]);
        $organisationEvent3 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 3',
            'slug' => 'organisation-event-3',
            'start_date' => $date3->format('Y-m-d'),
            'end_date' => $date3->format('Y-m-d'),
            'start_time' => $starttime,
            'end_time' => $endtime,
        ]);

        $from = $date1->modify('-1 day')->format('Y-m-d');
        $response = $this->json('GET', "/core/v1/organisation-events?filter[ends_after]={$from}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent1->id]);
        $response->assertJsonFragment(['id' => $organisationEvent2->id]);
        $response->assertJsonMissing(['id' => $organisationEvent3->id]);

        $to = $date1->modify('+1 day')->format('Y-m-d');
        $response = $this->json('GET', "/core/v1/organisation-events?filter[ends_before]={$to}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent1->id]);
        $response->assertJsonFragment(['id' => $organisationEvent3->id]);
        $response->assertJsonMissing(['id' => $organisationEvent2->id]);

        $response = $this->json('GET', "/core/v1/organisation-events?filter[ends_after]={$from}&filter[ends_before]={$to}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent1->id]);
        $response->assertJsonMissing(['id' => $organisationEvent3->id]);
        $response->assertJsonMissing(['id' => $organisationEvent2->id]);
    }

    /**
     * @test
     */
    public function listOrganisationEventsFilterByAccessibilityAsGuest200(): void
    {
        $organisationEvent1 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 1',
            'slug' => 'organisation-event-1',
            'is_virtual' => false,
            'location_id' => function () {
                return Location::factory()->create([
                    'has_wheelchair_access' => false,
                    'has_induction_loop' => false,
                    'has_accessible_toilet' => false,
                ])->id;
            },
        ]);
        $organisationEvent2 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 2',
            'slug' => 'organisation-event-2',
            'is_virtual' => false,
            'location_id' => function () {
                return Location::factory()->create([
                    'has_wheelchair_access' => true,
                    'has_induction_loop' => false,
                    'has_accessible_toilet' => false,
                ])->id;
            },
        ]);
        $organisationEvent3 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 3',
            'slug' => 'organisation-event-3',
            'is_virtual' => false,
            'location_id' => function () {
                return Location::factory()->create([
                    'has_wheelchair_access' => false,
                    'has_induction_loop' => true,
                    'has_accessible_toilet' => false,
                ])->id;
            },
        ]);
        $organisationEvent4 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 4',
            'slug' => 'organisation-event-4',
            'is_virtual' => false,
            'location_id' => function () {
                return Location::factory()->create([
                    'has_wheelchair_access' => true,
                    'has_induction_loop' => true,
                    'has_accessible_toilet' => false,
                ])->id;
            },
        ]);
        $organisationEvent5 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 5',
            'slug' => 'organisation-event-5',
            'is_virtual' => false,
            'location_id' => function () {
                return Location::factory()->create([
                    'has_wheelchair_access' => false,
                    'has_induction_loop' => false,
                    'has_accessible_toilet' => true,
                ])->id;
            },
        ]);
        $organisationEvent6 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 6',
            'slug' => 'organisation-event-6',
            'is_virtual' => false,
            'location_id' => function () {
                return Location::factory()->create([
                    'has_wheelchair_access' => true,
                    'has_induction_loop' => false,
                    'has_accessible_toilet' => true,
                ])->id;
            },
        ]);
        $organisationEvent7 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 7',
            'slug' => 'organisation-event-7',
            'is_virtual' => false,
            'location_id' => function () {
                return Location::factory()->create([
                    'has_wheelchair_access' => true,
                    'has_induction_loop' => true,
                    'has_accessible_toilet' => true,
                ])->id;
            },
        ]);
        $organisationEvent8 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 8',
            'slug' => 'organisation-event-8',
            'is_virtual' => true,
        ]);

        $response = $this->json('GET', '/core/v1/organisation-events?filter[has_wheelchair_access]=1');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent2->id]);
        $response->assertJsonFragment(['id' => $organisationEvent4->id]);
        $response->assertJsonFragment(['id' => $organisationEvent6->id]);
        $response->assertJsonFragment(['id' => $organisationEvent7->id]);
        $response->assertJsonMissing(['id' => $organisationEvent1->id]);
        $response->assertJsonMissing(['id' => $organisationEvent3->id]);
        $response->assertJsonMissing(['id' => $organisationEvent5->id]);
        $response->assertJsonMissing(['id' => $organisationEvent8->id]);

        $response = $this->json('GET', '/core/v1/organisation-events?filter[has_induction_loop]=1');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent3->id]);
        $response->assertJsonFragment(['id' => $organisationEvent4->id]);
        $response->assertJsonFragment(['id' => $organisationEvent7->id]);
        $response->assertJsonMissing(['id' => $organisationEvent1->id]);
        $response->assertJsonMissing(['id' => $organisationEvent2->id]);
        $response->assertJsonMissing(['id' => $organisationEvent5->id]);
        $response->assertJsonMissing(['id' => $organisationEvent8->id]);

        $response = $this->json('GET', '/core/v1/organisation-events?filter[has_wheelchair_access]=1&filter[has_induction_loop]=1');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent4->id]);
        $response->assertJsonFragment(['id' => $organisationEvent7->id]);
        $response->assertJsonMissing(['id' => $organisationEvent1->id]);
        $response->assertJsonMissing(['id' => $organisationEvent2->id]);
        $response->assertJsonMissing(['id' => $organisationEvent3->id]);
        $response->assertJsonMissing(['id' => $organisationEvent5->id]);
        $response->assertJsonMissing(['id' => $organisationEvent6->id]);
        $response->assertJsonMissing(['id' => $organisationEvent8->id]);

        $response = $this->json('GET', '/core/v1/organisation-events?filter[has_accessible_toilet]=1');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent5->id]);
        $response->assertJsonFragment(['id' => $organisationEvent6->id]);
        $response->assertJsonFragment(['id' => $organisationEvent7->id]);
        $response->assertJsonMissing(['id' => $organisationEvent1->id]);
        $response->assertJsonMissing(['id' => $organisationEvent2->id]);
        $response->assertJsonMissing(['id' => $organisationEvent3->id]);
        $response->assertJsonMissing(['id' => $organisationEvent4->id]);
        $response->assertJsonMissing(['id' => $organisationEvent8->id]);

        $response = $this->json('GET', '/core/v1/organisation-events?filter[has_wheelchair_access]=1&filter[has_induction_loop]=1&filter[has_accessible_toilet]=1');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent7->id]);
        $response->assertJsonMissing(['id' => $organisationEvent1->id]);
        $response->assertJsonMissing(['id' => $organisationEvent2->id]);
        $response->assertJsonMissing(['id' => $organisationEvent3->id]);
        $response->assertJsonMissing(['id' => $organisationEvent4->id]);
        $response->assertJsonMissing(['id' => $organisationEvent5->id]);
        $response->assertJsonMissing(['id' => $organisationEvent6->id]);
        $response->assertJsonMissing(['id' => $organisationEvent8->id]);

        $response = $this->json('GET', '/core/v1/organisation-events?filter[has_wheelchair_access]=0');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent1->id]);
        $response->assertJsonFragment(['id' => $organisationEvent3->id]);
        $response->assertJsonFragment(['id' => $organisationEvent5->id]);
        $response->assertJsonMissing(['id' => $organisationEvent2->id]);
        $response->assertJsonMissing(['id' => $organisationEvent4->id]);
        $response->assertJsonMissing(['id' => $organisationEvent6->id]);
        $response->assertJsonMissing(['id' => $organisationEvent7->id]);
        $response->assertJsonMissing(['id' => $organisationEvent8->id]);
    }

    /**
     * @test
     */
    public function listOrganisationEventsFilterByCollectionAsGuest200(): void
    {
        $organisationEventCollection1 = Collection::factory()->typeOrganisationEvent()->create();
        $organisationEventCollection2 = Collection::factory()->typeOrganisationEvent()->create();
        $taxonomy1 = Taxonomy::factory()->create();
        $taxonomy2 = Taxonomy::factory()->create();
        $taxonomy3 = Taxonomy::factory()->create();
        $organisationEventCollection1->syncCollectionTaxonomies((new \Illuminate\Database\Eloquent\Collection([$taxonomy1])));
        $organisationEventCollection2->syncCollectionTaxonomies((new \Illuminate\Database\Eloquent\Collection([$taxonomy2])));

        $organisationEvent1 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 1',
            'slug' => 'organisation-event-1',
        ]);
        $organisationEvent2 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 2',
            'slug' => 'organisation-event-2',
        ]);
        $organisationEvent3 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 3',
            'slug' => 'organisation-event-3',
        ]);
        $organisationEvent4 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 4',
            'slug' => 'organisation-event-4',
        ]);
        $organisationEvent1->syncTaxonomyRelationships((new \Illuminate\Database\Eloquent\Collection([$taxonomy1])));
        $organisationEvent2->syncTaxonomyRelationships((new \Illuminate\Database\Eloquent\Collection([$taxonomy2])));
        $organisationEvent3->syncTaxonomyRelationships((new \Illuminate\Database\Eloquent\Collection([$taxonomy3])));

        $response = $this->json('GET', "/core/v1/organisation-events?filter[collections]={$organisationEventCollection1->id}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent1->id]);
        $response->assertJsonMissing(['id' => $organisationEvent2->id]);
        $response->assertJsonMissing(['id' => $organisationEvent3->id]);
        $response->assertJsonMissing(['id' => $organisationEvent4->id]);

        $response = $this->json('GET', "/core/v1/organisation-events?filter[collections]={$organisationEventCollection1->id},{$organisationEventCollection2->id}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent1->id]);
        $response->assertJsonFragment(['id' => $organisationEvent2->id]);
        $response->assertJsonMissing(['id' => $organisationEvent3->id]);
        $response->assertJsonMissing(['id' => $organisationEvent4->id]);
    }

    /**
     * @test
     */
    public function listOrganisationEventsOnlyRelatedOrganisationsAsOrganisationAdmin200(): void
    {
        $organisation1 = Organisation::factory()->create();
        $organisation2 = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation1);

        Passport::actingAs($user);

        $organisationEvent1 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 1',
            'slug' => 'organisation-event-1',
            'organisation_id' => $organisation1->id,
        ]);
        $organisationEvent2 = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event 2',
            'slug' => 'organisation-event-2',
            'organisation_id' => $organisation2->id,
        ]);

        $response = $this->json('GET', '/core/v1/organisation-events?filter[has_permission]=true');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $organisationEvent1->id]);
        $response->assertJsonMissing(['id' => $organisationEvent2->id]);
    }

    /**
     * @test
     */
    public function listOrganisationEventsCreatesAuditAsGuest200(): void
    {
        $this->fakeEvents();

        $event = OrganisationEvent::factory()->create();

        $this->json('GET', '/core/v1/organisation-events');

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) {
            return $event->getAction() === Audit::ACTION_READ;
        });
    }

    /**
     * Create an OrganisationEvent
     */

    /**
     * @test
     */
    public function postCreateOrganisationEventAsGuest401(): void
    {
        $response = $this->json('POST', '/core/v1/organisation-events');

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventAsServiceWorker403(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/organisation-events');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventAsServiceAdmin403(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/organisation-events');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventAsOrganisationAdmin200(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $image = Storage::disk('local')->get('/test-data/image.png');
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $imageResponse = $this->json('POST', '/core/v1/files', [
            'is_private' => false,
            'mime_type' => 'image/png',
            'file' => 'data:image/png;base64,' . base64_encode($image),
        ]);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => false,
            'fees_text' => $this->faker->sentence(),
            'fees_url' => $this->faker->url(),
            'organiser_name' => $this->faker->name(),
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail(),
            'organiser_url' => $this->faker->url(),
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence(),
            'booking_url' => $this->faker->url(),
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
            'image_file_id' => $this->getResponseContent($imageResponse, 'data.id'),
            'category_taxonomies' => [],
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment($payload);

        //Then an update request should be created for the new event
        $updateRequest = UpdateRequest::findOrFail($response->json('id'));

        $this->assertEquals($updateRequest->data, $payload);

        //And the organisation event should not yet be created
        $this->assertEmpty(OrganisationEvent::all());

        $this->approveUpdateRequest($updateRequest->id);

        unset($payload['category_taxonomies']);

        $payload['slug'] = 'a-new-organisation-event';

        $this->assertDatabaseHas('organisation_events', $payload);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventAsGlobalAdmin200(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => false,
            'fees_text' => $this->faker->sentence(),
            'fees_url' => $this->faker->url(),
            'organiser_name' => $this->faker->name(),
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail(),
            'organiser_url' => $this->faker->url(),
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence(),
            'booking_url' => $this->faker->url(),
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
            'category_taxonomies' => [],
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the new event
        $updateRequest = UpdateRequest::findOrFail($response->json('id'));

        $this->assertEquals($updateRequest->data, $payload);

        //And the organisation event should not yet be created
        $this->assertEmpty(OrganisationEvent::all());

        $this->approveUpdateRequest($updateRequest->id);

        unset($payload['category_taxonomies']);

        $payload['slug'] = 'a-new-organisation-event';

        // The organisation event is created
        $this->assertDatabaseHas((new OrganisationEvent())->getTable(), $payload);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventAsSuperAdmin201(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();

        Passport::actingAs($user);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => false,
            'fees_text' => $this->faker->sentence(),
            'fees_url' => $this->faker->url(),
            'organiser_name' => $this->faker->name(),
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail(),
            'organiser_url' => $this->faker->url(),
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence(),
            'booking_url' => $this->faker->url(),
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
            'category_taxonomies' => [],
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);

        unset($payload['category_taxonomies']);

        $payload['slug'] = 'a-new-organisation-event';

        // The organisation event is created
        $this->assertDatabaseHas((new OrganisationEvent())->getTable(), $payload);

        // And no update request was created
        $this->assertEmpty(UpdateRequest::all());
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventAsOtherOrganisationAdmin403(): void
    {
        $organisation1 = Organisation::factory()->create();
        $organisation2 = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation2);

        Passport::actingAs($user);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => false,
            'fees_text' => $this->faker->sentence(),
            'fees_url' => $this->faker->url(),
            'organiser_name' => $this->faker->name(),
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail(),
            'organiser_url' => $this->faker->url(),
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence(),
            'booking_url' => $this->faker->url(),
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation1->id,
            'category_taxonomies' => [],
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function postCreateHomepageOrganisationEventAsOrganisationAdmin422(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => false,
            'fees_text' => $this->faker->sentence(),
            'fees_url' => $this->faker->url(),
            'organiser_name' => $this->faker->name(),
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail(),
            'organiser_url' => $this->faker->url(),
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence(),
            'booking_url' => $this->faker->url(),
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => true,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
            'category_taxonomies' => [],
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function postCreateHomepageOrganisationEventAsGlobalAdmin200(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => false,
            'fees_text' => $this->faker->sentence(),
            'fees_url' => $this->faker->url(),
            'organiser_name' => $this->faker->name(),
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail(),
            'organiser_url' => $this->faker->url(),
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence(),
            'booking_url' => $this->faker->url(),
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => true,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
            'category_taxonomies' => [],
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the new event
        $updateRequest = UpdateRequest::findOrFail($response->json('id'));

        $this->assertEquals($updateRequest->data, $payload);

        $this->approveUpdateRequest($updateRequest->id);

        unset($payload['category_taxonomies']);

        // The organisation event is created
        $this->assertDatabaseHas((new OrganisationEvent())->getTable(), $payload);
    }

    /**
     * @test
     */
    public function postCreateHomepageOrganisationEventAsSuperAdmin201(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();

        Passport::actingAs($user);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => false,
            'fees_text' => $this->faker->sentence(),
            'fees_url' => $this->faker->url(),
            'organiser_name' => $this->faker->name(),
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail(),
            'organiser_url' => $this->faker->url(),
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence(),
            'booking_url' => $this->faker->url(),
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => true,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
            'category_taxonomies' => [],
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);

        unset($payload['image_file_id']);
        $payload['has_image'] = false;

        $response->assertJsonFragment($payload);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventWithTaxonomiesAsOrganisationAdmin422(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $taxonomy = Taxonomy::factory()->lgaStandards()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => false,
            'fees_text' => $this->faker->sentence(),
            'fees_url' => $this->faker->url(),
            'organiser_name' => $this->faker->name(),
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail(),
            'organiser_url' => $this->faker->url(),
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence(),
            'booking_url' => $this->faker->url(),
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => true,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
            'category_taxonomies' => [$taxonomy->id],
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventWithTaxonomiesAsGlobalAdmin200(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $taxonomy = Taxonomy::factory()->lgaStandards()->create();
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => false,
            'fees_text' => $this->faker->sentence(),
            'fees_url' => $this->faker->url(),
            'organiser_name' => $this->faker->name(),
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail(),
            'organiser_url' => $this->faker->url(),
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence(),
            'booking_url' => $this->faker->url(),
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => true,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
            'category_taxonomies' => [$taxonomy->id],
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the new event
        $updateRequest = UpdateRequest::findOrFail($response->json('id'));

        $this->assertEquals($updateRequest->data, $payload);

        $this->approveUpdateRequest($updateRequest->id);

        unset($payload['category_taxonomies']);

        // The organisation event is created
        $this->assertDatabaseHas((new OrganisationEvent())->getTable(), $payload);

        $organisationEvent = OrganisationEvent::where('title', $payload['title'])->firstOrFail();
        $this->assertDatabaseHas(table(OrganisationEventTaxonomy::class), [
            'organisation_event_id' => $organisationEvent->id,
            'taxonomy_id' => $taxonomy->id,
        ]);
        $this->assertDatabaseHas(table(OrganisationEventTaxonomy::class), [
            'organisation_event_id' => $organisationEvent->id,
            'taxonomy_id' => $taxonomy->parent_id,
        ]);

        $response = $this->getJson('/core/v1/organisation-events/' . $organisationEvent->id);

        $response->assertStatus(Response::HTTP_OK);

        $responsePayload = $payload;
        $responsePayload['category_taxonomies'] = [
            [
                'id' => $taxonomy->parent->id,
                'parent_id' => $taxonomy->parent->parent_id,
                'slug' => $taxonomy->parent->slug,
                'name' => $taxonomy->parent->name,
                'created_at' => $taxonomy->parent->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => $taxonomy->parent->updated_at->format(CarbonImmutable::ISO8601),
            ],
            [
                'id' => $taxonomy->id,
                'parent_id' => $taxonomy->parent_id,
                'slug' => $taxonomy->slug,
                'name' => $taxonomy->name,
                'created_at' => $taxonomy->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => $taxonomy->updated_at->format(CarbonImmutable::ISO8601),
            ],
        ];
        $response->assertJsonFragment($responsePayload);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventWithTaxonomiesAsSuperAdmin201(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $taxonomy = Taxonomy::factory()->lgaStandards()->create();
        $user = User::factory()->create()->makeSuperAdmin();

        Passport::actingAs($user);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => false,
            'fees_text' => $this->faker->sentence(),
            'fees_url' => $this->faker->url(),
            'organiser_name' => $this->faker->name(),
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail(),
            'organiser_url' => $this->faker->url(),
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence(),
            'booking_url' => $this->faker->url(),
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => true,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
            'category_taxonomies' => [$taxonomy->id],
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);

        $organisationEvent = OrganisationEvent::findOrFail($response->json('data.id'));
        $this->assertDatabaseHas(table(OrganisationEventTaxonomy::class), [
            'organisation_event_id' => $organisationEvent->id,
            'taxonomy_id' => $taxonomy->id,
        ]);
        $this->assertDatabaseHas(table(OrganisationEventTaxonomy::class), [
            'organisation_event_id' => $organisationEvent->id,
            'taxonomy_id' => $taxonomy->parent_id,
        ]);

        $responsePayload = $payload;
        $responsePayload['category_taxonomies'] = [
            [
                'id' => $taxonomy->parent->id,
                'parent_id' => $taxonomy->parent->parent_id,
                'slug' => $taxonomy->parent->slug,
                'name' => $taxonomy->parent->name,
                'created_at' => $taxonomy->parent->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => $taxonomy->parent->updated_at->format(CarbonImmutable::ISO8601),
            ],
            [
                'id' => $taxonomy->id,
                'parent_id' => $taxonomy->parent_id,
                'slug' => $taxonomy->slug,
                'name' => $taxonomy->name,
                'created_at' => $taxonomy->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => $taxonomy->updated_at->format(CarbonImmutable::ISO8601),
            ],
        ];
        $response->assertJsonFragment($responsePayload);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventCreatesAuditAsOrganisationAdmin201(): void
    {
        $this->fakeEvents();

        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => false,
            'fees_text' => $this->faker->sentence(),
            'fees_url' => $this->faker->url(),
            'organiser_name' => $this->faker->name(),
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail(),
            'organiser_url' => $this->faker->url(),
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence(),
            'booking_url' => $this->faker->url(),
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => false,
            'is_virtual' => true,
            'location_id' => null,
            'organisation_id' => $organisation->id,
            'category_taxonomies' => [],
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_OK);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $response) {
            return ($event->getAction() === Audit::ACTION_CREATE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->data['title'] === $this->getResponseContent($response)['data']['title']);
        });
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventWithImageAsGlobalAdmin200(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $image = Storage::disk('local')->get('/test-data/image.png');
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $imageResponse = $this->json('POST', '/core/v1/files', [
            'is_private' => false,
            'mime_type' => 'image/png',
            'alt_text' => 'image description',
            'file' => 'data:image/png;base64,' . base64_encode($image),
        ]);
        $imageResponse->assertStatus(Response::HTTP_CREATED);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => false,
            'fees_text' => $this->faker->sentence(),
            'fees_url' => $this->faker->url(),
            'organiser_name' => $this->faker->name(),
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail(),
            'organiser_url' => $this->faker->url(),
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence(),
            'booking_url' => $this->faker->url(),
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
            'image_file_id' => $this->getResponseContent($imageResponse, 'data.id'),
            'category_taxonomies' => [],
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the new event
        $updateRequest = UpdateRequest::findOrFail($response->json('id'));

        $this->assertEquals($updateRequest->data, $payload);

        $this->approveUpdateRequest($updateRequest->id);

        unset($payload['category_taxonomies']);

        // The organisation event is created
        $this->assertDatabaseHas((new OrganisationEvent())->getTable(), $payload);

        $organisationEvent = OrganisationEvent::where('title', $payload['title'])->firstOrFail();

        $content = $this->get("/core/v1/organisation-events/{$organisationEvent->id}/image.png")->content();
        $this->assertEquals($image, $content);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventWithImageAsOrganisationAdmin200(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $image = Storage::disk('local')->get('/test-data/image.png');
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $imageResponse = $this->json('POST', '/core/v1/files', [
            'is_private' => false,
            'mime_type' => 'image/png',
            'alt_text' => 'image description',
            'file' => 'data:image/png;base64,' . base64_encode($image),
        ]);
        $imageResponse->assertStatus(Response::HTTP_CREATED);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => false,
            'fees_text' => $this->faker->sentence(),
            'fees_url' => $this->faker->url(),
            'organiser_name' => $this->faker->name(),
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail(),
            'organiser_url' => $this->faker->url(),
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence(),
            'booking_url' => $this->faker->url(),
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
            'image_file_id' => $this->getResponseContent($imageResponse, 'data.id'),
            'category_taxonomies' => [],
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment($payload);

        $responseData = json_decode($response->getContent());

        //Then an update request should be created for the new event
        $updateRequest = UpdateRequest::findOrFail($response->json('id'));

        $this->assertEquals($updateRequest->data, $payload);

        // Simulate frontend check by making call with UpdateRequest ID.
        $updateRequestId = $responseData->id;

        Passport::actingAs(User::factory()->create()->makeSuperAdmin());

        $updateRequestCheckResponse = $this->get(
            route(
                'core.v1.update-requests.show',
                ['update_request' => $updateRequestId]
            )
        );

        $updateRequestCheckResponse->assertSuccessful();
        $updateRequestResponseData = json_decode($updateRequestCheckResponse->getContent(), true);

        $this->assertEquals($updateRequestResponseData['data'], $payload);
        //And the organisation event should not yet be created
        $this->assertEmpty(OrganisationEvent::all());

        // Get the event image for the update request
        $content = $this->get("/core/v1/organisation-events/new/image.png?update_request_id=$updateRequestId")->content();
        $this->assertEquals($image, $content);

        $updateRequestApproveResponse = $this->put(
            route(
                'core.v1.update-requests.approve',
                ['update_request' => $updateRequestId]
            )
        );

        $updateRequestApproveResponse->assertSuccessful();

        unset($payload['category_taxonomies']);

        $this->assertDatabaseHas('organisation_events', $payload);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventMinimumFieldsAsOrganisationAdmin200(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'organiser_name' => null,
            'organiser_phone' => null,
            'organiser_email' => null,
            'organiser_url' => null,
            'booking_title' => null,
            'booking_summary' => null,
            'booking_url' => null,
            'booking_cta' => null,
            'homepage' => false,
            'is_virtual' => true,
            'location_id' => null,
            'organisation_id' => $organisation->id,
            'category_taxonomies' => [],
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventRequiredFieldsAsOrganisationAdmin422(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');

        $response = $this->json('POST', '/core/v1/organisation-events', []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/organisation-events', [
            'title' => 'A New Organisation Event',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/organisation-events', [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/organisation-events', [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/organisation-events', [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/organisation-events', [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/organisation-events', [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/organisation-events', [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/organisation-events', [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventCreatesUniqueSlugAsOrganisationAdmin200(): void
    {
        $organisation = Organisation::factory()->create();

        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'organiser_name' => null,
            'organiser_phone' => null,
            'organiser_email' => null,
            'organiser_url' => null,
            'booking_title' => null,
            'booking_summary' => null,
            'booking_url' => null,
            'booking_cta' => null,
            'homepage' => false,
            'is_virtual' => true,
            'location_id' => null,
            'organisation_id' => $organisation->id,
            'category_taxonomies' => [],
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest1 = UpdateRequest::find($response->json('id'));

        $this->assertEquals('a-new-organisation-event', $updateRequest1->data['slug']);

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest2 = UpdateRequest::find($response->json('id'));

        $this->assertEquals('a-new-organisation-event', $updateRequest2->data['slug']);

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest3 = UpdateRequest::find($response->json('id'));

        $this->assertEquals('a-new-organisation-event', $updateRequest3->data['slug']);

        $updateRequest1 = $this->approveUpdateRequest($updateRequest1->id);

        $this->assertDatabaseHas('organisation_events', [
            'id' => $updateRequest1['updateable_id'],
            'slug' => 'a-new-organisation-event',
        ]);

        $updateRequest2 = $this->approveUpdateRequest($updateRequest2->id);

        $this->assertDatabaseHas('organisation_events', [
            'id' => $updateRequest2['updateable_id'],
            'slug' => 'a-new-organisation-event-1',
        ]);

        $updateRequest3 = $this->approveUpdateRequest($updateRequest3->id);

        $this->assertDatabaseHas('organisation_events', [
            'id' => $updateRequest3['updateable_id'],
            'slug' => 'a-new-organisation-event-2',
        ]);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventCreatesUniqueSlugAsSuperAdmin201(): void
    {
        $organisation = Organisation::factory()->create();

        $user = User::factory()->create()->makeSuperAdmin();

        Passport::actingAs($user);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'organiser_name' => null,
            'organiser_phone' => null,
            'organiser_email' => null,
            'organiser_url' => null,
            'booking_title' => null,
            'booking_summary' => null,
            'booking_url' => null,
            'booking_cta' => null,
            'homepage' => false,
            'is_virtual' => true,
            'location_id' => null,
            'organisation_id' => $organisation->id,
            'category_taxonomies' => [],
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);

        $this->assertEquals('a-new-organisation-event', $response->json('data.slug'));

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);

        $this->assertEquals('a-new-organisation-event-1', $response->json('data.slug'));

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_CREATED);

        $this->assertEquals('a-new-organisation-event-2', $response->json('data.slug'));
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventIfNotFreeRequiresFeeDataAsOrganisationAdmin200(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'organiser_name' => $this->faker->name(),
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail(),
            'organiser_url' => $this->faker->url(),
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence(),
            'booking_url' => $this->faker->url(),
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
            'category_taxonomies' => [],
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $payload['is_free'] = false;

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload['fees_url'] = $this->faker->url();

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload['fees_text'] = $this->faker->sentence();

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventWithOrganiserRequiresOrganiserContactAsOrganisationAdmin200(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'organiser_name' => null,
            'organiser_phone' => null,
            'organiser_email' => null,
            'organiser_url' => null,
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence(),
            'booking_url' => $this->faker->url(),
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
            'category_taxonomies' => [],
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $payload['organiser_name'] = $this->faker->name();

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload['organiser_phone'] = random_uk_phone();

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $payload['organiser_phone'] = null;
        $payload['organiser_email'] = $this->faker->safeEmail();

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $payload['organiser_email'] = null;
        $payload['organiser_url'] = $this->faker->url();

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventWithBookingDetailsRequiresAllBookingFieldsAsOrganisationAdmin200(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'organiser_name' => $this->faker->name(),
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail(),
            'organiser_url' => $this->faker->url(),
            'booking_title' => null,
            'booking_summary' => null,
            'booking_url' => null,
            'booking_cta' => null,
            'homepage' => false,
            'is_virtual' => true,
            'location_id' => null,
            'organisation_id' => $organisation->id,
            'category_taxonomies' => [],
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $payload['booking_title'] = $this->faker->sentence(3);

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload['booking_summary'] = $this->faker->sentence();

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload['booking_url'] = $this->faker->url();

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload['booking_cta'] = $this->faker->words(2, true);

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventStartsInPastAsOrganisationAdmin200(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $start = $this->faker->dateTimeBetween('-7 days', '-1 days')->format('Y-m-d');
        $end = $this->faker->dateTimeBetween('+1 days', '+7 days')->format('Y-m-d');

        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $start,
            'end_date' => $end,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'organiser_name' => null,
            'organiser_phone' => null,
            'organiser_email' => null,
            'organiser_url' => null,
            'booking_title' => null,
            'booking_summary' => null,
            'booking_url' => null,
            'booking_cta' => null,
            'homepage' => false,
            'is_virtual' => true,
            'location_id' => null,
            'organisation_id' => $organisation->id,
            'category_taxonomies' => [],
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventEndsInPastAsOrganisationAdmin422(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $start = $this->faker->dateTimeBetween('-7 days', '-4 days')->format('Y-m-d');
        $end = $this->faker->dateTimeBetween('-3 days', '-1 days')->format('Y-m-d');

        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $start,
            'end_date' => $end,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'organiser_name' => null,
            'organiser_phone' => null,
            'organiser_email' => null,
            'organiser_url' => null,
            'booking_title' => null,
            'booking_summary' => null,
            'booking_url' => null,
            'booking_cta' => null,
            'homepage' => false,
            'is_virtual' => true,
            'location_id' => null,
            'organisation_id' => $organisation->id,
            'category_taxonomies' => [],
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventEndsBeforeItStartsAsOrganisationAdmin422(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $start = $this->faker->dateTimeBetween('+4 days', '+7 days')->format('Y-m-d');
        $end = $this->faker->dateTimeBetween('+1 days', '+3 days')->format('Y-m-d');

        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $start,
            'end_date' => $end,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'organiser_name' => null,
            'organiser_phone' => null,
            'organiser_email' => null,
            'organiser_url' => null,
            'booking_title' => null,
            'booking_summary' => null,
            'booking_url' => null,
            'booking_cta' => null,
            'homepage' => false,
            'is_virtual' => true,
            'location_id' => null,
            'organisation_id' => $organisation->id,
            'category_taxonomies' => [],
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function postCreateOrganisationEventStartsTodayAsOrganisationAdmin200(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $start = Carbon::now()->format('Y-m-d');
        $end = Carbon::now()->addDay()->format('Y-m-d');

        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $start,
            'end_date' => $end,
            'start_time' => '09:00:00',
            'end_time' => '21:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => true,
            'fees_text' => null,
            'fees_url' => null,
            'organiser_name' => null,
            'organiser_phone' => null,
            'organiser_email' => null,
            'organiser_url' => null,
            'booking_title' => null,
            'booking_summary' => null,
            'booking_url' => null,
            'booking_cta' => null,
            'homepage' => false,
            'is_virtual' => true,
            'location_id' => null,
            'organisation_id' => $organisation->id,
            'category_taxonomies' => [],
        ];

        $response = $this->json('POST', '/core/v1/organisation-events', $payload);

        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * Get a single OrganisationEvent
     */

    /**
     * @test
     */
    public function getSingleOrganisationEventAsGuest200(): void
    {
        $organisationEvent = OrganisationEvent::factory()->withImage()->create();

        $response = $this->json('GET', "/core/v1/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment([
            'id' => $organisationEvent->id,
            'title' => $organisationEvent->title,
            'slug' => $organisationEvent->slug,
            'start_date' => $organisationEvent->start_date->toDateString(),
            'end_date' => $organisationEvent->end_date->toDateString(),
            'start_time' => $organisationEvent->start_time,
            'end_time' => $organisationEvent->end_time,
            'intro' => $organisationEvent->intro,
            'description' => $organisationEvent->description,
            'image' => [
                'id' => $organisationEvent->imageFile->id,
                'mime_type' => $organisationEvent->imageFile->mime_type,
                'alt_text' => $organisationEvent->imageFile->meta['alt_text'],
                'url' => $organisationEvent->imageFile->url(),
            ],
            'is_free' => $organisationEvent->is_free,
            'fees_text' => $organisationEvent->fees_text,
            'fees_url' => $organisationEvent->fees_url,
            'organiser_name' => $organisationEvent->organisation_name,
            'organiser_phone' => $organisationEvent->organiser_phone,
            'organiser_email' => $organisationEvent->organiser_email,
            'organiser_url' => $organisationEvent->organiser_url,
            'booking_title' => $organisationEvent->booking_title,
            'booking_summary' => $organisationEvent->booking_summary,
            'booking_url' => $organisationEvent->booking_url,
            'booking_cta' => $organisationEvent->booking_cta,
            'homepage' => $organisationEvent->homepage,
            'is_virtual' => $organisationEvent->is_virtual,
            'google_calendar_link' => $organisationEvent->googleCalendarLink,
            'microsoft_calendar_link' => $organisationEvent->microsoftCalendarLink,
            'apple_calendar_link' => $organisationEvent->appleCalendarLink,
            'location_id' => $organisationEvent->location_id,
            'organisation_id' => $organisationEvent->organisation_id,
            'category_taxonomies' => [],
            'created_at' => $organisationEvent->created_at->format(CarbonImmutable::ISO8601),
            'updated_at' => $organisationEvent->updated_at->format(CarbonImmutable::ISO8601),
        ]);
    }

    /**
     * @test
     */
    public function getSingleOrganisationEventBySlugAsGuest200(): void
    {
        $organisationEvent = OrganisationEvent::factory()->create();

        $response = $this->json('GET', "/core/v1/organisation-events/{$organisationEvent->slug}");

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment([
            'id' => $organisationEvent->id,
            'title' => $organisationEvent->title,
            'slug' => $organisationEvent->slug,
            'start_date' => $organisationEvent->start_date->toDateString(),
            'end_date' => $organisationEvent->end_date->toDateString(),
            'start_time' => $organisationEvent->start_time,
            'end_time' => $organisationEvent->end_time,
            'intro' => $organisationEvent->intro,
            'description' => $organisationEvent->description,
            'image' => null,
            'is_free' => $organisationEvent->is_free,
            'fees_text' => $organisationEvent->fees_text,
            'fees_url' => $organisationEvent->fees_url,
            'organiser_name' => $organisationEvent->organisation_name,
            'organiser_phone' => $organisationEvent->organiser_phone,
            'organiser_email' => $organisationEvent->organiser_email,
            'organiser_url' => $organisationEvent->organiser_url,
            'booking_title' => $organisationEvent->booking_title,
            'booking_summary' => $organisationEvent->booking_summary,
            'booking_url' => $organisationEvent->booking_url,
            'booking_cta' => $organisationEvent->booking_cta,
            'homepage' => $organisationEvent->homepage,
            'is_virtual' => $organisationEvent->is_virtual,
            'google_calendar_link' => $organisationEvent->googleCalendarLink,
            'microsoft_calendar_link' => $organisationEvent->microsoftCalendarLink,
            'apple_calendar_link' => $organisationEvent->appleCalendarLink,
            'location_id' => $organisationEvent->location_id,
            'organisation_id' => $organisationEvent->organisation_id,
            'category_taxonomies' => [],
            'created_at' => $organisationEvent->created_at->format(CarbonImmutable::ISO8601),
            'updated_at' => $organisationEvent->updated_at->format(CarbonImmutable::ISO8601),
        ]);
    }

    /**
     * @test
     */
    public function getSingleOrganisationEventAsGuestCreatesAudit200(): void
    {
        $this->fakeEvents();

        $organisationEvent = OrganisationEvent::factory()->create();

        $response = $this->json('GET', "/core/v1/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_OK);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($organisationEvent) {
            return ($event->getAction() === Audit::ACTION_READ) &&
                ($event->getModel()->id === $organisationEvent->id);
        });
    }

    /**
     * @test
     */
    public function getSingleOrganisationEventImagePngAsGuest200(): void
    {
        $image = File::factory()->imagePng()->create();
        $organisationEvent = OrganisationEvent::factory([
            'image_file_id' => $image->id,
        ])->create();

        $response = $this->get("/core/v1/organisation-events/{$organisationEvent->id}/image.png");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertHeader('Content-Type', 'image/png');

        $this->assertEquals(Storage::disk('local')->get('/test-data/image.png'), $response->content());
    }

    /**
     * @test
     */
    public function getSingleOrganisationEventImageJpgAsGuest200(): void
    {
        $image = File::factory()->imageJpg()->create();
        $organisationEvent = OrganisationEvent::factory([
            'image_file_id' => $image->id,
        ])->create();

        $response = $this->get("/core/v1/organisation-events/{$organisationEvent->id}/image.jpg");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertHeader('Content-Type', 'image/jpeg');

        $this->assertEquals(Storage::disk('local')->get('/test-data/image.jpg'), $response->content());
    }

    /**
     * @test
     */
    public function getSingleOrganisationEventImageSvgAsGuest200(): void
    {
        $image = File::factory()->imageSvg()->create();
        $organisationEvent = OrganisationEvent::factory([
            'image_file_id' => $image->id,
        ])->create();

        $response = $this->get("/core/v1/organisation-events/{$organisationEvent->id}/image.svg");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertHeader('Content-Type', 'image/svg+xml');

        $this->assertEquals(Storage::disk('local')->get('/test-data/image.svg'), $response->content());
    }

    /**
     * @test
     */
    public function getSingleOrganisationEventImageCreatesAuditAsGuest200(): void
    {
        $this->fakeEvents();

        $organisationEvent = OrganisationEvent::factory()->withImage()->create();

        $response = $this->get("/core/v1/organisation-events/{$organisationEvent->id}/image.png");

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($organisationEvent) {
            return ($event->getAction() === Audit::ACTION_READ) &&
                ($event->getModel()->id === $organisationEvent->id);
        });
    }

    /**
     * @test
     */
    public function getSingleOrganisationEventIcalAsGuest200(): void
    {
        $organisationEvent = OrganisationEvent::factory()->notVirtual()->withOrganiser()->create();

        $now = new DateTime();
        $start = new Carbon($organisationEvent->start_date);
        [$startHour, $startMinute, $startSecond] = explode(':', $organisationEvent->start_time);
        $start->setTime($startHour, $startMinute, $startSecond);
        $end = new Carbon($organisationEvent->end_date);
        [$endHour, $endMinute, $endSecond] = explode(':', $organisationEvent->end_time);
        $end->setTime($endHour, $endMinute, $endSecond);
        $urlsafeTitle = urlencode($organisationEvent->title);
        $urlsafeIntro = urlencode($organisationEvent->intro);
        $urlsafeLocation = urlencode($organisationEvent->location->toAddress()->__toString());

        $iCalendar = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//hacksw/handcal//NONSGML v1.0//EN',
            'BEGIN:VEVENT',
            'UID:' . $organisationEvent->id,
            'DTSTAMP:' . $now->format('Ymd\\THis\\Z'),
            'ORGANIZER;CN=' . $organisationEvent->organiser_name . ':MAILTO:' . $organisationEvent->organiser_email,
            'DTSTART:' . $start->format('Ymd\\THis\\Z'),
            'DTEND:' . $end->format('Ymd\\THis\\Z'),
            'SUMMARY:' . $organisationEvent->title,
            'DESCRIPTION:' . $organisationEvent->intro,
            'GEO:' . $organisationEvent->location->lat . ';' . $organisationEvent->location->lon,
            'LOCATION:' . str_ireplace(',', '\,', $organisationEvent->location->toAddress()->__toString()),
            'END:VEVENT',
            'END:VCALENDAR',
        ]);

        $this->assertEquals('https://calendar.google.com/calendar/render?action=TEMPLATE&dates=' . urlencode($start->format('Ymd\\THis\\Z') . '/' . $end->format('Ymd\\THis\\Z')) . '&details=' . $urlsafeTitle . '&location=' . $urlsafeLocation . '&text=' . $urlsafeIntro, $organisationEvent->googleCalendarlink);

        $this->assertEquals('https://outlook.office.com/calendar/0/deeplink/compose?path=%2Fcalendar%2Faction%2Fcompose&rru=addevent&startdt=' . urlencode($start->format(DateTime::ATOM)) . '&enddt=' . urlencode($end->format(DateTime::ATOM)) . '&subject=' . $urlsafeTitle . '&location=' . $urlsafeLocation . '&body=' . $urlsafeIntro, $organisationEvent->microsoftCalendarLink);

        $this->assertEquals(secure_url('/core/v1/organisation-events/' . $organisationEvent->id . '/event.ics'), $organisationEvent->appleCalendarLink);

        $response = $this->get($organisationEvent->appleCalendarLink);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertHeader('Content-Type', 'text/calendar; charset=UTF-8');

        $this->assertEquals($iCalendar, $response->content());
    }

    /**
     * Update an OrganisationEvent
     */

    /**
     * @test
     */
    public function putUpdateOrganisationEventAsGuest401(): void
    {
        $organisationEvent = OrganisationEvent::factory()->create();

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAsServiceWorker403(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);

        Passport::actingAs($user);

        $organisationEvent = OrganisationEvent::factory()->create();

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAsServiceAdmin403(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $organisationEvent = OrganisationEvent::factory()->create();

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAsOrganisationAdmin200(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $organisationEvent = OrganisationEvent::factory()->create([
            'organisation_id' => $organisation->id,
        ]);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => false,
            'fees_text' => $this->faker->sentence(),
            'fees_url' => $this->faker->url(),
            'organiser_name' => $this->faker->name(),
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail(),
            'organiser_url' => $this->faker->url(),
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence(),
            'booking_url' => $this->faker->url(),
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
            'category_taxonomies' => [],
        ];

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment($payload);

        $updateRequest = UpdateRequest::findOrFail($response->json('id'));

        $this->assertEquals($updateRequest->data, $payload);
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAsGlobalAdmin200(): void
    {
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $organisationEvent = OrganisationEvent::factory()->create();

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => false,
            'fees_text' => $this->faker->sentence(),
            'fees_url' => $this->faker->url(),
            'organiser_name' => $this->faker->name(),
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail(),
            'organiser_url' => $this->faker->url(),
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence(),
            'booking_url' => $this->faker->url(),
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
            'image_file_id' => null,
        ];

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment($payload);

        $updateRequest = UpdateRequest::findOrFail($response->json('id'));

        $this->assertEquals($updateRequest->data, $payload);

        $this->approveUpdateRequest($updateRequest->id);

        // The organisation event is updated
        $this->assertDatabaseHas((new OrganisationEvent())->getTable(), array_merge(['id' => $organisationEvent->id], $payload));
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAutoApprovedAsSuperAdmin200(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin($organisation);

        Passport::actingAs($user);

        $organisationEvent = OrganisationEvent::factory()->create();

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => false,
            'fees_text' => $this->faker->sentence(),
            'fees_url' => $this->faker->url(),
            'organiser_name' => $this->faker->name(),
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail(),
            'organiser_url' => $this->faker->url(),
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence(),
            'booking_url' => $this->faker->url(),
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
        ];

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        // The organisation event is updated
        $this->assertDatabaseHas((new OrganisationEvent())->getTable(), array_merge(['id' => $organisationEvent->id], $payload));

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::EXISTING_TYPE_ORGANISATION_EVENT)
            ->where('updateable_id', $organisationEvent->id)
            ->firstOrFail();
        $this->assertEquals($updateRequest->data, $payload);

        // Update request should already have been approved.
        $this->assertNotNull($updateRequest->approved_at);
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAsOrganisationAdminCreatesAudit200(): void
    {
        $this->fakeEvents();

        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $organisationEvent = OrganisationEvent::factory()->create([
            'organisation_id' => $organisation->id,
        ]);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => false,
            'fees_text' => $this->faker->sentence(),
            'fees_url' => $this->faker->url(),
            'organiser_name' => $this->faker->name(),
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail(),
            'organiser_url' => $this->faker->url(),
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence(),
            'booking_url' => $this->faker->url(),
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
            'organisation_id' => $organisation->id,
            'category_taxonomies' => [],
        ];

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $organisationEvent) {
            return ($event->getAction() === Audit::ACTION_UPDATE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $organisationEvent->id);
        });
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAsGlobalAdminAddImage200(): void
    {
        $user = User::factory()->create()->makeGlobalAdmin();
        $image = Storage::disk('local')->get('/test-data/image.png');

        Passport::actingAs($user);

        $imageResponse = $this->json('POST', '/core/v1/files', [
            'is_private' => false,
            'mime_type' => 'image/png',
            'alt_text' => 'image description',
            'file' => 'data:image/png;base64,' . base64_encode($image),
        ]);

        $imageResponse->assertStatus(Response::HTTP_CREATED);

        $organisationEvent = OrganisationEvent::factory()->create();

        $payload = [
            'image_file_id' => $this->getResponseContent($imageResponse, 'data.id'),
        ];

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest = UpdateRequest::findOrFail($response->json('id'));

        $this->assertEquals($updateRequest->data, $payload);

        $this->approveUpdateRequest($updateRequest->id);

        $content = $this->get("/core/v1/organisation-events/{$organisationEvent->id}/image.png")->content();
        $this->assertEquals($image, $content);
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAsGlobalAdminRemoveImage200(): void
    {
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $organisationEvent = OrganisationEvent::factory()->withImage()->create();

        $payload = [
            'image_file_id' => null,
        ];

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest = UpdateRequest::findOrFail($response->json('id'));

        $this->assertEquals($updateRequest->data, $payload);

        $this->approveUpdateRequest($updateRequest->id);

        $organisationEvent = $organisationEvent->fresh();
        $this->assertEquals(null, $organisationEvent->image_file_id);
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAsOtherOrganisationAdmin403(): void
    {
        $organisation1 = Organisation::factory()->create();
        $organisation2 = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation2);

        Passport::actingAs($user);

        $organisationEvent = OrganisationEvent::factory()->create([
            'organisation_id' => $organisation1->id,
        ]);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => false,
            'fees_text' => $this->faker->sentence(),
            'fees_url' => $this->faker->url(),
            'organiser_name' => $this->faker->name(),
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail(),
            'organiser_url' => $this->faker->url(),
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence(),
            'booking_url' => $this->faker->url(),
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
            'category_taxonomies' => [],
        ];

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAddToHomepageAsOrganisationAdmin422(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $organisationEvent = OrganisationEvent::factory()->create([
            'organisation_id' => $organisation->id,
        ]);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => false,
            'fees_text' => $this->faker->sentence(),
            'fees_url' => $this->faker->url(),
            'organiser_name' => $this->faker->name(),
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail(),
            'organiser_url' => $this->faker->url(),
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence(),
            'booking_url' => $this->faker->url(),
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => true,
            'is_virtual' => false,
            'location_id' => $location->id,
            'category_taxonomies' => [],
        ];

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventAddToHomepageAsGlobalAdmin200(): void
    {
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $organisationEvent = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event Title',
            'slug' => 'organisation-event-title',
        ]);

        $payload = [
            'title' => 'New Event Title',
        ];

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest = UpdateRequest::findOrFail($response->json('id'));
        $this->assertEquals($updateRequest->data, $payload);

        $this->approveUpdateRequest($updateRequest->id);

        // The organisation event is updated
        $this->assertDatabaseHas((new OrganisationEvent())->getTable(), [
            'title' => 'New Event Title',
            'slug' => 'organisation-event-title',
        ]);

        $payload = [
            'slug' => 'new-event-title',
        ];

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest = UpdateRequest::findOrFail($response->json('id'));
        $this->assertEquals($updateRequest->data, $payload);

        $this->approveUpdateRequest($updateRequest->id);

        // The organisation event is updated
        $this->assertDatabaseHas((new OrganisationEvent())->getTable(), [
            'title' => 'New Event Title',
            'slug' => 'new-event-title',
        ]);
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventUpdateSlugAsOrganisationAdmin200(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $organisationEvent1 = OrganisationEvent::factory()->create([
            'organisation_id' => $organisation->id,
            'slug' => 'event-slug',
        ]);

        $organisationEvent2 = OrganisationEvent::factory()->create([
            'organisation_id' => $organisation->id,
            'slug' => 'other-slug',
        ]);

        $organisationEvent3 = OrganisationEvent::factory()->create([
            'organisation_id' => $organisation->id,
            'slug' => 'yet-another-slug',
        ]);

        $payload = [
            'slug' => 'event-slug',
        ];

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent2->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest1 = UpdateRequest::find($response->json('id'));

        $this->assertEquals('event-slug', $updateRequest1->data['slug']);

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent3->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest2 = UpdateRequest::find($response->json('id'));

        $this->assertEquals('event-slug', $updateRequest2->data['slug']);

        $this->approveUpdateRequest($updateRequest1->id);

        // The organisation event is updated
        $this->assertDatabaseHas((new OrganisationEvent())->getTable(), [
            'id' => $organisationEvent2->id,
            'slug' => 'event-slug-1',
        ]);

        $this->approveUpdateRequest($updateRequest2->id);

        // The organisation event is updated
        $this->assertDatabaseHas((new OrganisationEvent())->getTable(), [
            'id' => $organisationEvent3->id,
            'slug' => 'event-slug-2',
        ]);
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventUpdateSlugAsSuperAdmin200(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();

        Passport::actingAs($user);

        $organisationEvent1 = OrganisationEvent::factory()->create([
            'organisation_id' => $organisation->id,
            'slug' => 'event-slug',
        ]);

        $organisationEvent2 = OrganisationEvent::factory()->create([
            'organisation_id' => $organisation->id,
            'slug' => 'other-slug',
        ]);

        $organisationEvent3 = OrganisationEvent::factory()->create([
            'organisation_id' => $organisation->id,
            'slug' => 'yet-another-slug',
        ]);

        $payload = [
            'slug' => 'event-slug',
        ];

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent2->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseHas('organisation_events', [
            'id' => $organisationEvent2->id,
            'slug' => 'event-slug-1',
        ]);

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent3->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseHas('organisation_events', [
            'id' => $organisationEvent3->id,
            'slug' => 'event-slug-2',
        ]);
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventUpdateTaxonomiesAsOrganisationAdmin200(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $organisationEvent = OrganisationEvent::factory()->create([
            'organisation_id' => $organisation->id,
        ]);

        $taxonomy1 = Taxonomy::factory()->create();
        $taxonomy2 = Taxonomy::factory()->create();
        $organisationEvent->syncTaxonomyRelationships(collect([$taxonomy1]));

        $this->assertDatabaseHas(table(OrganisationEventTaxonomy::class), [
            'organisation_event_id' => $organisationEvent->id,
            'taxonomy_id' => $taxonomy1->id,
        ]);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => false,
            'fees_text' => $this->faker->sentence(),
            'fees_url' => $this->faker->url(),
            'organiser_name' => $this->faker->name(),
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail(),
            'organiser_url' => $this->faker->url(),
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence(),
            'booking_url' => $this->faker->url(),
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
            'category_taxonomies' => [$taxonomy1->id, $taxonomy2->id],
        ];

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['data' => $payload]);
        $response->assertJsonFragment(['message' => __('updates.pending', ['appname' => config('app.name')])]);
        $updateRequest = UpdateRequest::findOrFail($response->json('id'));
        $this->assertEquals($updateRequest->data, $payload);

        $this->assertDatabaseHas(table(OrganisationEventTaxonomy::class), [
            'organisation_event_id' => $organisationEvent->id,
            'taxonomy_id' => $taxonomy1->id,
        ]);
        $this->assertDatabaseMissing(table(OrganisationEventTaxonomy::class), [
            'organisation_event_id' => $organisationEvent->id,
            'taxonomy_id' => $taxonomy2->id,
        ]);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas(table(OrganisationEventTaxonomy::class), [
            'organisation_event_id' => $organisationEvent->id,
            'taxonomy_id' => $taxonomy2->id,
        ]);
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventUpdateTaxonomiesAsGlobalAdmin200(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $organisationEvent = OrganisationEvent::factory()->create([
            'organisation_id' => $organisation->id,
        ]);

        $taxonomy1 = Taxonomy::factory()->create();
        $taxonomy2 = Taxonomy::factory()->create();
        $taxonomy3 = Taxonomy::factory()->create();
        $organisationEvent->syncTaxonomyRelationships(collect([$taxonomy1]));

        $this->assertDatabaseHas(table(OrganisationEventTaxonomy::class), [
            'organisation_event_id' => $organisationEvent->id,
            'taxonomy_id' => $taxonomy1->id,
        ]);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => false,
            'fees_text' => $this->faker->sentence(),
            'fees_url' => $this->faker->url(),
            'organiser_name' => $this->faker->name(),
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail(),
            'organiser_url' => $this->faker->url(),
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence(),
            'booking_url' => $this->faker->url(),
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
            'category_taxonomies' => [$taxonomy2->id, $taxonomy3->id],
        ];

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment(['data' => $payload]);
        $response->assertJsonFragment(['message' => __('updates.pending', ['appname' => config('app.name')])]);
        $updateRequest = UpdateRequest::findOrFail($response->json('id'));
        $this->assertEquals($updateRequest->data, $payload);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas(table(OrganisationEventTaxonomy::class), [
            'organisation_event_id' => $organisationEvent->id,
            'taxonomy_id' => $taxonomy2->id,
        ]);
        $this->assertDatabaseHas(table(OrganisationEventTaxonomy::class), [
            'organisation_event_id' => $organisationEvent->id,
            'taxonomy_id' => $taxonomy3->id,
        ]);
        $this->assertDatabaseHas(table(OrganisationEventTaxonomy::class), [
            'organisation_event_id' => $organisationEvent->id,
            'taxonomy_id' => $taxonomy2->parent_id,
        ]);
        $this->assertDatabaseMissing(table(OrganisationEventTaxonomy::class), [
            'organisation_event_id' => $organisationEvent->id,
            'taxonomy_id' => $taxonomy1->id,
        ]);

        $responsePayload = $payload;
        $responsePayload['category_taxonomies'] = [
            [
                'id' => $taxonomy2->parent->id,
                'parent_id' => $taxonomy2->parent->parent_id,
                'slug' => $taxonomy2->parent->slug,
                'name' => $taxonomy2->parent->name,
                'created_at' => $taxonomy2->parent->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => $taxonomy2->parent->updated_at->format(CarbonImmutable::ISO8601),
            ],
            [
                'id' => $taxonomy2->id,
                'parent_id' => $taxonomy2->parent_id,
                'slug' => $taxonomy2->slug,
                'name' => $taxonomy2->name,
                'created_at' => $taxonomy2->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => $taxonomy2->updated_at->format(CarbonImmutable::ISO8601),
            ],
            [
                'id' => $taxonomy3->id,
                'parent_id' => $taxonomy3->parent_id,
                'slug' => $taxonomy3->slug,
                'name' => $taxonomy3->name,
                'created_at' => $taxonomy3->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => $taxonomy3->updated_at->format(CarbonImmutable::ISO8601),
            ],
        ];
        $response = $this->json('GET', "/core/v1/organisation-events/{$organisationEvent->id}");
        $response->assertJsonFragment($responsePayload);
    }

    /**
     * @test
     */
    public function putUpdateOrganisationEventUpdateTaxonomiesAsSuperAdmin200(): void
    {
        $organisation = Organisation::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();

        Passport::actingAs($user);

        $organisationEvent = OrganisationEvent::factory()->create([
            'organisation_id' => $organisation->id,
        ]);

        $taxonomy1 = Taxonomy::factory()->create();
        $taxonomy2 = Taxonomy::factory()->create();
        $taxonomy3 = Taxonomy::factory()->create();
        $organisationEvent->syncTaxonomyRelationships(collect([$taxonomy1]));

        $this->assertDatabaseHas(table(OrganisationEventTaxonomy::class), [
            'organisation_event_id' => $organisationEvent->id,
            'taxonomy_id' => $taxonomy1->id,
        ]);

        $date = $this->faker->dateTimeBetween('tomorrow', '+6 weeks')->format('Y-m-d');
        $payload = [
            'title' => 'A New Organisation Event',
            'slug' => 'a-new-organisation-event',
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'intro' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'is_free' => false,
            'fees_text' => $this->faker->sentence(),
            'fees_url' => $this->faker->url(),
            'organiser_name' => $this->faker->name(),
            'organiser_phone' => random_uk_phone(),
            'organiser_email' => $this->faker->safeEmail(),
            'organiser_url' => $this->faker->url(),
            'booking_title' => $this->faker->sentence(3),
            'booking_summary' => $this->faker->sentence(),
            'booking_url' => $this->faker->url(),
            'booking_cta' => $this->faker->words(2, true),
            'homepage' => false,
            'is_virtual' => false,
            'location_id' => $location->id,
            'category_taxonomies' => [$taxonomy2->id, $taxonomy3->id],
        ];

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment(['data' => $payload]);
        $response->assertJsonFragment(['message' => __('updates.pre-approved')]);

        $this->assertDatabaseHas(table(OrganisationEventTaxonomy::class), [
            'organisation_event_id' => $organisationEvent->id,
            'taxonomy_id' => $taxonomy2->id,
        ]);
        $this->assertDatabaseHas(table(OrganisationEventTaxonomy::class), [
            'organisation_event_id' => $organisationEvent->id,
            'taxonomy_id' => $taxonomy3->id,
        ]);
        $this->assertDatabaseHas(table(OrganisationEventTaxonomy::class), [
            'organisation_event_id' => $organisationEvent->id,
            'taxonomy_id' => $taxonomy2->parent_id,
        ]);
        $this->assertDatabaseMissing(table(OrganisationEventTaxonomy::class), [
            'organisation_event_id' => $organisationEvent->id,
            'taxonomy_id' => $taxonomy1->id,
        ]);

        $responsePayload = $payload;
        $responsePayload['category_taxonomies'] = [
            [
                'id' => $taxonomy2->parent->id,
                'parent_id' => $taxonomy2->parent->parent_id,
                'slug' => $taxonomy2->parent->slug,
                'name' => $taxonomy2->parent->name,
                'created_at' => $taxonomy2->parent->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => $taxonomy2->parent->updated_at->format(CarbonImmutable::ISO8601),
            ],
            [
                'id' => $taxonomy2->id,
                'parent_id' => $taxonomy2->parent_id,
                'slug' => $taxonomy2->slug,
                'name' => $taxonomy2->name,
                'created_at' => $taxonomy2->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => $taxonomy2->updated_at->format(CarbonImmutable::ISO8601),
            ],
            [
                'id' => $taxonomy3->id,
                'parent_id' => $taxonomy3->parent_id,
                'slug' => $taxonomy3->slug,
                'name' => $taxonomy3->name,
                'created_at' => $taxonomy3->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => $taxonomy3->updated_at->format(CarbonImmutable::ISO8601),
            ],
        ];
        $response = $this->json('GET', "/core/v1/organisation-events/{$organisationEvent->id}");
        $response->assertJsonFragment($responsePayload);
    }

    /**
     * Delete an OrganisationEvent
     */

    /**
     * @test
     */
    public function deleteRemoveOrganisationEventAsGuest401(): void
    {
        $organisationEvent = OrganisationEvent::factory()->create();

        $response = $this->json('DELETE', "/core/v1/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function deleteRemoveOrganisationEventAsServiceWorker403(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);

        Passport::actingAs($user);

        $organisationEvent = OrganisationEvent::factory()->create();

        $response = $this->json('DELETE', "/core/v1/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function deleteRemoveOrganisationEventAsServiceAdmin403(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $organisationEvent = OrganisationEvent::factory()->create();

        $response = $this->json('DELETE', "/core/v1/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function deleteRemoveOrganisationEventAsOrganisationAdmin403(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        Passport::actingAs($user);

        $organisationEvent = OrganisationEvent::factory()->create([
            'organisation_id' => $organisation->id,
        ]);

        $response = $this->json('DELETE', "/core/v1/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $this->assertDatabaseHas((new OrganisationEvent())->getTable(), ['id' => $organisationEvent->id]);
    }

    /**
     * @test
     */
    public function deleteRemoveOrganisationEventAsGlobalAdmin403(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin($organisation);

        Passport::actingAs($user);

        $organisationEvent = OrganisationEvent::factory()->create();

        $response = $this->json('DELETE', "/core/v1/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $this->assertDatabaseHas((new OrganisationEvent())->getTable(), ['id' => $organisationEvent->id]);
    }

    /**
     * @test
     */
    public function deleteRemoveOrganisationEventAsSuperAdmin200(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin($organisation);

        Passport::actingAs($user);

        $organisationEvent = OrganisationEvent::factory()->create();

        $response = $this->json('DELETE', "/core/v1/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing((new OrganisationEvent())->getTable(), ['id' => $organisationEvent->id]);
    }

    /**
     * @test
     */
    public function deleteRemoveOrganisationEventAsSuperAdminCreatesAudit200(): void
    {
        $this->fakeEvents();

        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();

        Passport::actingAs($user);

        $organisationEvent = OrganisationEvent::factory()->create([
            'organisation_id' => $organisation->id,
        ]);

        $response = $this->json('DELETE', "/core/v1/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_OK);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $organisationEvent) {
            return ($event->getAction() === Audit::ACTION_DELETE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $organisationEvent->id);
        });
    }

    /**
     * @test
     */
    public function deleteOrganisationEventWithUpdateRequestsAsSuperAdmin200(): void
    {
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $organisationEvent = OrganisationEvent::factory()->create([
            'title' => 'Organisation Event Title',
            'slug' => 'organisation-event-title',
        ]);

        $payload = [
            'title' => 'New Event Title',
        ];

        $response = $this->json('PUT', "/core/v1/organisation-events/{$organisationEvent->id}", $payload);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest = UpdateRequest::findOrFail($response->json('id'));
        $this->assertEquals($updateRequest->data, $payload);

        Passport::actingAs(User::factory()->create()->makeSuperAdmin());

        $response = $this->json('DELETE', "/core/v1/organisation-events/{$organisationEvent->id}");

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseMissing((new OrganisationEvent())->getTable(), ['id' => $organisationEvent->id]);
        $this->assertDatabaseMissing('update_requests', ['id' => $updateRequest->id, 'deleted_at' => null]);
    }
}
