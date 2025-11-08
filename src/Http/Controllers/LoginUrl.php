<?php

namespace TomatoPHP\FilamentTenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Stancl\Tenancy\Features\UserImpersonation;
use TomatoPHP\FilamentTenancy\Models\Tenant;

class LoginUrl extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|string|email|max:255',
        ]);

        $tenant = Tenant::query()->where('email', $request->get('email'))->first();
        if ($tenant) {
            $user = User::query()->where('email', $tenant->email)->first();
            if ($user) {
                $user->update([
                    'name' => $tenant->name,
                    'email' => $tenant->email,
                    'password' => $tenant->password,
                ]);
            }
        }

        return UserImpersonation::makeResponse($request->get('token'));
    }
}
