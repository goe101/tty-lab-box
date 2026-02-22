<?php

namespace App\Http\Controllers;

use App\Models\Lab;
use App\Models\Attempt;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $labs = Lab::where('published', true)->get();

        $userId = auth()->id() ?? 1;

        $attempts = Attempt::where('user_id', $userId)
            ->with('result')
            ->orderByDesc('id')
            ->get();

        $latestAttempts = [];
        foreach ($attempts as $attempt) {
            if (!isset($latestAttempts[$attempt->lab_id])) {
                $latestAttempts[$attempt->lab_id] = $attempt;
            }
        }

        return view('home', compact('labs', 'latestAttempts'));
    }
}
