<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // Login dengan "login" (email atau username) + password
    public function login(Request $request)
    {
        $cred = $request->validate([
            'login'    => 'required|string', // email ATAU username
            'password' => 'required|string',
        ]);

        $login = $cred['login'];

        // Cek email dulu, jika tidak sesuai format email cari sebagai username
        $query = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? User::where('email', $login)
            : User::where('username', $login);

        $user = $query->first();

        if (!$user || !Hash::check($cred['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 422);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
