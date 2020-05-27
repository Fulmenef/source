<?php

declare(strict_types=1);

namespace App\Command\Main;

use App\Command\AbstractBaseCommand;
use App\Environment\Configuration\ConfigurationInstaller;
use App\Environment\EnvironmentMaker;
use App\Event\EnvironmentInstalledEvent;
use App\Exception\OrigamiExceptionInterface;
use App\Helper\CommandExitCode;
use App\Helper\ProcessProxy;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class InstallCommand extends AbstractBaseCommand
{
    /** @var ProcessProxy */
    private $processProxy;

    /** @var EnvironmentMaker */
    private $configurator;

    /** @var ConfigurationInstaller */
    private $installer;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    public function __construct(
        ProcessProxy $processProxy,
        EnvironmentMaker $configurator,
        ConfigurationInstaller $installer,
        EventDispatcherInterface $eventDispatcher,
        ?string $name = null
    ) {
        parent::__construct($name);

        $this->processProxy = $processProxy;
        $this->configurator = $configurator;
        $this->installer = $installer;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setDescription('Installs a Docker environment in the desired directory');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->note('The environment will be installed in the current directory.');

        try {
            $location = $this->processProxy->getWorkingDirectory();

            $name = $this->configurator->askEnvironmentName($io, basename($location));
            $type = $this->configurator->askEnvironmentType($io, $location);
            $phpVersion = $this->configurator->askPhpVersion($io, $type);
            $domains = $this->configurator->askDomains($io, $type);

            $environment = $this->installer->install($name, $location, $type, $phpVersion, $domains);

            $event = new EnvironmentInstalledEvent($environment, $io);
            $this->eventDispatcher->dispatch($event);

            $io->success('Environment successfully installed.');
        } catch (OrigamiExceptionInterface $exception) {
            $io->error($exception->getMessage());
            $exitCode = CommandExitCode::EXCEPTION;
        }

        return $exitCode ?? CommandExitCode::SUCCESS;
    }
}
