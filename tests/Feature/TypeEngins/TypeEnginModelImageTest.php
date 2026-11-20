<?php

namespace Tests\Feature\TypeEngins;

use App\Models\TypeEngin;
use App\Models\TypeEnginModel;
use Tests\TestCase;

class TypeEnginModelImageTest extends TestCase
{
    private const IMAGE = 'https://cdn.example.com/engins/benne-10-roues.png';

    /** @test */
    public function a_type_engin_model_can_be_created_with_an_image()
    {
        $this->postJson('/api/v1/type-engins-models/create', [
            'type_engin_slug' => 'camion-benne',
            'title' => 'Benne',
            'subtitle' => '10 roues',
            'image' => self::IMAGE,
        ])
            ->assertOk()
            ->assertJsonPath('data.image', self::IMAGE);

        $this->assertSame(self::IMAGE, TypeEnginModel::where('slug', 'benne-10-roues')->value('image'));
    }

    /** @test */
    public function the_image_is_exposed_in_the_models_of_the_type_engins_list()
    {
        TypeEngin::create(['name' => 'Camion benne', 'slug' => 'camion-benne']);
        TypeEnginModel::create(['type_engin_slug' => 'camion-benne', 'title' => 'Benne', 'slug' => 'benne', 'image' => self::IMAGE]);

        $this->getJson('/api/v1/type-engins/list')
            ->assertOk()
            ->assertJsonPath('data.0.models.0.image', self::IMAGE);
    }

    /** @test */
    public function the_image_is_optional()
    {
        $this->postJson('/api/v1/type-engins-models/create', ['title' => 'Plateau'])
            ->assertOk()
            ->assertJsonPath('data.image', null);
    }
}
