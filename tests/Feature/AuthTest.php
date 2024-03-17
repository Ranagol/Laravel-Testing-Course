<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AuthTest extends TestCase
{
    /**
     * if we write to the fake db some data (example creating user for testing the authentication)
     * then we must use this RefreshDatabase trait. This will make our data to be written into the
     * fake db.
     */
    use RefreshDatabase;

    public function test_successfull_login_redirects_to_products()
    {
        /**
         * We create a user. This user will be in the db. This will make possible for this user
         * to log in with this credentials.
         */
        User::create([
            'name' => 'User',
            'email' => 'user@user.com',
            'password' => bcrypt('password123')
        ]);

        /**
         * So we login the user. Aka we send a post request with user's credentials to the /login
         * page.
         */
        $response = $this->post('/login', [
            'email' => 'user@user.com',
            'password' => 'password123'
        ]);

        /**
         * The HTTP response status code 302 Found is a common way of performing URL redirection.
         * Here we expect that after a successfull login, the user will be redirected to /products.
         */
        $response->assertStatus(302);
        $response->assertRedirect('products');
    }

    /**
     * When an unathenticated user tries to access /products, Inertia and Breez will simply redirect
     * the user to the /login page.
     * The HTTP response status code 302 Found is a common way of performing URL redirection.
     */
    public function test_unauthenticated_user_cannot_access_product()
    {
        $response = $this->get('/products');

        $response->assertStatus(302);
        $response->assertRedirect('login');
    }

    // public function test_registration_fires_events()
    // {
    //     Event::fake();
    //     // $this->expectsEvents(Registered::class);

    //     $response = $this->post('/register', [
    //         'name' => 'User',
    //         'email' => 'user@user.com',
    //         'password' => 'password123',
    //         'password_confirmation' => 'password123',
    //     ]);

    //     $response->assertStatus(302);

    //     Event::assertDispatched(Registered::class);
    // }
}
