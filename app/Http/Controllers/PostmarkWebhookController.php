<?php

namespace App\Http\Controllers;

use App\Support\MailTracking\PostmarkWebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostmarkWebhookController extends Controller
{
    public function __invoke(Request $request, PostmarkWebhookProcessor $processor): JsonResponse
    {
        $payload = $request->json()->all();

        if (! is_array($payload) || $payload === []) {
            $payload = $request->all();
        }

        if (is_array($payload) && $payload !== []) {
            $processor->process($payload);
        }

        return response()->json(['ok' => true]);
    }
}
