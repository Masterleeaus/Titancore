<?php

namespace Modules\TitanCore\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;

/**
 * Logs API — /api/v1/logs/*
 *
 * Provides read-only access to platform log files.
 * Only files within the configured Laravel log path are accessible.
 * No log modification or deletion is permitted via this API.
 */
class LogsController extends Controller
{
    /** Maximum number of lines to return in a single tail request. */
    private const MAX_LINES = 500;

    /**
     * GET /api/v1/logs
     *
     * List available log files with size and modification time.
     */
    public function index(): JsonResponse
    {
        $logPath = storage_path('logs');
        $files   = [];

        if (is_dir($logPath)) {
            foreach (File::files($logPath) as $file) {
                if ($file->getExtension() !== 'log') {
                    continue;
                }
                $files[] = [
                    'name'          => $file->getFilename(),
                    'size_bytes'    => $file->getSize(),
                    'modified_at'   => date('c', $file->getMTime()),
                ];
            }
            usort($files, fn ($a, $b) => strcmp($b['modified_at'], $a['modified_at']));
        }

        return response()->json([
            'data'  => $files,
            'total' => count($files),
            'path'  => $logPath,
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/logs/app
     *
     * Return the tail of the primary application log (laravel.log).
     */
    public function app(Request $request): JsonResponse
    {
        return $this->tailLog('laravel.log', $request);
    }

    /**
     * GET /api/v1/logs/platform
     *
     * Return the tail of the platform log channel if configured,
     * otherwise falls back to the main application log.
     */
    public function platform(Request $request): JsonResponse
    {
        $channel = config('logging.channels.platform.path');
        if ($channel && is_file($channel)) {
            $filename = basename($channel);
            $dir      = dirname($channel);
            return $this->tailLogFromPath($dir, $filename, $request);
        }

        return $this->tailLog('laravel.log', $request);
    }

    /**
     * GET /api/v1/logs/{filename}
     *
     * Return the tail of a specific log file by filename.
     * Only files within storage/logs are accessible.
     */
    public function show(Request $request, string $filename): JsonResponse
    {
        // Sanitise: only allow simple filenames (alphanumeric, underscores, hyphens) with .log extension
        if (! preg_match('/^[a-zA-Z0-9_-]+\.log$/', $filename)) {
            return response()->json(['error' => 'Invalid log filename'], 422);
        }

        return $this->tailLog($filename, $request);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function tailLog(string $filename, Request $request): JsonResponse
    {
        return $this->tailLogFromPath(storage_path('logs'), $filename, $request);
    }

    private function tailLogFromPath(string $dir, string $filename, Request $request): JsonResponse
    {
        $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        // Ensure path stays within the expected directory
        $realDir  = realpath($dir) ?: $dir;
        $realPath = realpath($path);

        if ($realPath === false || ! str_starts_with($realPath, $realDir . DIRECTORY_SEPARATOR)) {
            return response()->json(['error' => 'Log file not found or access denied'], 404);
        }

        if (! is_file($realPath)) {
            return response()->json(['error' => 'Log file not found'], 404);
        }

        $lines = (int) $request->query('lines', 100);
        $lines = max(1, min($lines, self::MAX_LINES));

        $content = $this->readTail($realPath, $lines);

        return response()->json([
            'file'  => $filename,
            'lines' => count($content),
            'data'  => $content,
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * Read the last $n lines from a file efficiently.
     *
     * @return string[]
     */
    private function readTail(string $path, int $n): array
    {
        $fp = fopen($path, 'rb');
        if (! $fp) {
            return [];
        }

        fseek($fp, 0, SEEK_END);
        $size   = ftell($fp);
        $chunk  = 4096;
        $buffer = '';
        $found  = 0;
        $pos    = $size;

        while ($pos > 0 && $found <= $n) {
            $readSize = min($chunk, $pos);
            $pos     -= $readSize;
            fseek($fp, $pos);
            $buffer = fread($fp, $readSize) . $buffer;
            $found  = substr_count($buffer, "\n");
        }

        fclose($fp);

        $allLines = explode("\n", $buffer);
        // Remove empty trailing element if file ends with newline
        if (end($allLines) === '') {
            array_pop($allLines);
        }

        return array_slice($allLines, -$n);
    }
}
