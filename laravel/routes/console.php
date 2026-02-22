<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Attempt;
use App\Services\LxdManager;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    $expiredAttempts = Attempt::with('attemptNodes')
        ->where('status', 'running')
        ->where('ends_at', '<', now())
        ->get();

    $lxd = new LxdManager();

    foreach ($expiredAttempts as $attempt) {
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
            $errorJson = ['error' => 'Grader node not found'];
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

        $attempt->update(['status' => 'expired']);
    }
})->everyMinute();
