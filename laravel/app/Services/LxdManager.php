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

    public function createVm(string $name, array $opts = []): void
    {
        $image = $opts['image'] ?? env('LAB_DEFAULT_IMAGE', 'images:rockylinux/9');
        $this->run([$this->lxdBin, 'launch', $image, $name, '--vm']);
    }

    public function startVm(string $name): void
    {
        $this->run([$this->lxdBin, 'start', $name]);
    }

    public function exec(string $name, array $cmd, int $timeoutSec = 60): array
    {
        return $this->run(array_merge([$this->lxdBin, 'exec', $name, '--'], $cmd), $timeoutSec);
    }

    public function deleteVm(string $name, bool $force = true): void
    {
        $args = [$this->lxdBin, 'delete', $name];
        if ($force) {
            $args[] = '--force';
        }
        $this->run($args);
    }

    public function getVmIp(string $name): ?string
    {
        $output = $this->run([$this->lxdBin, 'list', $name, '-c', '4', '--format', 'csv'])['stdout'];
        $ip = trim(explode(' ', $output)[0] ?? '');
        return $ip !== '' ? $ip : null;
    }

    public function exists(string $name): bool
    {
        try {
            $this->run([$this->lxdBin, 'info', $name]);
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
            throw new \RuntimeException("Process Failed: " . $process->getErrorOutput());
        }

        return [
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput(),
        ];
    }
}
