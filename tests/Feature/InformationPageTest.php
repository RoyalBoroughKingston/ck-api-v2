<?php

namespace Tests\Feature;

use App\Models\InformationPage;
use App\Models\User;
use Illuminate\Http\Response;
use Laravel\Passport\Passport;
use Tests\TestCase;

class InformationPageTest extends TestCase
{
    /**
     * @test
     */
    public function listEnabledInformationPagesAsGuest200()
    {
        $parentPage = factory(InformationPage::class)->states('withImage')->create();
        $informationPage = factory(InformationPage::class)->states('withImage')->create([
            'parent_id' => $parentPage->id,
        ]);
        $childPages = factory(InformationPage::class, 3)->states('withImage')->create([
            'parent_id' => $informationPage->id,
        ]);
        $response = $this->json('GET', '/core/v1/information-pages/index');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'title',
                    'content',
                    'order',
                    'enabled',
                    'image' => [
                        'id',
                        'mime_type',
                        'created_at',
                        'updated_at',
                    ],
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function listMixedStateInformationPagesAsGuest200()
    {
        $parentPage = factory(InformationPage::class)->states('withImage')->create();
        $informationPage = factory(InformationPage::class)->states('withImage')->create([
            'parent_id' => $parentPage->id,
            'enabled' => InformationPage::DISABLED,
        ]);
        $childPages = factory(InformationPage::class, 3)->states('withImage')->create([
            'parent_id' => $informationPage->id,
        ]);
        $response = $this->json('GET', '/core/v1/information-pages/index');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'id' => $parentPage->id,
        ]);
    }

    /**
     * @test
     */
    public function listMixedStateInformationPagesAsAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage = factory(InformationPage::class)->states('withImage')->create();
        $informationPage = factory(InformationPage::class)->states('withImage')->create([
            'parent_id' => $parentPage->id,
            'enabled' => InformationPage::DISABLED,
        ]);
        $childPages = factory(InformationPage::class, 3)->states('withImage')->create([
            'parent_id' => $informationPage->id,
        ]);
        $response = $this->json('GET', '/core/v1/information-pages/index');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(5, 'data');
    }

    /**
     * @test
     */
    public function getEnabledInformationPageAsGuest200()
    {
        $parentPage = factory(InformationPage::class)->states('withImage')->create();
        $informationPage = factory(InformationPage::class)->states('withImage')->create([
            'parent_id' => $parentPage->id,
        ]);
        $childPages = factory(InformationPage::class, 3)->states('withImage')->create([
            'parent_id' => $informationPage->id,
        ]);
        $response = $this->json('GET', '/core/v1/information-pages/' . $informationPage->id);
        dump($response->json());
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonResource([
            'id',
            'title',
            'content',
            'order',
            'enabled',
            'image' => [
                'id',
                'mime_type',
                'created_at',
                'updated_at',
            ],
            'parent' => [
                'id',
                'title',
                'image',
                'content',
                'order',
                'enabled',
                'created_at',
                'updated_at',
            ],
            'children' => [
                '*' => [
                    'id',
                    'title',
                    'image',
                    'content',
                    'order',
                    'enabled',
                    'created_at',
                    'updated_at',
                ],
            ],
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * @test
     */
    public function getDisabledInformationPageAsGuest403()
    {
        $parentPage = factory(InformationPage::class)->states('withImage')->create();
        $informationPage = factory(InformationPage::class)->states('withImage')->create([
            'parent_id' => $parentPage->id,
            'enabled' => InformationPage::DISABLED,
        ]);
        $childPages = factory(InformationPage::class, 3)->states('withImage')->create([
            'parent_id' => $informationPage->id,
        ]);
        $response = $this->json('GET', '/core/v1/information-pages/' . $informationPage->id);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function getDisabledInformationPageAsAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = factory(User::class)->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $parentPage = factory(InformationPage::class)->states('withImage')->create();
        $informationPage = factory(InformationPage::class)->states('withImage')->create([
            'parent_id' => $parentPage->id,
            'enabled' => InformationPage::DISABLED,
        ]);
        $childPages = factory(InformationPage::class, 3)->states('withImage')->create([
            'parent_id' => $informationPage->id,
        ]);
        $response = $this->json('GET', '/core/v1/information-pages/' . $informationPage->id);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonResource([
            'id',
            'title',
            'content',
            'order',
            'enabled',
            'image' => [
                'id',
                'mime_type',
                'created_at',
                'updated_at',
            ],
            'parent' => [
                'id',
                'title',
                'image',
                'content',
                'order',
                'enabled',
                'created_at',
                'updated_at',
            ],
            'children' => [
                '*' => [
                    'id',
                    'title',
                    'image',
                    'content',
                    'order',
                    'enabled',
                    'created_at',
                    'updated_at',
                ],
            ],
            'created_at',
            'updated_at',
        ]);
    }
}
