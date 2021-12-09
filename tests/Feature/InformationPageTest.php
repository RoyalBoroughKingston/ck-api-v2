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
        $informationPage = factory(InformationPage::class)->states('withParent', 'withChildren')->create();

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
                    'image',
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
        $informationPage = factory(InformationPage::class)
            ->states('withImage', 'withParent', 'disabled')
            ->create();

        $response = $this->json('GET', '/core/v1/information-pages/index');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'id' => $informationPage->parent->id,
        ]);
        $response->assertJsonMissing([
            'id' => $informationPage->id,
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

        $informationPage = factory(InformationPage::class)
            ->states('withImage', 'withParent', 'disabled')
            ->create();

        $response = $this->json('GET', '/core/v1/information-pages/index');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment([
            'id' => $informationPage->parent->id,
        ]);
        $response->assertJsonFragment([
            'id' => $informationPage->id,
        ]);
    }

    /**
     * @test
     */
    public function getEnabledInformationPageAsGuest200()
    {
        $informationPage = factory(InformationPage::class)->states('withImage', 'withParent', 'withChildren')->create();

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

    /**
     * @test
     */
    public function getDisabledInformationPageAsGuest403()
    {
        $informationPage = factory(InformationPage::class)
            ->states('withImage', 'withParent', 'withChildren', 'disabled')
            ->create();

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

        $informationPage = factory(InformationPage::class)
            ->states('withImage', 'withParent', 'withChildren', 'disabled')
            ->create();

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
