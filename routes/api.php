<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\TransfeeraWebhookController;
use App\Http\Controllers\TransfeeraProAnimalController;
use App\Http\Controllers\TransfeeraSiulsanController;
use App\Http\Controllers\LytexController;
use App\Http\Controllers\TransfeeraAutoPixController;
use App\Http\Controllers\PaidSusanPetRescueController;
use App\Http\Controllers\PayPalController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\NuveiController;
use App\Http\Controllers\SquareController;

Route::post('/checkout-br', [CheckoutController::class, 'handleCheckoutBr']);
Route::post('/checkout-susan-pet-rescue', [CheckoutController::class, 'handleSusanPetRescue']);

Route::post('/transfeera', [TransfeeraWebhookController::class, 'handle']);
Route::post('/proanimal', [TransfeeraProAnimalController::class, 'receive']);
Route::post('/siulsan', [TransfeeraSiulsanController::class, 'receive']);
Route::post('/lytex/invoice', [LytexController::class, 'createInvoice']);
Route::post('/lytex/webhook', [LytexController::class, 'webhook']);
Route::post('/automatic-pix/create-authorization', [TransfeeraAutoPixController::class, 'createAuthorization']);

Route::post('/paid-susan-pet-rescue', [PaidSusanPetRescueController::class, 'paid']);
Route::post('/paid-susan-pet-rescue-donor', [PaidSusanPetRescueController::class, 'paidDonor']);

Route::post('/paypal/create-order', [PayPalController::class, 'createOrder']);         // alias para PayPal Donate SDK
Route::post('/paypal/webhook', [PayPalController::class, 'webhook']); 
Route::post('/paypal/donation-notify', [PayPalController::class, 'donationNotify']);

Route::post('/stripe/payment-intent', [StripeController::class, 'createPaymentIntent']);
Route::post('/stripe/subscription', [StripeController::class, 'createSubscription']);
Route::post('/stripe/webhook', [StripeController::class, 'handle']);
Route::post('/stripe/webhook/mail', [StripeController::class, 'mail']);

Route::post('/square/payment', [SquareController::class, 'createPayment']);
Route::post('/square/payment/confirmed', [SquareController::class, 'handlePaid']);

Route::post('/nuvei/open-order', [NuveiController::class, 'openOrder']);
