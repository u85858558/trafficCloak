<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:dns-lookup')]
class DnsLookupCommand extends Command
{
    protected static string $defaultName = 'app:dns-lookup';

    private string $dataDir;

    public function __construct(string $dataDir)
    {
        $this->dataDir = $dataDir;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Perform DNS lookup for a random site from a CSV file.')
            ->setHelp('This command reads a random line from the top-1m.csv file and resolves the domain to an IP address.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $csvFile = $this->dataDir . '/top-1m.csv';

        if (!file_exists($csvFile) || !is_readable($csvFile)) {
            $io->error('The CSV file could not be found or is not readable.');
            return Command::FAILURE;
        }

        $site = $this->getRandomDomain($csvFile);

        if ($site === null) {
            $io->warning('No valid domain found in the file.');
            return Command::FAILURE;
        }

        try {
            $resolvedIp = gethostbyname($site);

            if ($resolvedIp === $site) {
                $io->warning(sprintf('Failed to resolve %s', $site));
            } else {
                $io->success(sprintf('%s resolved to %s', $site, $resolvedIp));
            }
        } catch (\Exception $e) {
            $io->error(sprintf('Error resolving %s: %s', $site, $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function getRandomDomain(string $filePath): ?string
    {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (!$lines) {
            return null;
        }

        $randomLine = $lines[array_rand($lines)];
        $parts = explode(',', $randomLine);

        return $parts[1] ?? null;
    }
}