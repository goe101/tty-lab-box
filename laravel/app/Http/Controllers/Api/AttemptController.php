<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\Lab;
use App\Services\LxdManager;
use Illuminate\Http\Request;

class AttemptController extends Controller
{
    public function start(Request $request)
    {
        $request->validate(['lab_id' => 'required|exists:labs,id']);

        $lab = Lab::with('nodes')->findOrFail($request->lab_id);

        $userId = auth()->id() ?? 1;

        $attempt = Attempt::create([
            'user_id' => $userId,
            'lab_id' => $lab->id,
            'status' => 'running',
            'started_at' => now(),
            'ends_at' => now()->addMinutes($lab->duration),
        ]);

        $lxd = new LxdManager();
        $nodes = [];

        foreach ($lab->nodes as $node) {
            $instanceName = 'att_' . $attempt->id . '__' . $node->node_name;

            $opts = ['image' => $node->image];
            $lxd->createVm($instanceName, $opts);

            $attempt->attemptNodes()->create([
                'node_name' => $node->node_name,
                'instance_name' => $instanceName,
            ]);

            $nodes[] = [
                'node_name' => $node->node_name,
                'instance_name' => $instanceName,
            ];
        }

        return response()->json([
            'attemptId' => $attempt->id,
            'endsAt' => $attempt->ends_at,
            'nodes' => $nodes,
        ]);
    }

    public function stop(Request $request)
    {
        $request->validate(['attempt_id' => 'required|exists:attempts,id']);

        $attempt = Attempt::with('attemptNodes')->findOrFail($request->attempt_id);

        $lxd = new LxdManager();
        foreach ($attempt->attemptNodes as $node) {
            if ($lxd->exists($node->instance_name)) {
                $lxd->deleteVm($node->instance_name, true);
            }
        }

        $attempt->update(['status' => 'stopped']);

        return response()->json(['success' => true]);
    }

    public function submit(Request $request)
    {
        $request->validate(['attempt_id' => 'required|exists:attempts,id']);

        $attempt = Attempt::with('attemptNodes', 'lab')->findOrFail($request->attempt_id);
        $lxd = new LxdManager();

        $graderNode = $attempt->attemptNodes->firstWhere('node_name', 'srv1') ?? $attempt->attemptNodes->first();

        $score = 0;
        $errorJson = null;

        if ($graderNode && $lxd->exists($graderNode->instance_name)) {
            try {
                $res = $lxd->exec($graderNode->instance_name, ['/usr/local/bin/grade.sh']);
                $output = json_decode($res['stdout'], true);
                if (isset($output['score'])) {
                    $score = $output['score'];
                } else {
                    $errorJson = ['error' => 'Invalid JSON from grader', 'raw' => $res['stdout']];
                }
            } catch (\Exception $e) {
                $errorJson = ['error' => $e->getMessage()];
            }
        } else {
            $errorJson = ['error' => 'Grader node not found or not running'];
        }

        $attempt->result()->updateOrCreate(
            ['attempt_id' => $attempt->id],
            ['score' => $score, 'error_json' => $errorJson]
        );

        foreach ($attempt->attemptNodes as $node) {
            if ($lxd->exists($node->instance_name)) {
                $lxd->deleteVm($node->instance_name, true);
            }
        }

        $attempt->update(['status' => 'submitted']);

        return response()->json([
            'success' => true,
            'score' => $score,
            'error' => $errorJson
        ]);
    }
}
