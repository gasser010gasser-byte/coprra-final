<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WishlistFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_routes_are_registered(): void
    {
        $this->assertTrue(route()->has('api.wishlist.index'));
        $this->assertTrue(route()->has('api.wishlist.store'));
        $this->assertTrue(route()->has('api.wishlist.store-with-product'));
        $this->assertTrue(route()->has('api.wishlist.destroy'));
    }

    public function test_user_can_add_product_to_wishlist(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson(route('api.wishlist.store-with-product', $product->id));

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'count' => 1,
            ]);

        $this->assertDatabaseHas('wishlists', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'deleted_at' => null,
        ]);
    }

    public function test_user_can_remove_product_from_wishlist(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $user->wishlist()->attach($product->id);

        Sanctum::actingAs($user);

        $response = $this->deleteJson(route('api.wishlist.destroy', $product->id));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 0,
            ]);

        $this->assertDatabaseMissing('wishlists', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_user_can_view_their_wishlist_page(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $user->wishlist()->attach($product->id);

        $response = $this->actingAs($user)->get('/account/wishlist');

        $response->assertOk()
            ->assertSee($product->name);
    }

    public function test_guest_cannot_add_to_wishlist_and_is_prompted_to_log_in(): void
    {
        $product = Product::factory()->create();

        $response = $this->postJson(route('api.wishlist.store-with-product', $product->id));

        $response->assertStatus(401);
    }
}
