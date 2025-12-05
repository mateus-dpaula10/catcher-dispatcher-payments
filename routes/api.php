<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\TransfeeraWebhookController;
use App\Http\Controllers\TransfeeraProAnimalController;
use App\Http\Controllers\TransfeeraSiulsanController;
use App\Http\Controllers\LytexController;
use App\Http\Controllers\TransfeeraAutoPixController;

Route::post('/checkout', [CheckoutController::class, 'handle']);
Route::post('/transfeera', [TransfeeraWebhookController::class, 'handle']);
Route::post('/proanimal', [TransfeeraProAnimalController::class, 'receive']);
Route::post('/siulsan', [TransfeeraSiulsanController::class, 'receive']);
Route::post('/lytex/invoice', [LytexController::class, 'createInvoice']);
Route::post('/automatic-pix/create-authorization', [TransfeeraAutoPixController::class, 'createAuthorization']);