<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

final class MainService
{
    public function __construct(
        private readonly DomainLookupService $domainLookupService,
        private readonly GoogleSearchService $googleSearchService,
        private readonly WikipediaSearchService $wikipediaSearchService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Facade entry: execute all actions in sequence.
     */
    public function runAll(string $dataDir): void
    {
        $this->logger->info('Facade: starting all actions');

        $this->runDomainLookup($dataDir);
        $this->runGoogleSearch();
        $this->runWikipediaCrawl();

        $this->logger->info('Facade: all actions finished');
    }

    /**
     * Facade: DNS lookup against CSV in dataDir.
     */
    public function runDomainLookup(string $dataDir): void
    {
        $csvFile = rtrim($dataDir, '/').'/top-1m.csv';

        if (! file_exists($csvFile)) {
            $this->logger->warning("CSV file not found at: {$csvFile}");
            return;
        }

        try {
            $this->domainLookupService->lookupDomains($csvFile);
        } catch (\Throwable $e) {
            $this->logger->error('Domain lookup failed: ' . $e->getMessage());
        }
    }

    /**
     * Facade: Execute the Google search flow.
     */
    public function runGoogleSearch(): void
    {
        try {
            $this->googleSearchService->search();
        } catch (\Throwable $e) {
            $this->logger->error('Google search failed: ' . $e->getMessage());
        }
    }

    /**
     * Facade: Execute the Wikipedia crawl flow with optional parameters.
     */
    public function runWikipediaCrawl(?int $maxDepth = null, ?int $maxLinksPerPage = null): void
    {
        try {
            if ($maxDepth !== null) {
                $this->wikipediaSearchService->setMaxDepth($maxDepth);
            }
            if ($maxLinksPerPage !== null) {
                $this->wikipediaSearchService->setMaxLinksPerPage($maxLinksPerPage);
            }
            $this->wikipediaSearchService->crawl();
        } catch (\Throwable $e) {
            $this->logger->error('Wikipedia crawl failed: ' . $e->getMessage());
        }
    }

    /**
     * Backward compatible alias for the previous API.
     */
    public function launchAll(string $dataDir): void
    {
        $this->runAll($dataDir);
    }
}