<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\TransfeeraWebhookController;
use App\Http\Controllers\TransfeeraProAnimalController;
use App\Http\Controllers\TransfeeraSiulsanController;
use App\Http\Controllers\LytexController;
use App\Http\Controllers\TransfeeraAutoPixController;
use App\Http\Controllers\PaidSusanPetRescueController;
use App\Http\Controllers\BackfillSusanPetRescuePaidController;
use App\Http\Controllers\BackfillSusanPetRescuePaidControllerUtmify;
use App\Http\Controllers\PayPalController;
use App\Http\Controllers\StripeController;

Route::post('/checkout', [CheckoutController::class, 'handle']);
Route::post('/transfeera', [TransfeeraWebhookController::class, 'handle']);
Route::post('/proanimal', [TransfeeraProAnimalController::class, 'receive']);
Route::post('/siulsan', [TransfeeraSiulsanController::class, 'receive']);
Route::post('/lytex/invoice', [LytexController::class, 'createInvoice']);
Route::post('/automatic-pix/create-authorization', [TransfeeraAutoPixController::class, 'createAuthorization']);

Route::post('/checkout-susan-pet-rescue', [CheckoutController::class, 'handleSusanPetRescue']);
Route::post('/checkout-susan-pet-rescue-donor', [CheckoutController::class, 'handleSusanPetRescueDonor']);

Route::post('/paid-susan-pet-rescue', [PaidSusanPetRescueController::class, 'paid']);
Route::post('/paid-susan-pet-rescue-donor', [PaidSusanPetRescueController::class, 'paidDonor']);

Route::post('/paypal/orders', [PayPalController::class, 'createOrder']);                 // cria order
Route::post('/paypal/orders/{orderId}/capture', [PayPalController::class, 'captureOrder']); // captura (pago)   
Route::post('/paypal/subscriptions', [PayPalController::class, 'createSubscription']); 
Route::post('/paypal/webhook', [PayPalController::class, 'webhook']); 

Route::post('/stripe/checkout-session', [StripeController::class, 'createCheckoutSession']);
Route::post('/stripe/payment-intent', [StripeController::class, 'createPaymentIntent']);
Route::post('/stripe/webhook', [StripeController::class, 'handle']);

// Route::post('/spr/backfill/capi/test-first', [BackfillSusanPetRescuePaidController::class, 'testFirst']);
// Route::post('/spr/backfill/capi/run', [BackfillSusanPetRescuePaidController::class, 'run']);
// Route::post('/spr/backfill/capi/send-one', [BackfillSusanPetRescuePaidController::class, 'sendOne']);

// routes/api.php (ou routes/web.php se preferir)
// Route::post('/susan/utmify/resend-one', [BackfillSusanPetRescuePaidControllerUtmify::class, 'resendUtmifyOne']);