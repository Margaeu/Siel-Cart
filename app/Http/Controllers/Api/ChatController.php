<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatController extends Controller
{
    public const MAX_MESSAGE_LENGTH = 2000;

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
            $response = Http::timeout(60)->post($nodeApiUrl, [
                'message' => $request->message,
                'shown' => $request->input('shown', []),
                'last_query' => $request->input('last_query'),
            ]);

            if ($response->successful()) {
                $botReply = $response->json('response') ?? $response->json('message') ?? 'No response key returned from AI.';

                // Recommendation replies also say which products they named, the query
                // to continue from, and follow-up chips; the widget stores and renders them.
                $extras = array_filter([
                    'products' => $response->json('products'),
                    'query' => $response->json('query'),
                    'chips' => $response->json('chips'),
                ], fn ($value) => is_array($value) ? $value !== [] : is_string($value) && $value !== '');
            } else {
                $botReply = 'AI Service Error Status: ' . $response->status();
            }
        } catch (\Exception $e) {
            $botReply = 'AI Connection Failed: ' . $e->getMessage();
        }

        return response()->json(['response' => $botReply] + $extras);
    }
}