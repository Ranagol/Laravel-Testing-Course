<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Jobs\ProductPublishJob;
use App\Mail\NewProductCreated;
use App\Services\ProductService;
use App\Services\YouTubeService;
use App\Jobs\NewProductNotifyJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Brick\Math\Exception\NumberFormatException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Notifications\NewProductCreatedNotification;

class ProductsTest extends TestCase
{
    /**
     * this will refresh the database, refresh the migrations every time the tests are run. Which
     * is totally cool, when working with test db. But. There is a horrific danger of fully deleting
     * data on production!!!! This is one of the reason why we never do tests on production db!
     */
    use RefreshDatabase;

    public function test_load_products_page()
    {
        //We create a fake user with factory, for logging in.
        $user = User::factory()->create();
        /**
         * http://127.0.0.1:8000/products
         */
        $response = $this->actingAs($user)->get('/products');//sending a get request...
        $response->assertStatus(200);

        /**
         * Currently there are no product objects in db. So, there is nothing to display. In this
         * case, the page is set up to display this text: 'No products found'. This is what we check
         * here.
         */
        $response->assertSee('No products found');
    }

    public function test_homepage_contains_empty_table()
    {
        //We create a fake user with factory, for logging in.
        $user = User::factory()->create();

        /**
         * The route is protected with auth middleware. User must be logged in.
         */
        $response = $this->actingAs($user)->get('/products');

        $response->assertOk();
        $response->assertSee(__('No products found'));
    }

    public function test_homepage_contains_non_empty_table()
    {
        //We create a fake user with factory, for logging in.
        $user = User::factory()->create();

        //ARRANGE: set up data
        $product = Product::create([
            'name' => 'Product 1',
            'price' => 123
        ]);

        //ACT: simulate/trigger the thing that you want to test
        $response = $this->actingAs($user)->get('/products');

        //ASSERT
        $response->assertOk();
        $response->assertDontSee(__('No products found'));//No products found should not appear on page
        $response->assertSee('Product 1');//not specific enough, could produce false positive results
        $response->assertViewHas(

            /**
             * in the controller, the key passed to view is 'products'. Example:
             * return view('products.index', compact('products'));
             */
            'products',

            /**
             *  So, the $collection here is $products, sent by the controller to the view. We want
             * to check, if the $collection contains the $product, defined a couple of lines above.
             * To do this, we must use a callback function.
             */
            function ($collection) use ($product) {
                /**
                 * https://laravel.com/docs/10.x/collections#method-contains
                 * contains() will returns true, if the collection contains the given item
                 */
                return $collection->contains($product);
            }
        );
    }

