<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\LikertScale;
use App\Models\Survey;
use App\Models\User;
use Database\Seeders\LikertScaleSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Register, log in and log out, using Laravel's built-in session authentication.
 * Passwords are never stored as typed: the User model hashes them (bcrypt).
 */
class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request)
    {
        // Auth::attempt hashes the typed password and compares it with the stored hash.
        if (! Auth::attempt($request->validated(), $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'The email or password is incorrect.']);
        }

        // A new session id after login prevents "session fixation" attacks.
        $request->session()->regenerate();

        return redirect()->intended(route('surveys.index'));
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request)
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create($request->validated()); // the password is hashed by the model's cast
            $this->claimExistingData($user);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('surveys.index')->with('success', "Welcome to StatEase, {$user->name}!");
    }

    public function logout(Request $request)
    {
        Auth::logout();

        // Throw away the session and its CSRF token, so the old session cannot be reused.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have been logged out.');
    }

    /**
     * Surveys and custom scales made before accounts existed have no owner.
     * The first account to register becomes their owner, so no data is lost.
     */
    private function claimExistingData(User $user): void
    {
        if (User::count() !== 1) {
            return;
        }

        Survey::whereNull('user_id')->update(['user_id' => $user->id]);

        $builtInNames = array_column(LikertScaleSeeder::DEFAULT_SCALES, 'name');
        LikertScale::whereNull('user_id')->whereNotIn('name', $builtInNames)->update(['user_id' => $user->id]);
    }
}
