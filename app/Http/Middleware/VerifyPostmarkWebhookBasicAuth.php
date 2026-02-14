<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPostmarkWebhookBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedUser = (string) config('services.postmark.webhook_basic_user', '');
        $expectedPass = (string) config('services.postmark.webhook_basic_pass', '');

        if ($expectedUser === '' || $expectedPass === '') {
            report('Postmark webhook credentials are not configured.');

            return response('Unauthorized', 401, ['WWW-Authenticate' => 'Basic realm="Postmark Webhook"']);
        }

        $providedUser = (string) ($request->getUser() ?? '');
        $providedPass = (string) ($request->getPassword() ?? '');

        if (! hash_equals($expectedUser, $providedUser) || ! hash_equals($expectedPass, $providedPass)) {
            return response('Unauthorized', 401, ['WWW-Authenticate' => 'Basic realm="Postmark Webhook"']);
        }

        return $next($request);
    }
}
