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

    public User $user;
    public User $admin;

    /**
     * It seems to me that this is almost like a constructor in OOP.
     */
    protected function setUp(): void
    {
        parent::setUp();//Here we call the parent's setUp() first, which is mandatory

        //We create a fake user with factory, for logging in.
        $this->user = User::factory()->create(
            [
                'is_admin' => false
            ]
        );

        //We create an admin
        $this->admin = User::factory()->create(
            [
                'is_admin' => true
            ]
        );

    }

    public function test_load_products_page()
    {
        /**
         * http://127.0.0.1:8000/products
         */
        $response = $this->actingAs($this->user)->get('/products');//sending a get request...
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

        /**
         * The route is protected with auth middleware. User must be logged in.
         */
        $response = $this->actingAs($this->user)->get('/products');

        $response->assertOk();
        $response->assertSee(__('No products found'));
    }

    public function test_homepage_contains_non_empty_table()
    {
        //ARRANGE: set up data
        $product = Product::create([
            'name' => 'Product 1',
            'price' => 123
        ]);

        //ACT: simulate/trigger the thing that you want to test
        $response = $this->actingAs($this->user)->get('/products');

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

        /**
         * If we go to the /products page, 10 products should be displayed. The 11th product,
         * although exists, should not be on the page. This is what we check.
         */
        $response = $this->actingAs($this->user)->get('/products');

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

    /**
     * Here we test the view, if it has a button displayed.
     */
    public function test_admin_can_see_products_create_button()
    {
        $response = $this->actingAs($this->admin)->get('/products');

        $response->assertOk();//Assert that the response has a 200 HTTP status code:

        /**
         * 'Add new product' is the text on the button.
         */
        $response->assertSee('Add new product');
    }

    /**
     * Here we test the view, if it has a button displayed.
     */
    public function test_non_admin_cannot_see_products_create_button()
    {
        $response = $this->actingAs($this->user)->get('/products');

        $response->assertOk();//Assert that the response has a 200 HTTP status code:

        /**
         * 'Add new product' is the text on the button.
         */
        $response->assertDontSee('Add new product');
    }

    /**
     * Here we test if the admin or the user can access a page (route).
     */
    public function test_admin_can_access_product_create_page()
    {
        $response = $this->actingAs($this->admin)->get('/products/create');

        $response->assertOk();//Assert that the response has a 200 HTTP status code:
    }

    /**
     * Here we test if the admin or the user can access a page (route).
     */
    public function test_non_admin_cannot_access_product_create_page()
    {
        $response = $this->actingAs($this->user)->get('/products/create');

        /**
         * https://laravel.com/docs/10.x/http-tests#assert-forbidden
         * Assert that the response has a forbidden (403) HTTP status code
         *
         * The is_admin middleware is created so, that is will give this response to all non-admin
         * users: abort(403);
         */
        $response->assertForbidden();
    }


    /**
     * Here we test if a created product actually appeared in the fake db.
     */
    public function test_create_product_successful()
    {
        //Creating a product.
        $product = [
            'name' => 'Product 123',
            'price' => 1234
        ];

        /**
         * This is for things that happen in the browser. This does not cover what happens ind the
         * db.
         */
        $response = $this->followingRedirects()
                            ->actingAs($this->admin)
                            ->post('/products', $product);

        $response->assertStatus(200);

        /**
         * https://laravel.com/docs/10.x/http-tests#assert-see-text
         *
         */
        $response->assertSeeText($product['name']);

        /**
         * We check here if the created product is in the fake db.
         * Assert that a table in the database contains records matching the given key / value
         * query constraints
         *
         * https://laravel.com/docs/10.x/database-testing#assert-database-has
         */
        $this->assertDatabaseHas(
            'products', //this is the table name
            [
                'name' => 'Product 123',//this is the product that we just created
                'price' => 123400
            ]
        );

        /**
         * A couple of lines above we created a new product, and inserted it into db. That means
         * that this new product is the last one, if all is ok. This is what we want to check here.
         * So here we check if the last products name and price are correct.
         */
        $lastProduct = Product::latest()->first();
        $this->assertEquals($product['name'], $lastProduct->name);
        $this->assertEquals($product['price'] * 100, $lastProduct->price);
    }


    /**
     * This is only for checking if the right values were displayed in the edit form. This test
     * does not test if the editing is actaully working.
     */
    public function test_product_edit_contains_correct_values()
    {
        //Create a product
        $product = Product::factory()->create();

        //Check if the product is in db
        $this->assertDatabaseHas('products', [
            'name' => $product->name,
            'price' => $product->price
        ]);

        /**
         * Assert that a given model exists in the database:
         * https://laravel.com/docs/10.x/database-testing#assert-model-exists
         */
        $this->assertModelExists($product);

        /**
         * Opening the edit page, with the newly created product, that we will edit.
         */
        $response = $this->actingAs($this->admin)->get('products/' . $product->id . '/edit');

        //Checking if response has 200 status
        $response->assertOk();


        /**
         * This string below that we create should be in the html.
         * https://laravel.com/docs/10.x/http-tests#assert-see
         * false = do not do escaping on the ' " ' thingies in the string.
         */
        $response->assertSee('value="' . $product->name . '"', false);
        $response->assertSee('value="' . $product->price . '"', false);

        /**
         * This is a bit shorter way, that with the assertSee() above.
         * When opening the /edit page, the controller does this:
         * return view('products.edit', compact('product'));
         * Meaning it send a $product under the key 'product'. This is exactly what we check here.
         */
        $response->assertViewHas('product', $product);
    }

    /**
     * Now, this is the testing of the product update validation.
     */
    public function test_product_update_validation_error_redirects_back_to_form()
    {
        //We create a product in the fake db.
        $product = Product::factory()->create();

        /**
         * Notice: here we use PUT method for updating. But we are trying deliberatly to update
         * to wrong values. Because, name and price are mandatory and requered (defined so in the
         * validation rules). And here we try to send no new value for name and price. Just an
         * empty string. Which should trigger a validation error.
         */
        $response = $this->actingAs($this->admin)->put(
            'products/' . $product->id,
            [
                'name' => '',
                'price' => ''
            ]
        );

        /**
         * We expect here a redirect after a successfull update. Because, after a successfull update
         * this will happen in the controller:
         * return redirect()->route('products.index');
         */
        $response->assertStatus(302);

        /**
         * Assert that the response has validation errors for the given keys.
         * https://laravel.com/docs/10.x/http-tests#assert-invalid
         */
        $response->assertInvalid(['name', 'price']);
    }

    public function test_product_delete_successful()
    {
        //Creating a product for deletion
        $product = Product::factory()->create();

        //Sending a delete request as admin
        $response = $this->actingAs($this->admin)->delete('products/' . $product->id);

        /**
         * After a successfull deletion, we expect a redirect to the products page.
         * 302 is status for redirection.
         */
        $response->assertStatus(302);

        /**
         * Assert that the response is a redirect to the given URI:
         * https://laravel.com/docs/10.x/http-tests#assert-redirect
         */
        $response->assertRedirect('products');

        /**
         * Assert that a table in the database does not contain records matching the given key /
         * value query constraints:
         * https://laravel.com/docs/10.x/database-testing#assert-database-missing
         */
        $this->assertDatabaseMissing('products', $product->toArray());

        /**
         * Assert that a given model does not exist in the database:
         * https://laravel.com/docs/10.x/database-testing#assert-model-missing
         */
        $this->assertModelMissing($product);

        /**
         * Assert that a table in the database contains the given number of records: 0.
         * 0, because for every test we reset our db. Because this is a fake db for testing.
         * https://laravel.com/docs/10.x/database-testing#assert-database-count
         */
        $this->assertDatabaseCount('products', 0);
    }

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
