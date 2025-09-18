<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\MainFacade;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Psr\Log\LoggerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * @psalm-api
 */
#[AsCommand(name: 'app:main')]
class MainCommand extends Command
{
    protected static string $defaultName = 'app:main';

    public function __construct(
        private readonly MainFacade $mainFacade,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Start the TrafficCloak service.')
            ->addOption('datadir', null, InputOption::VALUE_REQUIRED, 'Data directory', getcwd() . '/data')
            ->addOption('daemon', 'd', InputOption::VALUE_NONE, 'Run as a daemon')
            ->addOption('loop', 'l', InputOption::VALUE_NONE, 'Run in a continuous loop')
            ->addOption('pidfile', null, InputOption::VALUE_REQUIRED, 'Save process PID to this file.', '/tmp/traffic.pid')
            ->addOption('interval', 'i', InputOption::VALUE_REQUIRED, 'Sleep interval between cycles in seconds', '60')
            ->addOption('logfile', null, InputOption::VALUE_REQUIRED, 'Log file path', 'php://stdout');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $logfile  = $input->getOption('logfile') ?? 'php://stdout';
        $pidFile  = $input->getOption('pidfile');
        $isDaemon = $input->getOption('daemon');
        $isLoop   = $input->getOption('loop');
        $dataDir  = $input->getOption('datadir');
        $interval = (int) $input->getOption('interval');
    
        if ($logfile && $this->logger instanceof Logger) {
            $this->logger->pushHandler(new StreamHandler($logfile, Logger::INFO));
        }
    
        if ($isDaemon) {
            $io->note('Running in daemon mode...');
            $this->daemonize($io, $pidFile, $dataDir, $interval);
        } elseif ($isLoop) {
            $io->note('Running in continuous loop mode...');
            $shouldRun = true;
            $this->runLoop($dataDir, $interval, $shouldRun);
        } else {
            $io->info('Running a single cycle...');
            try {
                $this->mainFacade->runAll($dataDir);
                $io->success('Cycle completed');
            } catch (\Throwable $e) {
                $this->logger->error('Error occurred during execution: ' . $e->getMessage());
                $io->error('Execution failed: ' . $e->getMessage());
                return Command::FAILURE;
            }
        }
    
        return Command::SUCCESS;
    }

    private function daemonize(SymfonyStyle $io, string $pidFile, string $dataDir, int $interval): void
    {
        if (file_exists($pidFile)) {
            $io->error('Daemon is already running. PID file exists.');
            return;
        }

        $pid = pcntl_fork();
        if ($pid === -1) {
            $io->error('Could not fork the process.');
            return;
        }

        if ($pid !== 0) {
            file_put_contents($pidFile, (string) $pid);
            $io->writeln(sprintf('Daemon started with PID %d (PID file: %s)', $pid, $pidFile));
            return;
        }
        posix_setsid();

        $shouldRun = true;
        if (function_exists('pcntl_signal')) {
            if (defined('SIGTERM')) {
                pcntl_signal((int) constant('SIGTERM'), function () use (&$shouldRun) { $shouldRun = false; });
            }
            if (defined('SIGINT')) {
                pcntl_signal((int) constant('SIGINT'), function () use (&$shouldRun) { $shouldRun = false; });
            }
        }

        $this->runLoop($dataDir, $interval, $shouldRun);
    }

    private function runLoop(string $dataDir, int $interval, bool &$shouldRun): void
    {
        while ($shouldRun) {
            try {
                $this->mainFacade->runAll($dataDir);
                $this->logger->info(sprintf('Cycle executed. Sleeping for %d seconds...', $interval));
            } catch (\Throwable $e) {
                $this->logger->error('Error occurred: ' . $e->getMessage());
            }

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
            sleep(max(0, $interval));
        }

        $this->logger->info('Daemon loop exiting gracefully.');
    }
}
