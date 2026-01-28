<?php

namespace App\Http\Controllers;

use App\Services\CheckoutBrService;
use App\Services\CheckoutProxyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        private CheckoutProxyService $checkoutProxyService,
        private CheckoutBrService $checkoutBrService
    ) {
    }

    public function handleCheckoutBr(Request $request): JsonResponse
    {
        return $this->checkoutBrService->handleCheckoutBr($request);
    }

    public function handleSusanPetRescue(Request $request): JsonResponse
    {
        return $this->checkoutProxyService->handleSusanPetRescue($request);
    }
}
