<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\DomainLookupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:main')]
class MainCommand extends Command
{
    protected static string $defaultName = 'app:main';

    public function __construct(private readonly DomainLookupService $lookupService)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Start the TrafficCloak service.')
            ->addOption('datadir', null, InputOption::VALUE_REQUIRED, 'Data directory', getcwd() . '/data')
            ->addOption('daemon', 'd', InputOption::VALUE_NONE, 'Run as a daemon')
            ->addOption('logfile', null, InputOption::VALUE_REQUIRED, 'Log to this file. Default is stdout.', null)
            ->addOption('pidfile', null, InputOption::VALUE_REQUIRED, 'Save process PID to this file.', '/tmp/traffic.pid');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logfile = $input->getOption('logfile') ?? 'php://stdout';
        $pidFile = $input->getOption('pidfile');
        $isDaemon = $input->getOption('daemon');
        $dataDir = $input->getOption('datadir');

        $logger = new ConsoleLogger($output);

        if ($isDaemon) {
            $logger->info('Running in daemon mode...');
            $this->daemonize($logger, $logfile, $pidFile, $dataDir);
        } else {
            $logger->info('Running in normal mode...');
            $this->startService($logger, $dataDir);
        }

        return Command::SUCCESS;
    }

    private function daemonize(\Symfony\Component\Console\Logger\ConsoleLogger $logger, $logfile, $pidFile, $dataDir): void
    {
        if (file_exists($pidFile)) {
            $logger->error('Daemon is already running. PID file exists.');
            return;
        }

        $pid = pcntl_fork();
        if ($pid === -1) {
            $logger->error('Could not fork the process.');
            return;
        }

        if ($pid !== 0) {
            file_put_contents($pidFile, $pid);
            exit(0);
        }

        // Detach from terminal
        posix_setsid();

        // Redirect output to logfile
        $logStream = fopen($logfile, 'a');
        if ($logStream) {
            fclose(STDOUT);
            fclose(STDERR);
            fopen($logfile, 'a');
        }

        $this->startService($logger, $dataDir);
    }

    private function startService($logger, string $dataDir): void
    {
        $csvFile = $dataDir . '/top-1m.csv';

        if (! file_exists($csvFile)) {
            $logger->error("CSV file not found at: {$csvFile}");
            return;
        }

        while (true) {
            try {
                $this->lookupService->lookupDomains($csvFile);
                $logger->info('Task executed. Sleeping for 60 seconds...');
                sleep(3);
            } catch (\Exception $e) {
                $logger->error('Error occurred: ' . $e->getMessage());
            }
        }
    }
}
