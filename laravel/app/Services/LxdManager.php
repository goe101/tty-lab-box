<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class LxdManager
{
    protected string $lxdBin;

    public function __construct()
    {
        $this->lxdBin = env('LXD_BIN', 'lxc');
    }

    /**
     * LXD instance names are strict. We normalize any provided name to:
     * - lowercase
     * - [a-z0-9-] only
     * - collapse multiple dashes
     * - trim dashes
     */
    protected function normalizeName(string $name): string
    {
        $name = strtolower($name);
        $name = str_replace('_', '-', $name);
        $name = preg_replace('/[^a-z0-9-]/', '-', $name) ?? $name;
        $name = preg_replace('/-+/', '-', $name) ?? $name;
        $name = trim($name, '-');

        // LXD doesn't accept empty names
        if ($name === '') {
            $name = 'vm';
        }

        // Keep it reasonably short (DNS-ish); avoids edge-case failures
        if (strlen($name) > 60) {
            $name = substr($name, 0, 60);
            $name = rtrim($name, '-');
        }

        return $name;
    }

    public function createVm(string $name, array $opts = []): void
    {
        $safeName = $this->normalizeName($name);
        $image = $opts['image'] ?? env('LAB_DEFAULT_IMAGE', 'images:rockylinux/9');

        // VM launch may take time (especially under VMware). Give it more time.
        $this->run([$this->lxdBin, 'launch', $image, $safeName, '--vm'], 300);
    }

    public function startVm(string $name): void
    {
        $safeName = $this->normalizeName($name);
        $this->run([$this->lxdBin, 'start', $safeName], 120);
    }

    public function exec(string $name, array $cmd, int $timeoutSec = 60): array
    {
        $safeName = $this->normalizeName($name);
        return $this->run(array_merge([$this->lxdBin, 'exec', $safeName, '--'], $cmd), $timeoutSec);
    }

    public function deleteVm(string $name, bool $force = true): void
    {
        $safeName = $this->normalizeName($name);

        $args = [$this->lxdBin, 'delete', $safeName];
        if ($force) {
            $args[] = '--force';
        }
        $this->run($args, 120);
    }

    public function getVmIp(string $name): ?string
    {
        $safeName = $this->normalizeName($name);

        $output = $this->run([$this->lxdBin, 'list', $safeName, '-c', '4', '--format', 'csv'], 60)['stdout'];
        $ip = trim(explode(' ', $output)[0] ?? '');
        return $ip !== '' ? $ip : null;
    }

    public function exists(string $name): bool
    {
        $safeName = $this->normalizeName($name);

        try {
            $this->run([$this->lxdBin, 'info', $safeName], 30);
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    protected function run(array $command, int $timeout = 60): array
    {
        $process = new Process($command);
        $process->setTimeout($timeout);
        $process->run();

        if (!$process->isSuccessful()) {
            $cmd = implode(' ', array_map(fn($c) => escapeshellarg((string)$c), $command));
            $out = trim($process->getOutput());
            $err = trim($process->getErrorOutput());

            throw new \RuntimeException(
                "Process Failed\nCMD: {$cmd}\nSTDOUT: {$out}\nSTDERR: {$err}"
            );
        }

        return [
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput(),
        ];
    }
}
