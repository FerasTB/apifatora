<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Handle user login and return an API token.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // Validate the login credentials
        $validator = Validator::make($request->all(), [
            'phone'    => 'required|string',
            'password' => 'required|string',
            // Optionally, you can validate the device name for token purposes
            // 'device_name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Attempt to authenticate the user
        $credentials = $request->only('phone', 'password');

        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid login credentials.'], 401);
        }

        $user = Auth::user();

        // Check if the user is active or any other status checks
        // For example:
        // if (!$user->is_active) {
        //     return response()->json(['message' => 'Account is inactive.'], 403);
        // }

        // Generate a Sanctum token for the user
        $token = $user->createToken('api_token')->plainTextToken;

        // Return the token and user data
        return response()->json([
            'message' => 'Login successful.',
            'token'   => $token,
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'phone' => $user->phone,
                'role'  => $user->role,
                // Add other user data as needed
            ],
        ], 200);
    }

    /**
     * Handle user logout and revoke the token.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.'], 200);
    }

    public function register(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'phone'    => 'required|string|max:255|unique:users',
            'password' => 'required|string|min:6',
        ], [
            'name.required'     => 'الاسم مطلوب',
            'phone.required'    => 'البريد الإلكتروني مطلوب',
            'phone.string'       => 'يرجى إدخال بريد إلكتروني صالح',
            'phone.unique'      => 'البريد الإلكتروني مستخدم بالفعل',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min'      => 'يجب أن تكون كلمة المرور مكونة من 6 أحرف على الأقل',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Create the user
        $user = User::create([
            'name'     => $request->name,
            'phone'    => $request->phone,
            'password' => Hash::make($request->password),
            // Set any additional default values, e.g., role
            'role'     => 'user',
        ]);
        $token = $user->createToken('api_token')->plainTextToken;

        // Return the token and user data
        return response()->json([
            'message' => 'Login successful.',
            'token'   => $token,
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'phone' => $user->phone,
                'role'  => $user->role,
                // Add other user data as needed
            ],
        ], 200);
    }
}
