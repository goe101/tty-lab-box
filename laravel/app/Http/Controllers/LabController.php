<?php

namespace App\Http\Controllers;

use App\Models\Lab;
use App\Models\Attempt;
use Illuminate\Http\Request;

class LabController extends Controller
{
    public function show($slug)
    {
        $lab = Lab::where('slug', $slug)->with(['steps' => fn($q) => $q->orderBy('order')], 'nodes')->firstOrFail();

        $userId = auth()->id() ?? 1;
        $activeAttempt = Attempt::where('user_id', $userId)
            ->where('lab_id', $lab->id)
            ->where('status', 'running')
            ->first();

        return view('lab', compact('lab', 'activeAttempt'));
    }
}
