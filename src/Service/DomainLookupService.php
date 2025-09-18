<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\DnsResolverInterface;
use Psr\Log\LoggerInterface;

class DomainLookupService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly DnsResolverInterface $resolver,
    ) {}

    public function lookupDomains(string $csvFile): void
    {
        $lines = file($csvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (! $lines) {
            $this->logger->error('Failed to read from CSV file.');
            return;
        }

        $randomLine = $lines[array_rand($lines)];
        [$id, $site] = explode(',', $randomLine);

        try {
            $site = trim($site);
            $ips = $this->resolver->resolveA($site);

            if ($ips === []) {
                throw new \Exception('DoH A resolution returned no records');
            }

            $this->logger->info(sprintf('%s resolved to %s', $site, implode(', ', $ips)));
        } catch (\Exception $e) {
            $this->logger->warning(sprintf('Failed to resolve %s: %s', $site, $e->getMessage()));
        }
    }
}
