<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Iresis\LoginAudit\Facades\LoginAudit;
use Workbench\App\Models\User;

Route::get('/', function () {
    return view('welcome');
});

// Manual smoke-test routes for the login-audit package. Not part of the
// package itself — only served locally via `composer serve`.
Route::get('/login-audit-demo/login', function () {
    $user = User::query()->firstOrCreate(
        ['email' => 'test@example.com'],
        ['name' => 'Test User', 'password' => bcrypt('password')],
    );

    Auth::login($user);

    return redirect('/login-audit-demo/sessions');
});

Route::get('/login-audit-demo/logout', function () {
    Auth::logout();

    return redirect('/');
});

Route::middleware(['auth', 'login-audit.activity'])->get('/login-audit-demo/sessions', function () {
    $user = Auth::user();

    return response()->json([
        'user' => ['id' => $user->id, 'email' => $user->email],
        'active_sessions' => LoginAudit::activeSessionsFor($user),
        'devices' => LoginAudit::devicesFor($user),
    ]);
});
