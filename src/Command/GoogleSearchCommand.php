<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\GoogleSearchService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:google:search')]
class GoogleSearchCommand extends Command
{
    private GoogleSearchService $service;

    public function __construct(GoogleSearchService $searchService)
    {
        $this->service = $searchService;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->service->search();
            $output->writeln('<info>Google search completed successfully.</info>');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}