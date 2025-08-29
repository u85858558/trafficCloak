<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

class DomainLookupService
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

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
            $host = gethostbyname($site);
            if ($host === $site) {
                throw new \Exception('DNS resolution failed');
            }
            $this->logger->info(sprintf('%s resolved to %s', $site, $host));
        } catch (\Exception $e) {
            $this->logger->warning(sprintf('Failed to resolve %s: %s', $site, $e->getMessage()));
        }
    }
}
