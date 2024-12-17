<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\DomainLookupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;

#[AsCommand(name: 'app:main')]
class MainCommand extends Command
{
    protected static string $defaultName = 'app:main';

    private DomainLookupService $lookupService;

    public function __construct(DomainLookupService $lookupService)
    {
        $this->lookupService = $lookupService;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Start the TrafficCloak service.')
            ->addOption('datadir', null, InputOption::VALUE_REQUIRED, 'Data directory', getcwd() . '/data')
            ->addOption('daemon', 'd', InputOption::VALUE_NONE, 'Run as a daemon')
            ->addOption('verbose', 'v', InputOption::VALUE_NONE, 'Increase logging')
            ->addOption('logfile', null, InputOption::VALUE_REQUIRED, 'Log to this file. Default is stdout.', null)
            ->addOption('pidfile', null, InputOption::VALUE_REQUIRED, 'Save process PID to this file.', '/tmp/traffic.pid');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logfile = $input->getOption('logfile') ?? 'php://stdout';
        $pidfile = $input->getOption('pidfile');
        $isDaemon = $input->getOption('daemon');
        $datadir = $input->getOption('datadir');
        $verbose = $input->getOption('verbose');

        $logger = new ConsoleLogger($output);

        if ($isDaemon) {
            $logger->info('Running in daemon mode...');
            $this->daemonize($logger, $logfile, $pidfile, $datadir);
        } else {
            $logger->info('Running in normal mode...');
            $this->startService($logger, $datadir);
        }

        return Command::SUCCESS;
    }

    private function daemonize($logger, $logfile, $pidfile, $datadir)
    {
        if (file_exists($pidfile)) {
            $logger->error('Daemon is already running. PID file exists.');
            return;
        }

        $pid = pcntl_fork();
        if ($pid === -1) {
            $logger->error('Could not fork the process.');
            return;
        }

        if ($pid) {
            file_put_contents($pidfile, $pid);
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

        $this->startService($logger, $datadir);
    }

    private function startService($logger, $datadir)
    {
        $csvFile = $datadir . '/top-1m.csv';

        if (!file_exists($csvFile)) {
            $logger->error("CSV file not found at: {$csvFile}");
            return;
        }

        while (true) {
            try {
                $this->lookupService->lookupDomains($csvFile);
                $logger->info('Task executed. Sleeping for 60 seconds...');
                sleep(60);
            } catch (\Exception $e) {
                $logger->error('Error occurred: ' . $e->getMessage());
            }
        }
    }
}