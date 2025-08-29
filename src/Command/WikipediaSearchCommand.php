<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\WikipediaSearchService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:wikipedia:crawl',
)]
class WikipediaSearchCommand extends Command
{
    public function __construct(
        private readonly WikipediaSearchService $wikipediaSearchService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('depth', 'd', InputOption::VALUE_OPTIONAL, 'Maximum crawl depth (number of links to follow)', 5)
            ->addOption('max-links', 'm', InputOption::VALUE_OPTIONAL, 'Maximum links to consider per page', 10)
            ->setHelp('This command starts from a random Wikipedia article and follows internal links naturally.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $depth = (int) $input->getOption('depth');
        $maxLinks = (int) $input->getOption('max-links');
        $io->title('Wikipedia Random Article Crawler');
        $io->info('Starting Wikipedia crawl with depth: ' . $depth . ', max links per page: ' . $maxLinks);

        try {
            $this->wikipediaSearchService->setMaxDepth($depth);
            $this->wikipediaSearchService->setMaxLinksPerPage($maxLinks);
            $this->wikipediaSearchService->crawl();
            $io->success('Wikipedia crawl completed successfully.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Wikipedia crawl failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
