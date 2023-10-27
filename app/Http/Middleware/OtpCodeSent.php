<?php

namespace App\Http\Middleware;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Closure;

class OtpCodeSent
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (session()->has('otp.user_id') && session()->has('otp.code')) {
            return $next($request);
        }

        return redirect(route('login'));
    }
}
