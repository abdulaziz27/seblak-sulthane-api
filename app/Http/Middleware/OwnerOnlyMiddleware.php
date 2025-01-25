<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OwnerOnlyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::user() && Auth::user()->role !== 'owner') {
            return redirect()->route('home')->with('error', 'Only owner can access this feature');
        }

        return $next($request);
    }
}
