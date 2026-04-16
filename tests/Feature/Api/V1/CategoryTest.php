<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_list_categories(): void
    {
        Category::factory(5)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'description', 'created_at', 'updated_at'],
                ],
            ]);
    }

    public function test_can_create_category(): void
    {
        $payload = [
            'name'        => 'Electronics',
            'description' => 'Electronic devices and accessories',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/v1/categories', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Electronics')
            ->assertJsonPath('data.description', 'Electronic devices and accessories');

        $this->assertDatabaseHas('categories', ['name' => 'Electronics']);
    }

    public function test_cannot_create_category_with_duplicate_name(): void
    {
        Category::factory()->create(['name' => 'Electronics']);

        $payload = [
            'name'        => 'Electronics',
            'description' => 'Another electronics category',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/v1/categories', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_can_show_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->user)->getJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.name', $category->name);
    }

    public function test_can_update_category(): void
    {
        $category = Category::factory()->create(['name' => 'Old Name']);

        $payload = [
            'name'        => 'Updated Name',
            'description' => 'New description',
        ];

        $response = $this->actingAs($this->user)->putJson("/api/v1/categories/{$category->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.description', 'New description');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Updated Name']);
    }

    public function test_cannot_update_category_with_duplicate_name(): void
    {
        $category1 = Category::factory()->create(['name' => 'Electronics']);
        $category2 = Category::factory()->create(['name' => 'Books']);

        $payload = ['name' => 'Electronics'];

        $response = $this->actingAs($this->user)->putJson("/api/v1/categories/{$category2->id}", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_can_update_category_with_same_name(): void
    {
        $category = Category::factory()->create(['name' => 'Electronics']);

        $payload = ['name' => 'Electronics', 'description' => 'Updated desc'];

        $response = $this->actingAs($this->user)->putJson("/api/v1/categories/{$category->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Electronics');
    }

    public function test_can_delete_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(204);
        $this->assertModelMissing($category);
    }

    public function test_validation_fails_with_missing_name(): void
    {
        $payload = ['description' => 'Missing name'];

        $response = $this->actingAs($this->user)->postJson('/api/v1/categories', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_description_is_optional(): void
    {
        $payload = ['name' => 'Minimal Category'];

        $response = $this->actingAs($this->user)->postJson('/api/v1/categories', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Minimal Category');
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(401);
    }
}
