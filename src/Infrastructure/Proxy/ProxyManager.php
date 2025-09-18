<?php

declare(strict_types=1);

namespace App\Infrastructure\Proxy;

use Psr\Log\LoggerInterface;

final class ProxyManager
{
    /** @var array<int, array<string, string|int>> */
    private array $proxies = [];
    private int $idx = 0;

    public function __construct(private readonly LoggerInterface $logger)
    {
        $this->loadProxies();
    }

    public function getNextProxy(): ?array
    {
        if ($this->proxies === []) {
            return null;
        }
        $proxy = $this->proxies[$this->idx % count($this->proxies)];
        $this->idx++;
        return $proxy;
    }

    private function loadProxies(): void
    {
        $rawEnv = trim((string) ($_SERVER['PROXIES'] ?? ''));
        $file = (string) ($_SERVER['PROXIES_FILE'] ?? 'data/proxies.txt');

        $lines = [];
        if ($rawEnv !== '') {
            $lines = array_map('trim', explode(',', $rawEnv));
        } elseif (is_file($file)) {
            $lines = array_map('trim', file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
        }

        foreach ($lines as $line) {
            $cfg = $this->parseProxyUri($line);
            if ($cfg !== null) {
                $this->proxies[] = $cfg;
            }
        }

        if ($this->proxies === []) {
            $this->logger->info('ProxyManager: no proxies configured (running without proxy)');
        } else {
            $this->logger->info('ProxyManager: loaded ' . count($this->proxies) . ' proxies');
        }
    }

    private function parseProxyUri(string $uri): ?array
    {
        $parts = @parse_url($uri);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'], $parts['port'])) {
            $this->logger->warning('ProxyManager: invalid proxy URI skipped: ' . $uri);
            return null;
        }

        $type = strtolower($parts['scheme']);
        if (!in_array($type, ['http', 'https', 'socks5'], true)) {
            $this->logger->warning('ProxyManager: unsupported proxy scheme skipped: ' . $uri);
            return null;
        }

        $cfg = [
            'type' => $type,
            'host' => $parts['host'],
            'port' => (int) $parts['port'],
        ];
        if (isset($parts['user'])) {
            $cfg['username'] = $parts['user'];
        }
        if (isset($parts['pass'])) {
            $cfg['password'] = $parts['pass'];
        }

        return $cfg;
    }
}