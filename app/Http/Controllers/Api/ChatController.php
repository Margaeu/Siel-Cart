<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatController extends Controller
{
    // Mirrored as MAX_MESSAGE_LENGTH in chatbot-server/lib/http-guard.js, which
    // enforces the same cap for any caller that skips this controller, and
    // rendered into the widget's maxlength. Change all of them together.
    public const MAX_MESSAGE_LENGTH = 100;

    // What a shopper sees when the assistant can't be reached. Same wording as
    // FRIENDLY_ERROR_MESSAGE in chatbot-server/lib/store-knowledge.js. The reply
    // used to be 'AI Connection Failed: '.$e->getMessage() or 'AI Service Error
    // Status: 500' -- shown to guests, and the exception text carried the
    // chatbot's internal URL. The detail now goes to the log instead.
    public const UNAVAILABLE_MESSAGE = 'Our assistant is temporarily unavailable. Please browse our catalog on the store page or contact the UBAP Office directly for immediate assistance.';

    public function store(Request $request)
    {
        // The cap bounds what an anonymous caller can forward to the paid model API
        // in a single request. Nothing here is stored: the Terms and Conditions (10.4)
        // promise chatbot conversations are not recorded, and there is no table for them.
        $request->validate([
            'message' => 'required|string|max:'.self::MAX_MESSAGE_LENGTH,
            // The browser remembers what was already recommended (the Node service is
            // stateless) so "show me more" can continue instead of repeating itself.
            'shown' => 'nullable|array|max:100',
            'shown.*' => 'string|max:255',
            'last_query' => 'nullable|string|max:'.self::MAX_MESSAGE_LENGTH,
        ]);

        // Query Express/Node AI Server
        $extras = [];

        try {
            $nodeApiUrl = config('services.chatbot.url', 'http://127.0.0.1:3000/api/chat');

            // Timeout increased from 15s to 60s to handle peak AI queue latency
            $http = Http::timeout(60);

            // The Node service is publicly reachable, so it only answers callers
            // holding this shared secret (chatbot-server/lib/http-guard.js). Unset
            // means not enforced on either side; it must be set on both at once.
            $token = (string) config('services.chatbot.token');
            if ($token !== '') {
                $http = $http->withToken($token);
            }

            $response = $http->post($nodeApiUrl, [
                'message' => $request->message,
                'shown' => $request->input('shown', []),
                'last_query' => $request->input('last_query'),
            ]);

            if ($response->successful()) {
                $botReply = $response->json('response') ?? $response->json('message') ?? self::UNAVAILABLE_MESSAGE;

                // Recommendation replies also say which products they named, the query
                // to continue from, and follow-up chips; the widget stores and renders them.
                $extras = array_filter([
                    'products' => $response->json('products'),
                    'query' => $response->json('query'),
                    'chips' => $response->json('chips'),
                ], fn ($value) => is_array($value) ? $value !== [] : is_string($value) && $value !== '');
            } else {
                // A 401 here is a configuration fault, not a provider outage: the
                // two services disagree about CHATBOT_SERVICE_TOKEN. Named in the
                // log so it isn't mistaken for "the AI is down".
                Log::error($response->status() === 401
                    ? 'Chatbot rejected this app\'s service token: CHATBOT_SERVICE_TOKEN differs between the Laravel app and the chatbot service.'
                    : 'Chatbot service returned an error status.', [
                        'status' => $response->status(),
                    ]);

                $botReply = self::UNAVAILABLE_MESSAGE;
            }
        } catch (Throwable $e) {
            report($e);

            $botReply = self::UNAVAILABLE_MESSAGE;
        }

        return response()->json(['response' => $botReply] + $extras);
    }
}
