<?php

namespace Tests\Feature\Services;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_audit_log_is_created_when_item_is_created(): void
    {
        $this->actingAs($this->user);

        $category = Category::factory()->create();

        $payload = [
            'name'                => 'Test Item',
            'sku'                 => 'TEST-SKU-001',
            'quantity'            => 50,
            'price'               => 99.99,
            'min_stock_threshold' => 10,
            'category_id'         => $category->id,
        ];

        $response = $this->postJson('/api/v1/items', $payload);

        $response->assertStatus(201);

        $auditLog = AuditLog::where('model_type', 'Item')
            ->where('action', 'create')
            ->latest()
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals($this->user->id, $auditLog->user_id);
        $this->assertNull($auditLog->changes);
    }

    public function test_audit_log_records_changes_on_update(): void
    {
        $this->actingAs($this->user);

        $item = Item::factory()->create(['quantity' => 50]);

        $payload = [
            'quantity' => 25,
            'price'    => 199.99,
        ];

        $response = $this->putJson("/api/v1/items/{$item->id}", $payload);

        $response->assertStatus(200);

        $auditLog = AuditLog::where('model_type', 'Item')
            ->where('action', 'update')
            ->latest()
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals($this->user->id, $auditLog->user_id);
        $this->assertNotNull($auditLog->changes);

        $changes = $auditLog->changes;
        $this->assertArrayHasKey('quantity', $changes);
        $this->assertEquals(50, $changes['quantity']['old']);
        $this->assertEquals(25, $changes['quantity']['new']);
    }

    public function test_audit_log_is_created_when_item_is_deleted(): void
    {
        $this->actingAs($this->user);

        $item = Item::factory()->create();
        $itemId = $item->id;

        $response = $this->deleteJson("/api/v1/items/{$item->id}");

        $response->assertStatus(204);

        $auditLog = AuditLog::where('model_type', 'Item')
            ->where('action', 'delete')
            ->where('model_id', $itemId)
            ->latest()
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals($this->user->id, $auditLog->user_id);
    }

    public function test_audit_log_is_created_when_category_is_created(): void
    {
        $this->actingAs($this->user);

        $payload = [
            'name'        => 'New Category',
            'description' => 'Category description',
        ];

        $response = $this->postJson('/api/v1/categories', $payload);

        $response->assertStatus(201);

        $auditLog = AuditLog::where('model_type', 'Category')
            ->where('action', 'create')
            ->latest()
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals($this->user->id, $auditLog->user_id);
    }

    public function test_audit_log_is_not_created_without_authenticated_user(): void
    {
        $category = Category::factory()->create();

        $payload = [
            'name'                => 'Test Item',
            'sku'                 => 'TEST-SKU-001',
            'quantity'            => 50,
            'price'               => 99.99,
            'min_stock_threshold' => 10,
            'category_id'         => $category->id,
        ];

        $response = $this->postJson('/api/v1/items', $payload);

        $response->assertStatus(401);

        $auditLog = AuditLog::where('model_type', 'Item')
            ->where('action', 'create')
            ->first();

        $this->assertNull($auditLog);
    }

    public function test_multiple_field_changes_are_recorded(): void
    {
        $this->actingAs($this->user);

        $category = Category::factory()->create();
        $item = Item::factory()->create([
            'name'                => 'Original Name',
            'quantity'            => 100,
            'price'               => 50.00,
            'min_stock_threshold' => 10,
        ]);

        $payload = [
            'name'                => 'Updated Name',
            'quantity'            => 50,
            'price'               => 75.00,
            'min_stock_threshold' => 20,
        ];

        $response = $this->putJson("/api/v1/items/{$item->id}", $payload);

        $response->assertStatus(200);

        $auditLog = AuditLog::where('model_type', 'Item')
            ->where('action', 'update')
            ->where('model_id', $item->id)
            ->latest()
            ->first();

        $changes = $auditLog->changes;
        $this->assertCount(4, $changes);
        $this->assertArrayHasKey('name', $changes);
        $this->assertArrayHasKey('quantity', $changes);
        $this->assertArrayHasKey('price', $changes);
        $this->assertArrayHasKey('min_stock_threshold', $changes);
    }
}
