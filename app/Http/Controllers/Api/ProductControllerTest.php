<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function testIndex()
    {
        $products = Product::factory()->count(3)->create();

        $response = $this->getJson('/api/products');

        $response->assertOk()
            ->assertJsonCount(3)
            ->assertJsonStructure([
                '*' => ['id', 'name', 'description', 'price', 'created_at', 'updated_at']
            ]);
    }

    public function testStore()
    {
        $data = [
            'name' => $this->faker->name,
            'description' => $this->faker->sentence,
            'price' => $this->faker->randomFloat(2, 0, 1000),
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertCreated()
            ->assertJson($data);

        $this->assertDatabaseHas('products', $data);
    }

    public function testShow()
    {
        $product = Product::factory()->create();

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertOk()
            ->assertJson($product->toArray());
    }

    public function testUpdate()
    {
        $product = Product::factory()->create();

        $data = [
            'name' => $this->faker->name,
            'description' => $this->faker->sentence,
            'price' => $this->faker->randomFloat(2, 0, 1000),
        ];

        $response = $this->putJson("/api/products/{$product->id}", $data);

        $response->assertOk()
            ->assertJson($data);

        $this->assertDatabaseHas('products', $data);
    }

    public function testDestroy()
    {
        $product = Product::factory()->create();

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('products', $product->toArray());
    }
}
