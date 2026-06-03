<?php

namespace App\Http\Middleware;

use App\Models\MobileAccessToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();

        if (! is_string($plainToken) || $plainToken === '') {
            return $this->unauthorized();
        }

        $token = MobileAccessToken::query()
            ->with('user.roles')
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if (! $token || $token->isExpired() || ! $token->user) {
            return $this->unauthorized();
        }

        $token->forceFill(['last_used_at' => now()])->save();

        Auth::setUser($token->user);
        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('mobile_access_token', $token);

        return $next($request);
    }

    private function unauthorized(): Response
    {
        return response()->json([
            'message' => 'Token movil invalido o vencido.',
        ], 401);
    }
}
