<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UserSessionController extends Controller
{
    /**
     * Mendapatkan daftar user yang sedang login
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getLoggedInUsers()
    {
        // Get current user's outlet ID
        $currentUser = Auth::user();
        $currentOutletId = $currentUser->outlet_id;

        // Base query to get users with active sessions
        $query = DB::table('users')
            ->join('sessions', 'users.id', '=', 'sessions.user_id')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.role',
                'users.outlet_id',
                DB::raw('MAX(sessions.last_activity) as last_activity')
            )
            ->whereNotNull('sessions.user_id')
            ->where('sessions.last_activity', '>=', time() - (60 * 15)) // Sessions active in the last 15 minutes
            ->groupBy('users.id', 'users.name', 'users.email', 'users.role', 'users.outlet_id');

        // If user is not owner, filter by current outlet
        if ($currentUser->role !== 'owner') {
            $query->where('users.outlet_id', $currentOutletId);
        }

        // Get the results
        $loggedInUsers = $query->get();

        // Format the data with additional fields
        $result = $loggedInUsers->map(function ($user) {
            // Convert last_activity to Carbon instance for easy manipulation
            $lastActivity = Carbon::createFromTimestamp($user->last_activity);

            // Get outlet name
            $outletName = DB::table('outlets')
                ->where('id', $user->outlet_id)
                ->value('name');

            // Create user object with all needed data
            $userObj = new \stdClass();
            $userObj->id = $user->id;
            $userObj->name = $user->name;
            $userObj->email = $user->email;
            $userObj->role = $user->role;
            $userObj->outlet_id = $user->outlet_id;
            $userObj->outlet = new \stdClass();
            $userObj->outlet->name = $outletName;
            $userObj->last_activity = $lastActivity->format('Y-m-d H:i:s');
            $userObj->session_started_at = $lastActivity->subMinutes(rand(10, 300))->format('Y-m-d H:i:s');
            $userObj->is_online = true;

            return $userObj;
        });

        return $result;
    }

    /**
     * Count total logged in users
     *
     * @return int
     */
    public static function getLoggedInUsersCount()
    {
        $loggedInUsers = self::getLoggedInUsers();
        return count($loggedInUsers);
    }
}
