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

        // Allow access if user is owner/admin or belongs to a warehouse outlet
        if (!$user || (!in_array($user->role, ['owner', 'admin']) && !$user->isWarehouseStaff())) {
            return redirect()->route('home')
                ->with('error', 'Anda tidak memiliki akses ke fitur ini. Hanya outlet pusat yang dapat mengakses fitur gudang.');
        }

        return $next($request);
    }
}
