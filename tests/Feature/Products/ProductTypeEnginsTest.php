<?php

namespace Tests\Feature\Products;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductEnginRelation;
use App\Models\TypeEngin;
use Tests\TestCase;

class ProductTypeEnginsTest extends TestCase
{
    private Customer $customer;

    private Product $gravier;

    private TypeEngin $benne;

    private TypeEngin $plateau;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create(['name' => 'Client', 'phone' => '2250700000009', 'is_active' => true]);
        $this->gravier = Product::create(['name' => 'Gravier', 'slug' => 'gravier']);
        $this->benne = TypeEngin::create(['name' => 'Camion benne', 'slug' => 'camion-benne']);
        $this->plateau = TypeEngin::create(['name' => 'Camion plateau', 'slug' => 'camion-plateau']);
    }

    private function syncTypeEngins(int $productId, array $body)
    {
        return $this->actingAs($this->customer, 'api-customers')
            ->putJson("/api/v1/products/{$productId}/type-engins", $body);
    }

    /** @test */
    public function it_associates_type_engins_with_a_product()
    {
        $this->syncTypeEngins($this->gravier->id, ['type_engin_ids' => [$this->benne->id, $this->plateau->id]])
            ->assertOk()
            ->assertJsonPath('data.id', $this->gravier->id)
            ->assertJsonCount(2, 'data.type_engins')
            ->assertJsonMissingPath('data.type_engins.0.pivot');

        $this->assertEqualsCanonicalizing(
            [$this->benne->id, $this->plateau->id],
            $this->gravier->typeEngins()->pluck('type_engins.id')->all()
        );
    }

    /** @test */
    public function syncing_replaces_previous_associations_and_an_empty_list_clears_them()
    {
        $this->syncTypeEngins($this->gravier->id, ['type_engin_ids' => [$this->benne->id, $this->plateau->id]])->assertOk();

        $this->syncTypeEngins($this->gravier->id, ['type_engin_ids' => [$this->plateau->id]])
            ->assertOk()
            ->assertJsonCount(1, 'data.type_engins')
            ->assertJsonPath('data.type_engins.0.id', $this->plateau->id);

        $this->syncTypeEngins($this->gravier->id, ['type_engin_ids' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data.type_engins');
    }

    /** @test */
    public function soft_deleted_relations_are_ignored()
    {
        ProductEnginRelation::create(['product_id' => $this->gravier->id, 'type_engin_id' => $this->benne->id])->delete();

        $this->assertCount(0, $this->gravier->typeEngins()->get());
    }

    /** @test */
    public function it_rejects_unknown_or_missing_type_engins()
    {
        $this->syncTypeEngins($this->gravier->id, ['type_engin_ids' => [999999]])->assertStatus(422);
        $this->syncTypeEngins($this->gravier->id, [])->assertStatus(422);

        $this->assertCount(0, $this->gravier->typeEngins()->get());
    }

    /** @test */
    public function it_returns_404_for_an_unknown_product()
    {
        $this->syncTypeEngins(999999, ['type_engin_ids' => [$this->benne->id]])->assertNotFound();
    }

    /** @test */
    public function it_requires_authentication()
    {
        $this->putJson("/api/v1/products/{$this->gravier->id}/type-engins", ['type_engin_ids' => [$this->benne->id]])
            ->assertUnauthorized();
    }

    /** @test */
    public function products_list_exposes_their_type_engins()
    {
        $this->gravier->typeEngins()->sync([$this->benne->id]);

        $this->getJson('/api/v1/products/list')
            ->assertOk()
            ->assertJsonPath('data.0.type_engins.0.slug', 'camion-benne');
    }

    /** @test */
    public function type_engins_list_can_be_filtered_by_product()
    {
        $sable = Product::create(['name' => 'Sable', 'slug' => 'sable']);
        $this->gravier->typeEngins()->sync([$this->benne->id]);
        $sable->typeEngins()->sync([$this->plateau->id]);

        $this->getJson("/api/v1/type-engins/list?product_id={$this->gravier->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->benne->id);

        $this->getJson('/api/v1/type-engins/list')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
