<?php

declare(strict_types=1);

namespace App\Contract;

interface DnsResolverInterface
{
    /**
     * Resolve IPv4 (A) records for a hostname.
     *
     * @return string[]
     */
    public function resolveA(string $hostname): array;

    /**
     * Resolve IPv6 (AAAA) records for a hostname.
     *
     * @return string[]
     */
    public function resolveAAAA(string $hostname): array;

    /**
     * Resolve multiple record types. Keys are type names (e.g., 'A', 'AAAA').
     *
     * @param string[] $types
     * @return array{A?: string[], AAAA?: string[]}
     */
    public function resolve(string $hostname, array $types = ['A', 'AAAA']): array;
}