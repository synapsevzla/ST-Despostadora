<?php
declare(strict_types=1);

namespace Src\Auth;

final class RateLimiter
{
    private static string $cacheDir = '';

    private static function init(): void
    {
        if (self::$cacheDir === '') {
            self::$cacheDir = sys_get_temp_dir() . '/deboning_rate_limit';
            if (!is_dir(self::$cacheDir)) {
                @mkdir(self::$cacheDir, 0700, true);
            }
        }
    }

    public static function check(string $identifier, int $maxAttempts = 5, int $decaySeconds = 300): bool
    {
        self::init();
        $file = self::$cacheDir . '/' . md5($identifier) . '.json';
        $now = time();

        if (!file_exists($file)) {
            return true;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            return true;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return true;
        }

        if ($now - ($data['time'] ?? 0) > $decaySeconds) {
            @unlink($file);
            return true;
        }

        return (($data['attempts'] ?? 0) < $maxAttempts);
    }

    public static function hit(string $identifier): void
    {
        self::init();
        $file = self::$cacheDir . '/' . md5($identifier) . '.json';
        $now = time();
        $attempts = 1;

        if (file_exists($file)) {
            $content = @file_get_contents($file);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (is_array($data) && ($now - ($data['time'] ?? 0) <= 300)) {
                    $attempts = (int)($data['attempts'] ?? 0) + 1;
                }
            }
        }

        @file_put_contents($file, json_encode(['attempts' => $attempts, 'time' => $now]), LOCK_EX);
    }

    public static function clear(string $identifier): void
    {
        self::init();
        $file = self::$cacheDir . '/' . md5($identifier) . '.json';
        if (file_exists($file)) {
            @unlink($file);
        }
    }
}
