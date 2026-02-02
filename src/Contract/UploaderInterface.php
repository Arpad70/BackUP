<?php
declare(strict_types=1);
namespace BackupApp\Contract;

interface UploaderInterface
{
    /**
     * Upload a local file to remote target
     *
     * @return array<string,mixed>
     */
    public function upload(string $local, string $remote, string $host, int $port, string $user, string $pass): array;
}
