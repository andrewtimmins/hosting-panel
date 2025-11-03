<?php
namespace App\Support;

use RuntimeException;

class CommandRunner
{
    public function run(string $command, int $timeout = 30): array
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes);

        if (!\is_resource($process)) {
            throw new RuntimeException('Unable to start process for command: ' . $command);
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], true);
        stream_set_blocking($pipes[2], true);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $status = proc_get_status($process);
        $exitCode = $status['exitcode'];

        if ($status['running']) {
            $start = time();
            while ($status['running'] && time() - $start < $timeout) {
                usleep(200000);
                $status = proc_get_status($process);
            }
            if ($status['running']) {
                proc_terminate($process);
                $exitCode = 124;
                $stderr .= PHP_EOL . 'Command timed out after ' . $timeout . ' seconds.';
            } else {
                $exitCode = $status['exitcode'];
            }
        }

        proc_close($process);

        return [
            'exit_code' => $exitCode,
            'stdout' => trim((string) $stdout),
            'stderr' => trim((string) $stderr),
        ];
    }
}
