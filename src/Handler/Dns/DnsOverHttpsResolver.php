<?php

declare(strict_types=1);

namespace App\Handler\Dns;

use App\Contract\DnsResolverInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

final class DnsOverHttpsResolver implements DnsResolverInterface
{
    final const TYPE_CODE_A = 1;
    final const TEPE_CODE_AAAA = 28;

    private const DEFAULT_ENDPOINT = 'https://dns.google/resolve';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ClientInterface $http = new Client(['timeout' => 5.0]),
        private readonly string $endpoint = self::DEFAULT_ENDPOINT,
    ) {
    }

    public function resolveA(string $hostname): array
    {
        $hostname = $this->normalizeHost($hostname);
        $answers = $this->query($hostname, 'A');
        return $this->extractRecords($answers, self::TYPE_CODE_A);
    }

    public function resolveAAAA(string $hostname): array
    {
        $hostname = $this->normalizeHost($hostname);
        $answers = $this->query($hostname, 'AAAA');
        return $this->extractRecords($answers, self::TEPE_CODE_AAAA);
    }

    public function resolve(string $hostname, array $types = ['A', 'AAAA']): array
    {
        $hostname = $this->normalizeHost($hostname);
        $out = [];

        foreach ($types as $type) {
            if ($type === 'A') {
                $out['A'] = $this->extractRecords($this->query($hostname, 'A'), self::TYPE_CODE_A);
            } elseif ($type === 'AAAA') {
                $out['AAAA'] = $this->extractRecords($this->query($hostname, 'AAAA'), self::TEPE_CODE_AAAA);
            }
        }

        return $out;
    }

    private function query(string $name, string $type): array
    {
        try {
            $resp = $this->http->request('GET', $this->endpoint, [
                'query' => ['name' => $name, 'type' => $type],
                'headers' => ['Accept' => 'application/json'],
            ]);

            $json = json_decode((string) $resp->getBody(), true, flags: JSON_THROW_ON_ERROR);

            if (!isset($json['Status']) || (int) $json['Status'] !== 0) {
                $code = $json['Status'] ?? 'unknown';
                $this->logger->debug("DoH response non-zero Status={$code} for {$name} {$type}");
                return [];
            }

            return $json['Answer'] ?? [];
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf('DoH query failed for %s %s: %s', $name, $type, $e->getMessage()));
            return [];
        }
    }

    private function extractRecords(array $answers, int $typeCode): array
    {
        $out = [];
        foreach ($answers as $ans) {
            if (($ans['type'] ?? null) === $typeCode && isset($ans['data'])) {
                $out[] = trim((string) $ans['data']);
            }
        }
        return $out;
    }

    private function normalizeHost(string $host): string
    {
        $host = trim($host);
        if (preg_match('~^https?://~i', $host)) {
            $parsed = parse_url($host);
            if (isset($parsed['host'])) {
                $host = $parsed['host'];
            }
        }
        return rtrim(strtolower($host), '.');
    }
}