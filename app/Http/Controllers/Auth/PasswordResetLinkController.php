<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // Password::sendResetLink returns the same "we emailed you if it exists"
        // status for unknown addresses, so this does not confirm account existence.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', __('If that email address is in our system, a password reset link is on its way.'));
    }
}
