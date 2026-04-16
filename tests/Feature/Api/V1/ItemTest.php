<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_list_items(): void
    {
        Item::factory(5)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/items');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'sku', 'quantity', 'status', 'category_id'],
                ],
            ]);
    }

    public function test_can_create_item(): void
    {
        $category = Category::factory()->create();

        $payload = [
            'name'                => 'Dell XPS 13',
            'sku'                 => 'LAPTOP-001',
            'quantity'            => 50,
            'price'               => 999.99,
            'min_stock_threshold' => 10,
            'category_id'         => $category->id,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/v1/items', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Dell XPS 13')
            ->assertJsonPath('data.sku', 'LAPTOP-001')
            ->assertJsonPath('data.quantity', 50)
            ->assertJsonPath('data.status', 'in stock');

        $this->assertDatabaseHas('items', ['sku' => 'LAPTOP-001']);
    }

    public function test_low_stock_status_is_set_automatically(): void
    {
        $category = Category::factory()->create();

        $payload = [
            'name'                => 'Keyboard',
            'sku'                 => 'KEY-001',
            'quantity'            => 3,
            'price'               => 49.99,
            'min_stock_threshold' => 10,
            'category_id'         => $category->id,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/v1/items', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'low stock');

        $this->assertDatabaseHas('items', ['status' => 'low stock']);
    }

    public function test_updating_quantity_updates_status(): void
    {
        $item = Item::factory()->highStock()->create(['min_stock_threshold' => 20]);

        $payload = ['quantity' => 5];

        $response = $this->actingAs($this->user)->putJson("/api/v1/items/{$item->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'low stock');

        $this->assertDatabaseHas('items', [
            'id'     => $item->id,
            'status' => 'low stock',
        ]);
    }

    public function test_can_show_item(): void
    {
        $item = Item::factory()->create();

        $response = $this->actingAs($this->user)->getJson("/api/v1/items/{$item->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $item->id)
            ->assertJsonPath('data.name', $item->name);
    }

    public function test_can_update_item(): void
    {
        $item = Item::factory()->create();

        $payload = [
            'name'  => 'Updated Name',
            'price' => 199.99,
        ];

        $response = $this->actingAs($this->user)->putJson("/api/v1/items/{$item->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.price', '199.99');
    }

    public function test_can_delete_item(): void
    {
        $item = Item::factory()->create();

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/items/{$item->id}");

        $response->assertStatus(204);
        $this->assertModelMissing($item);
    }

    public function test_validation_fails_with_duplicate_sku(): void
    {
        $category = Category::factory()->create();
        Item::factory()->create(['sku' => 'UNIQUE-SKU']);

        $payload = [
            'name'                => 'Test Item',
            'sku'                 => 'UNIQUE-SKU',
            'quantity'            => 10,
            'price'               => 99.99,
            'min_stock_threshold' => 5,
            'category_id'         => $category->id,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/v1/items', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('sku');
    }

    public function test_validation_fails_with_invalid_category_id(): void
    {
        $payload = [
            'name'                => 'Test Item',
            'sku'                 => 'TEST-SKU',
            'quantity'            => 10,
            'price'               => 99.99,
            'min_stock_threshold' => 5,
            'category_id'         => 9999,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/v1/items', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('category_id');
    }

    public function test_validation_fails_with_negative_quantity(): void
    {
        $category = Category::factory()->create();

        $payload = [
            'name'                => 'Test Item',
            'sku'                 => 'TEST-SKU',
            'quantity'            => -5,
            'price'               => 99.99,
            'min_stock_threshold' => 5,
            'category_id'         => $category->id,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/v1/items', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('quantity');
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/items');

        $response->assertStatus(401);
    }
}
