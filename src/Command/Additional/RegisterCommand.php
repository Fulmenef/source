<?php

declare(strict_types=1);

namespace App\Command\Additional;

use App\Command\AbstractBaseCommand;
use App\Entity\Environment;
use App\Exception\OrigamiExceptionInterface;
use App\Helper\CommandExitCode;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RegisterCommand extends AbstractBaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('origami:register');
        $this->setAliases(['register']);

        $this->setDescription('Register an external environment which was not created by Origami.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            if ($this->io->confirm('Do you want to register the current directory as a custom environment?', false)) {
                $location = $this->processProxy->getWorkingDirectory();

                $this->systemManager->install($location, Environment::TYPE_CUSTOM, null);
                $this->io->success('Environment successfully registered.');
            }
        } catch (OrigamiExceptionInterface $e) {
            $this->io->error($e->getMessage());
            $exitCode = CommandExitCode::EXCEPTION;
        }

        return $exitCode ?? CommandExitCode::SUCCESS;
    }
}
