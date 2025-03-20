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
            'outlet_id' => 'required_unless:role,owner|nullable|exists:outlets,id',
        ]);

        // Check permissions
        if (Auth::user()->role === 'admin') {
            // Admins can only create staff users
            if ($request->role !== 'staff') {
                return redirect()->back()->with('error', 'Anda hanya bisa membuat akun staff');
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
            'outlet_id' => $request->outlet_id ?: null,
        ]);

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil dibuat');
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);

        // If admin, can only edit their own account or staff from their outlet
        if (Auth::user()->role === 'admin') {
            if (
                $user->id !== Auth::id() && // Allow editing self
                ($user->outlet_id !== Auth::user()->outlet_id || $user->role !== 'staff')
            ) {
                return redirect()->route('users.index')->with('error', 'Akses tidak diizinkan');
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
            if ($user->id !== Auth::id()) { // Not editing self
                if ($user->outlet_id !== Auth::user()->outlet_id || $user->role !== 'staff') {
                    return redirect()->route('users.index')->with('error', 'Akses tidak diizinkan');
                }
                // Force outlet_id to admin's outlet for staff
                $request->merge(['outlet_id' => Auth::user()->outlet_id]);
            } else {
                // Admin editing themselves - don't allow role or outlet change
                $request->merge([
                    'role' => $user->role,
                    'outlet_id' => $user->outlet_id
                ]);
            }
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

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil diupdate');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Check permissions
        if (Auth::user()->role === 'admin') {
            if ($user->outlet_id !== Auth::user()->outlet_id || $user->role !== 'staff') {
                return redirect()->route('users.index')->with('error', 'Akses tidak diizinkan');
            }
        }

        // Prevent deletion of the last owner
        if ($user->role === 'owner' && User::where('role', 'owner')->count() <= 1) {
            return redirect()->route('users.index')
                ->with('error', 'Tidak bisa menghapus akun owner terakhir!');
        }

        // Prevent self-deletion
        if ($user->id === Auth::id()) {
            return redirect()->route('users.index')
                ->with('error', 'Cannot delete your own account');
        }

        $user->delete();
        return redirect()->route('users.index')->with('success', 'Pengguna berhasil dihapus');
    }

    // Profile
    /**
     * Display the user's profile.
     */
    public function profile()
    {
        $user = Auth::user();

        // Include the outlet relation for viewing
        $user->load('outlet');

        // Get outlets for the dropdown if needed
        $outlets = [];
        if ($user->role === 'owner') {
            $outlets = Outlet::all();
        } else {
            $outlets = Outlet::where('id', $user->outlet_id)->get();
        }

        return view('pages.users.profile', compact('user', 'outlets'));
    }

    /**
     * Update the user's profile.
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        // Validate request
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'current_password' => 'nullable|required_with:password',
            'password' => 'nullable|min:8|required_with:current_password|confirmed',
        ]);

        // Check current password if trying to update password
        if ($request->filled('current_password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return back()
                    ->withInput()
                    ->withErrors(['current_password' => 'Kata sandi saat ini tidak benar']);
            }
        }

        // Update basic info
        $user->name = $request->name;
        $user->email = $request->email;

        // Update password if provided
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return redirect()->route('profile')
            ->with('success', 'Profil berhasil diperbarui');
    }
}
