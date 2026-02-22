<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use App\Models\Attempt;

class TerminalController extends Controller
{
    public function token(Request $request)
    {
        $request->validate([
            'attempt_id' => 'required|exists:attempts,id',
            'node_name' => 'required|string',
        ]);

        $attempt = Attempt::with('attemptNodes')->findOrFail($request->attempt_id);

        if ($attempt->status !== 'running') {
            return response()->json(['error' => 'Attempt is not running'], 403);
        }

        $userId = auth()->id() ?? 1;
        if ($attempt->user_id !== $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $node = $attempt->attemptNodes->firstWhere('node_name', $request->node_name);
        if (!$node) {
            return response()->json(['error' => 'Node not found in this attempt'], 404);
        }

        $secret = env('TERMINAL_JWT_SECRET', 'change-me');
        $payload = [
            'iss' => env('APP_URL'),
            'iat' => time(),
            'exp' => time() + 300,
            'userId' => $userId,
            'attemptId' => $attempt->id,
            'nodeName' => $request->node_name,
            'instanceName' => $node->instance_name,
        ];

        $token = JWT::encode($payload, $secret, 'HS256');

        return response()->json([
            'wsUrl' => env('TERMINAL_WS_URL', 'ws://127.0.0.1:8081/ws'),
            'token' => $token,
        ]);
    }
}
