<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::redirect('/', 'login');
Route::get('download', [ProductController::class, 'download']);

/**
 * 1 level middleware: here we check if the user is logged in. Only authenticated users can access
 * the /products page.
 */
Route::middleware('auth')->group(function() {
    Route::get('products', [ProductController::class, 'index'])->name('products.index');

    /**
     * 2 level middleware: here we check if the user is an admin. Only admins can access the /products/create
     * page.
     */
    Route::middleware('is_admin')->group(function() {
        Route::get('products/create', [ProductController::class, 'create'])
            ->name('products.create');
        Route::post('products', [ProductController::class, 'store'])
            ->name('products.store');
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])
            ->name('products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])
            ->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])
            ->name('products.destroy');
    });
});

require __DIR__.'/auth.php';
