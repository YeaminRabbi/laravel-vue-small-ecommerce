<?php

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopProduct;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(LazilyRefreshDatabase::class);
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});
function commerceUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}
function listingPayload(Product $product): array
{
    return ['product_id' => $product->id, 'name' => 'Shop-specific mug', 'description' => 'Custom details', 'price' => '99.50', 'thumbnail' => 'https://example.com/mug.jpg', 'is_active' => true];
}

it('issues a token, returns permissions and revokes the token on logout', function (): void {
    $user = commerceUser('admin');
    $login = $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password'])
        ->assertOk()->assertJsonPath('user.roles.0', 'admin');
    $token = $login->json('token');
    $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id);
    $this->withToken($token)->postJson('/api/v1/logout')->assertNoContent();
    expect($user->tokens()->count())->toBe(0);
    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/dashboard')->assertUnauthorized();
});
it('rejects invalid login and unauthenticated access', function (): void {
    $this->postJson('/api/v1/login', ['email' => 'unknown@example.com', 'password' => 'bad'])->assertUnprocessable();
    $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    $this->getJson('/api/v1/products')->assertUnauthorized();
});
it('throttles repeated login attempts', function (): void {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/login', ['email' => 'unknown@example.com', 'password' => 'bad']);
    }
    $this->postJson('/api/v1/login', ['email' => 'unknown@example.com', 'password' => 'bad'])->assertTooManyRequests();
});
it('lets admins create owners with fixed roles and assign shops', function (): void {
    Sanctum::actingAs(commerceUser('admin'));
    $owner = $this->postJson('/api/v1/owners', ['name' => 'Owner', 'email' => 'new@example.com', 'password' => 'password123', 'roles' => ['admin']])->assertCreated()->assertJsonPath('roles.0', 'shop_owner')->json('id');
    $shop = $this->postJson('/api/v1/shops', ['name' => 'New shop', 'slug' => 'new-shop', 'owner_id' => $owner, 'is_active' => true])->assertCreated()->json('id');
    $this->putJson('/api/v1/shops/'.$shop, ['name' => 'Updated shop', 'slug' => 'new-shop', 'owner_id' => $owner, 'is_active' => false])->assertOk();
    $this->deleteJson('/api/v1/owners/'.$owner)->assertUnprocessable();
    $this->deleteJson('/api/v1/shops/'.$shop)->assertNoContent();
    $this->deleteJson('/api/v1/owners/'.$owner)->assertNoContent();
});
it('rejects assigning a shop to a non-owner', function (): void {
    $admin = commerceUser('admin');
    Sanctum::actingAs($admin);
    $this->postJson('/api/v1/shops', ['name' => 'Bad shop', 'slug' => 'bad-shop', 'owner_id' => $admin->id, 'is_active' => true])->assertUnprocessable()->assertJsonValidationErrors('owner_id');
});
it('restricts owners to their shops and hides admin endpoints', function (): void {
    $owner = commerceUser('shop_owner');
    $own = Shop::factory()->create(['owner_id' => $owner->id]);
    $other = Shop::factory()->create();
    Sanctum::actingAs($owner);
    $this->getJson('/api/v1/shops')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
    $this->getJson('/api/v1/shops/'.$other->id.'/listings')->assertForbidden();
    $this->getJson('/api/v1/owners')->assertForbidden();
    $this->deleteJson('/api/v1/shops/'.$other->id)->assertForbidden();
    $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('stats.shops', 1)->assertJsonPath('stats.owners', null);
});
it('shares owner products and keeps shop overrides independent', function (): void {
    $owner = commerceUser('shop_owner');
    $shop = Shop::factory()->create(['owner_id' => $owner->id]);
    $otherShop = Shop::factory()->create();
    Sanctum::actingAs($owner);
    $product = $this->postJson('/api/v1/products', ['name' => 'Shared mug', 'sku' => 'MUG-1', 'price' => '50.00'])->assertCreated()->json('id');
    $product = Product::findOrFail($product);
    $listing = $this->postJson('/api/v1/shops/'.$shop->id.'/listings', listingPayload($product))->assertCreated()->json('id');
    $this->postJson('/api/v1/shops/'.$shop->id.'/listings', listingPayload($product))->assertUnprocessable();
    $this->postJson('/api/v1/shops/'.$otherShop->id.'/listings', listingPayload($product))->assertForbidden();
    $data = listingPayload($product);
    unset($data['product_id']);
    $data['price'] = '125.00';
    $this->putJson('/api/v1/shops/'.$shop->id.'/listings/'.$listing, $data)->assertOk()->assertJsonPath('price', '125.00');
    expect($product->fresh()->price)->toBe('50.00');
    Sanctum::actingAs(commerceUser('shop_owner'));
    $this->getJson('/api/v1/products')->assertOk()->assertJsonPath('data.0.id', $product->id);
    $this->putJson('/api/v1/products/'.$product->id, ['name' => 'Changed', 'sku' => 'MUG-1', 'price' => 1])->assertForbidden();
    $this->deleteJson('/api/v1/products/'.$product->id)->assertForbidden();
});
it('prevents cross-shop listing updates even for an owned shop', function (): void {
    $owner = commerceUser('shop_owner');
    $shop = Shop::factory()->create(['owner_id' => $owner->id]);
    $listing = ShopProduct::factory()->create();
    Sanctum::actingAs($owner);
    $data = listingPayload($listing->product);
    unset($data['product_id']);
    $this->putJson('/api/v1/shops/'.$shop->id.'/listings/'.$listing->id, $data)->assertNotFound();
    $this->deleteJson('/api/v1/shops/'.$shop->id.'/listings/'.$listing->id)->assertNotFound();
});
it('only exposes active assigned products with shop-specific data', function (): void {
    Product::factory()->create(['name' => 'Unassigned']);
    $visible = ShopProduct::factory()->create(['name' => 'Custom title', 'price' => '123.45']);
    ShopProduct::factory()->create(['is_active' => false]);
    ShopProduct::factory()->for(Shop::factory()->state(['is_active' => false]))->create();
    $this->getJson('/api/v1/storefront')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $visible->id)->assertJsonPath('data.0.name', 'Custom title')->assertJsonPath('data.0.price', '123.45');
});
it('validates prices and protects assigned catalog products from deletion', function (): void {
    Sanctum::actingAs(commerceUser('admin'));
    $this->postJson('/api/v1/products', ['name' => 'Invalid', 'sku' => 'BAD', 'price' => -1, 'thumbnail' => 'javascript:bad'])->assertUnprocessable()->assertJsonValidationErrors(['price', 'thumbnail']);
    $listing = ShopProduct::factory()->create();
    $this->deleteJson('/api/v1/products/'.$listing->product_id)->assertUnprocessable();
    $this->deleteJson('/api/v1/shops/'.$listing->shop_id.'/listings/'.$listing->id)->assertNoContent();
    $this->deleteJson('/api/v1/products/'.$listing->product_id)->assertNoContent();
});
