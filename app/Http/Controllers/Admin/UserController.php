<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of users or third-party apps.
     */
    public function index(Request $request)
    {
        $role = $request->query('role');

        if ($role) {
            $users = User::where('role', $role)->get();
        } else {
            $users = User::all();
        }

        return response()->json($users);
    }

    /**
     * Store a newly created user or third-party app.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name'                     => 'required|string|max:255',
            'email'                    => 'nullable|email|unique:users',
            'phone'                    => 'required|string|unique:users',
            'password'                 => 'required|string|min:6',
            'role'                     => ['required', Rule::in(['user', 'admin', 'third_party_app'])],
            'notification_preferences' => 'nullable|array',
        ]);

        $user = User::create([
            'name'                     => $validatedData['name'],
            'email'                    => $validatedData['email'],
            'email'                    => $validatedData['phone'],
            'password'                 => Hash::make($validatedData['password']),
            'role'                     => $validatedData['role'],
            'notification_preferences' => $validatedData['notification_preferences'] ?? null,
        ]);

        return response()->json(['message' => 'User created successfully', 'user' => $user], 201);
    }

    /**
     * Display the specified user or third-party app.
     */
    public function show($id)
    {
        $user = User::findOrFail($id);

        return response()->json($user);
    }

    /**
     * Update the specified user or third-party app.
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validatedData = $request->validate([
            'name'                     => 'sometimes|string|max:255',
            'email'                    => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'phone'                    => ['sometimes', 'phone', Rule::unique('users')->ignore($user->id)],
            'password'                 => 'sometimes|string|min:6',
            'role'                     => ['sometimes', Rule::in(['user', 'admin', 'third_party_app'])],
            'notification_preferences' => 'nullable|array',
        ]);

        if (isset($validatedData['password'])) {
            $validatedData['password'] = Hash::make($validatedData['password']);
        }

        $user->update($validatedData);

        return response()->json(['message' => 'User updated successfully', 'user' => $user]);
    }

    /**
     * Remove the specified user or third-party app.
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Prevent deletion of admin accounts if necessary
        if ($user->isAdmin()) {
            return response()->json(['message' => 'Cannot delete admin accounts'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    public function generateOtp()
    {
        $user = Auth::user();

        // Generate a random OTP (e.g., 6-digit numeric code)
        $plainOtp = random_int(100000, 999999);

        // Hash the OTP before storing
        $hashedOtp = Hash::make($plainOtp);

        // Set expiration time (e.g., 10 minutes from now)
        $expiresAt = now()->addMinutes(10);

        // Store the OTP
        $otp = Otp::create([
            'user_id' => $user->id,
            'otp' => $hashedOtp,
            'expires_at' => $expiresAt,
        ]);

        // Return or display the plain OTP to the user
        return response()->json([
            'message' => 'OTP generated successfully.',
            'otp' => $plainOtp, // Display the plain OTP to the user
            'expires_at' => $expiresAt,
        ], 201);
    }
}
