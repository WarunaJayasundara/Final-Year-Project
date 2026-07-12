<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * This app has no server-rendered login page (it's an API-only backend
     * behind the React SPA, which handles auth state itself via /api/auth/me)
     * - there is no "login" named route to redirect to, so never attempt
     * one. Returning null here always routes unauthenticated requests
     * through Handler::unauthenticated()'s JSON response instead.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        return null;
    }
}
