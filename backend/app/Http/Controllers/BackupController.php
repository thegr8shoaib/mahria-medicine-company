<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    public function avatar(Request $request, string $file)
    {
        $file = basename($file);
        $path = storage_path('app/avatars/' . $file);

        if (! is_file($path)) {
            return response()->json(['message' => 'Avatar not found.'], 404);
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg');

        return response()->file($path, ['Content-Type' => $mime]);
    }

    public function run(): JsonResponse
    {
        $backupDir = storage_path('app/backups');
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0777, true);
        }

        $stamp = date('Ymd-His');
        $folder = $backupDir . '/mahria-' . $stamp;

        try {
            $dbPath = database_path('database.sqlite');
            if (! is_file($dbPath)) {
                throw new \RuntimeException('database.sqlite not found.');
            }

            mkdir($folder, 0777, true);
            $pdo = new \PDO('sqlite:' . $dbPath);
            $target = $folder . '/database.sqlite';
            $pdo->exec("VACUUM INTO '" . str_replace("'", "''", $target) . "'");

            foreach (['avatars', 'QR.png', 'thermal-logo.png'] as $item) {
                $src = storage_path('app/' . $item);
                if (is_file($src)) {
                    copy($src, $folder . '/' . $item);
                } elseif (is_dir($src)) {
                    $this->copyDir($src, $folder . '/' . $item);
                }
            }

            $this->prune($backupDir);

            $dbCopy = $folder . '/database.sqlite';

            return response()->json([
                'message' => 'Backup created.',
                'file' => 'mahria-' . $stamp . '/database.sqlite',
                'size' => filesize($dbCopy),
                'folder' => basename($folder),
            ]);
        } catch (\Throwable $e) {
            Log::error('Backup failed: ' . $e->getMessage());

            return response()->json(['message' => 'Backup failed: ' . $e->getMessage()], 500);
        }
    }

    public function download(Request $request, string $path)
    {
        if (! preg_match('/^mahria-[\d-]+\/database\.sqlite$/', $path)) {
            return response()->json(['message' => 'Invalid backup file.'], 404);
        }

        $file = storage_path('app/backups/' . str_replace('/', DIRECTORY_SEPARATOR, $path));

        if (! is_file($file)) {
            return response()->json(['message' => 'Backup not found.'], 404);
        }

        return response()->download($file, 'mahria-backup-' . date('Ymd-His') . '.sqlite', [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    private function copyDir(string $src, string $dst): void
    {
        if (! is_dir($dst)) {
            mkdir($dst, 0777, true);
        }
        foreach (scandir($src) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $from = $src . DIRECTORY_SEPARATOR . $entry;
            if (is_file($from)) {
                copy($from, $dst . DIRECTORY_SEPARATOR . $entry);
            }
        }
    }

    private function prune(string $backupDir): void
    {
        $folders = array_values(array_filter(glob($backupDir . '/mahria-*'), 'is_dir'));
        rsort($folders);
        foreach (array_slice($folders, 10) as $old) {
            $this->rmDir($old);
        }
    }

    private function rmDir(string $dir): void
    {
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            is_dir($path) ? $this->rmDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}