<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WarehouseAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login')
                ->with('error', 'Anda harus login terlebih dahulu.');
        }

        // Allow access for owner regardless of outlet
        if ($user->role === 'owner') {
            return $next($request);
        }

        // For admin, check if their outlet is a warehouse
        if ($user->role === 'admin' && $user->outlet && $user->outlet->is_warehouse) {
            return $next($request);
        }

        // Deny access for all other users
        return redirect()->route('home')
            ->with('error', 'Anda tidak memiliki akses ke fitur ini. Hanya owner dan admin dari outlet pusat/gudang yang dapat mengakses fitur ini.');
    }
}
