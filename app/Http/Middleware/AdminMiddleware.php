<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->isAdmin()) {
            return redirect()
                ->to(url()->previous() ?: route('dashboard.index'))
                ->withErrors(['auth' => 'Acesso restrito: apenas administradores.']);
        }

        return $next($request);
    }
}
