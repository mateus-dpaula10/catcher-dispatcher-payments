<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DashboardIndexService;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardIndexService $service)
    {
        $data = $service->handle($request);
        return view('dashboard.index', $data);
    }
}
