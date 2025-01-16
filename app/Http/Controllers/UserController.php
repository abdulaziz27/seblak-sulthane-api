<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (Auth::user()->role === 'staff') {
                return redirect()->route('home')->with('error', 'Unauthorized access');
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $query = User::with('outlet');

        // If admin, only show users from their outlet
        if (Auth::user()->role === 'admin') {
            $query->where('outlet_id', Auth::user()->outlet_id);
        }

        // Search functionality
        if ($request->input('name')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->input('name') . '%')
                    ->orWhere('email', 'like', '%' . $request->input('name') . '%');
            });
        }

        $users = $query->paginate(10);
        return view('pages.users.index', compact('users'));
    }

    public function create()
    {
        // Only owner can see all outlets, admin only sees their own outlet
        $outlets = Auth::user()->role === 'owner'
            ? Outlet::all()
            : Outlet::where('id', Auth::user()->outlet_id)->get();

        return view('pages.users.create', compact('outlets'));
    }

    public function store(Request $request)
    {
        // Validate the request
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'role' => 'required|in:owner,admin,staff',
            'outlet_id' => 'required|exists:outlets,id',
        ]);

        // Check permissions
        if (Auth::user()->role === 'admin') {
            // Admins can only create staff users
            if ($request->role !== 'staff') {
                return redirect()->back()->with('error', 'You can only create staff accounts');
            }
            // Force outlet_id to admin's outlet
            $request->merge(['outlet_id' => Auth::user()->outlet_id]);
        }

        // Create the user
        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'outlet_id' => $request->outlet_id,
        ]);

        return redirect()->route('users.index')->with('success', 'User created successfully');
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);

        // If admin, can only edit users from their outlet
        if (Auth::user()->role === 'admin') {
            if ($user->outlet_id !== Auth::user()->outlet_id) {
                return redirect()->route('users.index')->with('error', 'Unauthorized access');
            }
            if ($user->role !== 'staff') {
                return redirect()->route('users.index')->with('error', 'You can only edit staff accounts');
            }
        }

        $outlets = Auth::user()->role === 'owner'
            ? Outlet::all()
            : Outlet::where('id', Auth::user()->outlet_id)->get();

        return view('pages.users.edit', compact('user', 'outlets'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Validate request
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $id,
            'role' => 'required|in:owner,admin,staff',
            'outlet_id' => 'required|exists:outlets,id',
        ]);

        // Check permissions
        if (Auth::user()->role === 'admin') {
            if ($user->outlet_id !== Auth::user()->outlet_id) {
                return redirect()->route('users.index')->with('error', 'Unauthorized access');
            }
            if ($request->role !== 'staff' || $user->role !== 'staff') {
                return redirect()->route('users.index')->with('error', 'You can only manage staff accounts');
            }
            // Force outlet_id to admin's outlet
            $request->merge(['outlet_id' => Auth::user()->outlet_id]);
        }

        // Update the user
        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'outlet_id' => $request->outlet_id,
        ]);

        // Update password if provided
        if ($request->password) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        return redirect()->route('users.index')->with('success', 'User updated successfully');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Check permissions
        if (Auth::user()->role === 'admin') {
            if ($user->outlet_id !== Auth::user()->outlet_id || $user->role !== 'staff') {
                return redirect()->route('users.index')->with('error', 'Unauthorized access');
            }
        }

        // Prevent deletion of the last owner
        if ($user->role === 'owner' && User::where('role', 'owner')->count() <= 1) {
            return redirect()->route('users.index')
                ->with('error', 'Cannot delete the last owner account');
        }

        // Prevent self-deletion
        if ($user->id === Auth::id()) {
            return redirect()->route('users.index')
                ->with('error', 'Cannot delete your own account');
        }

        $user->delete();
        return redirect()->route('users.index')->with('success', 'User deleted successfully');
    }
}