    public function test_paginated_products_table_doesnt_contain_11th_record()
    {
        /**
         * We create 11 products in the fake db
         */
        $products = Product::factory(11)->create();
        $lastProduct = $products->last();//the 11th product.

        //We create a fake user with factory, for logging in.
        $user = User::factory()->create();

        /**
         * If we go to the /products page, 10 products should be displayed. The 11th product,
         * although exists, should not be on the page. This is what we check.
         */
        $response = $this->actingAs($user)->get('/products');

        $response->assertOk();

        $response->assertViewHas(
            'products',
            function ($collection) use ($lastProduct) {
                return !$collection->contains($lastProduct);
            }
        );
    }

//     public function test_homepage_contains_table_product()
//     {
//         $product = Product::create([
//             'name' => 'table',
//             'price' => 123
//         ]);
//         $response = $this->actingAs($this->user)->get('/products');

//         $response->assertOk();
//         $response->assertSee($product->name);
//     }

//     public function test_homepage_contains_products_in_order()
//     {
//         [$product1, $product2] = Product::factory(2)->create();
//         $response = $this->actingAs($this->user)->get('/products');

//         $response->assertOk();
//         $response->assertSeeInOrder([$product1->name, $product2->name]);
//     }



//     public function test_admin_can_see_products_create_button()
//     {
//         $response = $this->actingAs($this->admin)->get('/products');

//         $response->assertOk();
//         $response->assertSee('Add new product');
//     }

//     public function test_non_admin_cannot_see_products_create_button()
//     {
//         $response = $this->actingAs($this->user)->get('/products');

//         $response->assertOk();
//         $response->assertDontSee('Add new product');
//     }

//     public function test_admin_can_access_product_create_page()
//     {
//         $response = $this->actingAs($this->admin)->get('/products/create');

//         $response->assertOk();
//     }

//     public function test_non_admin_cannot_access_product_create_page()
//     {
//         $response = $this->actingAs($this->user)->get('/products/create');

//         $response->assertForbidden();
//     }

//     public function test_create_product_successful()
//     {
//         $product = [
//             'name' => 'Product 123',
//             'price' => 1234
//         ];
//         $response = $this->followingRedirects()->actingAs($this->admin)->post('/products', $product);

//         $response->assertStatus(200);
//         $response->assertSeeText($product['name']);

//         $this->assertDatabaseHas('products', [
//             'name' => 'Product 123',
//             'price' => 123400
//         ]);

//         $lastProduct = Product::latest()->first();
//         $this->assertEquals($product['name'], $lastProduct->name);
//         $this->assertEquals($product['price'] * 100, $lastProduct->price);
//     }

//     public function test_product_edit_contains_correct_values()
//     {
//         $product = Product::factory()->create();
//         $this->assertDatabaseHas('products', [
//             'name' => $product->name,
//             'price' => $product->price
//         ]);
//         $this->assertModelExists($product);

//         $response = $this->actingAs($this->admin)->get('products/' . $product->id . '/edit');

//         $response->assertOk();
//         $response->assertSee('value="' . $product->name . '"', false);
//         $response->assertSee('value="' . $product->price . '"', false);
//         $response->assertViewHas('product', $product);
//     }

//     public function test_product_update_validation_error_redirects_back_to_form()
//     {
//         $product = Product::factory()->create();

//         $response = $this->actingAs($this->admin)->put('products/' . $product->id, [
//             'name' => '',
//             'price' => ''
//         ]);

//         $response->assertStatus(302);
//         $response->assertInvalid(['name', 'price']);
//     }

//     public function test_product_delete_successful()
//     {
//         $product = Product::factory()->create();

//         $response = $this->actingAs($this->admin)->delete('products/' . $product->id);

//         $response->assertStatus(302);
//         $response->assertRedirect('products');

//         $this->assertDatabaseMissing('products', $product->toArray());
//         $this->assertModelMissing($product);
//         $this->assertDatabaseCount('products', 0);
//     }

//     public function test_product_create_photo_upload_successful()
//     {
//         Storage::fake();
//         $filename = 'photo1.jpg';

//         $product = [
//             'name' => 'Product 123',
//             'price' => 1234,
//             'photo' => UploadedFile::fake()->image($filename), // ???
//         ];
//         $response = $this->followingRedirects()->actingAs($this->admin)->post('/products', $product);

//         $response->assertStatus(200);

//         $lastProduct = Product::latest()->first();
//         $this->assertEquals($filename, $lastProduct->photo);

//         Storage::assertExists('products/' . $filename);
//     }

//     public function test_product_create_job_notification_dispatched_successfully()
//     {
//         Bus::fake();

//         $product = [
//             'name' => 'Product 123',
//             'price' => 1234,
//         ];
//         $response = $this->followingRedirects()->actingAs($this->admin)->post('/products', $product);

//         $response->assertStatus(200);

//         Bus::assertDispatched(NewProductNotifyJob::class);
//     }

//     public function test_product_create_mail_sent_successfully()
//     {
//         Mail::fake();
//         Notification::fake();

//         $product = [
//             'name' => 'Product 123',
//             'price' => 1234,
//         ];
//         $response = $this->followingRedirects()->actingAs($this->admin)->post('/products', $product);

//         $response->assertStatus(200);

//         // ??? assert
//         Mail::assertSent(NewProductCreated::class);
//         Notification::assertSentTo($this->admin, NewProductCreatedNotification::class);
//     }

//     public function test_product_create_with_youtube_service()
//     {
//         $this->mock(YouTubeService::class)
//             ->shouldReceive('getThumbnailByID')
//             ->with('5XywKLjCD3g')
//             ->once()
//             ->andReturn('https://i.ytimg.com/vi/5XywKLjCD3g/default.jpg');

//         $product = [
//             'name' => 'Product 123',
//             'price' => 1234,
//             'youtube_id' => '5XywKLjCD3g',
//         ];
//         $response = $this->followingRedirects()->actingAs($this->admin)->post('/products', $product);

//         $response->assertStatus(200);
//     }

//     public function test_api_returns_products_list()
//     {
//         $product1 = Product::factory()->create();
//         $product2 = Product::factory()->create();
//         $response = $this->getJson('/api/products');

//         $response->assertJsonFragment([
//             'name' => $product1->name,
//             'price' => $product1->price
//         ]);
//         $response->assertJsonCount(2, 'data');
//     }

//     public function test_api_product_store_successful()
//     {
//         $product = [
//             'name' => 'Product 1',
//             'price' => 123
//         ];
//         $response = $this->postJson('/api/products', $product);

//         $response->assertCreated();
//         $response->assertSuccessful(); // but not assertOk()
//         $response->assertJson([
//             'name' => 'Product 1',
//             'price' => 12300
//         ]);
//     }

//     public function test_api_product_invalid_store_returns_error()
//     {
//         $product = [
//             'name' => '',
//             'price' => 123
//         ];
//         $response = $this->postJson('/api/products', $product);

//         $response->assertUnprocessable();
//         $response->assertJsonMissingValidationErrors('price');
//         $response->assertInvalid('name');
//     }

//     public function test_api_product_show_successful()
//     {
//         $productData = [
//             'name' => 'Product 1',
//             'price' => 123
//         ];
//         $product = Product::create($productData);

//         $response = $this->getJson('/api/products/' . $product->id);
//         $response->assertOk();
//         $response->assertJsonPath('data.name', $productData['name']);
//         $response->assertJsonMissingPath('data.created_at');
//         $response->assertJsonStructure([
//             'data' => [
//                 'id',
//                 'name',
//                 'price',
//             ]
//         ]);
//     }

//     public function test_api_product_update_successful()
//     {
//         $productData = [
//             'name' => 'Product 1',
//             'price' => 123
//         ];
//         $product = Product::create($productData);

//         $response = $this->putJson('/api/products/' . $product->id, [
//             'name' => 'Product updated',
//             'price' => 1234
//         ]);
//         $response->assertOk();
//         $response->assertJsonMissing($productData);
//     }

//     public function test_api_product_delete_logged_in_admin()
//     {
//         $product = Product::factory()->create();
//         $response = $this->actingAs($this->admin)->deleteJson('/api/products/' . $product->id);

//         $response->assertNoContent();

//         $this->assertDatabaseMissing('products', $product->toArray());
//         $this->assertDatabaseCount('products', 0);
//     }

//     public function test_api_product_delete_restricted_by_auth()
//     {
//         $product = Product::factory()->create();
//         $response = $this->deleteJson('/api/products/' . $product->id);

//         $response->assertUnauthorized();
//     }

//     public function test_product_service_create_returns_product()
//     {
//         $product = (new ProductService())->create('Test product', 1234);

//         $this->assertInstanceOf(Product::class, $product);
//     }

//     public function test_product_service_create_validation()
//     {
//         try {
//             (new ProductService())->create('Too big', 1234567);
//         } catch (\Exception $e) {
//             $this->assertInstanceOf(NumberFormatException::class, $e);
//         }
//     }

//     public function test_download_product_success()
//     {
//         $response = $this->get('/download');
//         $response->assertOk();
//         $response->assertHeader('Content-Disposition',
//             'attachment; filename=product-specification.pdf');
//     }

//     public function test_product_shows_when_published_at_correct_time()
//     {
//         $product = Product::factory()->create([
//             'published_at' => now()->addDay()->setTime(14, 00),
//         ]);

//         $this->freezeTime(function () use ($product) {
//             $this->travelTo(now()->addDay()->setTime(14, 01));
//             $response = $this->actingAs($this->user)->get('products');
//             $response->assertSeeText($product->name);
//         });

// //        $response = $this->actingAs($this->user)->get('/products');
// //        $response->assertDontSeeText($product->name);
//     }

//     public function test_artisan_publish_command_successful()
//     {
//         $this->artisan('product:publish 1')
//             ->assertExitCode(-1)
//             ->expectsOutput('Product not found');
//     }

//     public function test_job_product_publish_successful()
//     {
//         $product = Product::factory()->create();
//         $this->assertNull($product->published_at);

//         (new ProductPublishJob(1))->handle();

//         $product->refresh();
//         $this->assertNotNull($product->published_at);
//     }
}
