<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Cart;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function form(string $mode = 'login')
    {
        return view('auth.form', compact('mode'));
    }

    public function login(Request $r, Cart $cart)
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($d + ['active' => true], $r->boolean('remember'))) {
            return back()->withErrors(['email' => 'The supplied credentials could not be verified.'])->onlyInput('email');
        }
        $r->session()->regenerate();
        $cart->mergeGuest();

        return redirect()->intended('/account');
    }

    public function register(Request $r, Cart $cart)
    {
        $d = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:150|unique:users', 'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()]]);
        $user = User::create($d);
        Auth::login($user);
        $r->session()->regenerate();
        $cart->mergeGuest();

        return redirect('/account');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/');
    }

    public function forgot(Request $r)
    {
        $r->validate(['email' => 'required|email']);
        if (! config('store.mail_enabled')) {
            return back()->withErrors(['email' => 'Password recovery email is not configured. Please contact the store.']);
        }
        Password::sendResetLink($r->only('email'));

        return back()->with('success', 'If an eligible account exists, a recovery link has been requested.');
    }

    public function reset(Request $r)
    {
        $r->validate(['token' => 'required', 'email' => 'required|email', 'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()]]);
        $status = Password::reset($r->only('email', 'password', 'password_confirmation', 'token'), function ($u, $p) {
            $u->forceFill(['password' => Hash::make($p), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($u));
        });

        return $status === Password::PasswordReset ? redirect('/login')->with('success', __($status)) : back()->withErrors(['email' => __($status)]);
    }
}
