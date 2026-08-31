<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        // 1. Resolve customer ID safely
        $authenticatedId = Auth::guard('customer')->check() 
            ? Auth::guard('customer')->id() 
            : Auth::id();

        $customerId = ($authenticatedId && User::where('id', $authenticatedId)->exists()) 
            ? $authenticatedId 
            : null;

        // Ensure session ID is never null
        $sessionId = session()->getId() ?: 'guest_' . uniqid();

        // 2. Save user message to database
        try {
            ChatMessage::create([
                'customer_id' => $customerId,
                'session_id'  => $sessionId,
                'sender'      => 'user',
                'message'     => $request->message,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to save user chat message: ' . $e->getMessage());
        }

        // 3. Query Express/Node AI Server
        try {
            $nodeApiUrl = config('app.chatbot_url', 'http://127.0.0.1:3000/api/chat');
            
            // Timeout increased from 15s to 60s to handle peak AI queue latency
            $response = Http::timeout(60)->post($nodeApiUrl, [
                'message' => $request->message,
            ]);

            if ($response->successful()) {
                $botReply = $response->json('response') ?? $response->json('message') ?? 'No response key returned from AI.';
            } else {
                $botReply = 'AI Service Error Status: ' . $response->status();
            }
        } catch (\Exception $e) {
            $botReply = 'AI Connection Failed: ' . $e->getMessage();
        }

        // 4. Save AI response to database
        try {
            ChatMessage::create([
                'customer_id' => $customerId,
                'session_id'  => $sessionId,
                'sender'      => 'bot',
                'message'     => $botReply,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to save bot chat message: ' . $e->getMessage());
        }

        return response()->json([
            'response' => $botReply,
        ]);
    }
}