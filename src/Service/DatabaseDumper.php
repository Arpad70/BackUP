<?php
declare(strict_types=1);
namespace BackupApp\Service;

class DatabaseDumper
{
    /**
     * Dump a MySQL database to a local file using MYSQL_PWD env to avoid CLI password exposure.
     *
     * @return array<string,mixed>
     */
    public function dump(string $host, string $user, string $pass, string $name, int $port, string $outfile): array
    {
        $hostArg = escapeshellarg($host);
        $userArg = escapeshellarg($user);
        $nameArg = escapeshellarg($name);
        $cmd = "mysqldump --host={$hostArg} --port={$port} --user={$userArg} --single-transaction --quick --routines --triggers {$nameArg}";

        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($cmd, $descriptors, $pipes, null, ['MYSQL_PWD' => $pass]);
        if (!is_resource($process)) {
            $msg = 'Failed to start mysqldump process';
            error_log('DatabaseDumper: ' . $msg . ' -- cmd: ' . $cmd);
            return ['ok' => false, 'message' => $msg];
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $rc = proc_close($process);

        $written = false;
        if ($stdout !== false) {
            $written = @file_put_contents($outfile, $stdout);
        }

        $outText = trim(($stderr !== false ? $stderr : '') . "\n" . ($stdout !== false ? $stdout : ''));

        if ($rc === 0 && $written !== false && file_exists($outfile)) {
            $msg = $outText ?: 'Dump created';
            return ['ok' => true, 'message' => $msg];
        }

        $msg = $outText ?: 'mysqldump failed with exit code ' . intval($rc);
        error_log('DatabaseDumper: ' . $msg . ' -- cmd: ' . $cmd);
        return ['ok' => false, 'message' => $msg];
    }
}
