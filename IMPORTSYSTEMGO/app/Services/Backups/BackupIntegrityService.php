<?php

namespace App\Services\Backups;

use App\Models\BackupArchive;
use RuntimeException;
use ZipArchive;

class BackupIntegrityService
{
    public function verify(BackupArchive $archive): bool
    {
        $path = storage_path('app/private/'.$archive->storage_path);
        $root = realpath(storage_path('app/private/'.config('backups.directory')));
        $realPath = realpath($path);
        if (! $root || ! $realPath || ! str_starts_with($realPath, $root.DIRECTORY_SEPARATOR) || ! is_file($realPath)) {
            return false;
        }
        if (! hash_equals((string) $archive->sha256, (string) hash_file('sha256', $realPath))) {
            return false;
        }

        $zip = new ZipArchive;
        if ($zip->open($realPath, ZipArchive::CHECKCONS) !== true) {
            return false;
        }

        try {
            $checksumContent = $zip->getFromName('SHA256SUMS.txt');
            $manifestContent = $zip->getFromName('manifest.json');
            if (! is_string($checksumContent) || ! is_string($manifestContent)) {
                return false;
            }
            $manifest = json_decode($manifestContent, true, flags: JSON_THROW_ON_ERROR);
            if (($manifest['application'] ?? null) !== 'IMPORTSYSTEMGO' || ($manifest['kind'] ?? null) !== $archive->kind) {
                return false;
            }

            $checksums = [];
            foreach (preg_split('/\R/', trim($checksumContent)) ?: [] as $line) {
                if (preg_match('/^([a-f0-9]{64})  ([A-Za-z0-9_.-]+)$/', $line, $matches) !== 1) {
                    return false;
                }
                $checksums[$matches[2]] = $matches[1];
            }
            if (($checksums['manifest.json'] ?? null) !== hash('sha256', $manifestContent)) {
                return false;
            }

            foreach ($checksums as $name => $expected) {
                if ($name === 'manifest.json') {
                    continue;
                }
                $stream = $zip->getStream($name);
                if ($stream === false) {
                    return false;
                }
                $context = hash_init('sha256');
                hash_update_stream($context, $stream);
                fclose($stream);
                if (! hash_equals($expected, hash_final($context))) {
                    return false;
                }
            }

            return isset($checksums[$archive->kind === 'full' ? 'database.sql' : 'branches.csv']);
        } catch (\JsonException|RuntimeException) {
            return false;
        } finally {
            $zip->close();
        }
    }
}
