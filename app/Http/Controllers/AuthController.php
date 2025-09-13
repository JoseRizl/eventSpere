<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(Request $request){

        // Validate
        $fields = $request->validate([
            'name' => ['required', 'max:255'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'confirmed']
        ]);

        // Register
        $user = User::create($fields);

        // Login
        Auth::login($user);

        // Redirect
        return redirect()->route('home');
    }

    public function login(Request $request){
       $fields = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required']
       ]);

       if(Auth::attempt($fields, $request->remember)){
        $request->session()->regenerate();
        return redirect()->intended('/home');
       }

       return back()->withErrors([
        'email' => 'The provided credentials do not match our records.'
       ])->onlyInput('email');
    }

    public function logout(Request $request) {
        Auth::logout();

    $request->session()->invalidate();

    $request->session()->regenerateToken();

    return redirect()->route('login');
    }
}
