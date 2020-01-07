<?php

declare(strict_types=1);

namespace App\Tests\Command\Contextual;

use App\Command\Contextual\RootCommand;
use App\Exception\InvalidEnvironmentException;
use App\Helper\CommandExitCode;
use App\Helper\ProcessProxy;
use App\Middleware\Binary\DockerCompose;
use App\Middleware\SystemManager;
use App\Tests\TestCustomCommandsTrait;
use App\Tests\TestFakeEnvironmentTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 *
 * @covers \App\Command\AbstractBaseCommand
 * @covers \App\Command\Contextual\RootCommand
 *
 * @uses \App\Event\AbstractEnvironmentEvent
 */
final class RootCommandTest extends WebTestCase
{
    use TestCustomCommandsTrait;
    use TestFakeEnvironmentTrait;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->systemManager = $this->prophesize(SystemManager::class);
        $this->validator = $this->prophesize(ValidatorInterface::class);
        $this->dockerCompose = $this->prophesize(DockerCompose::class);
        $this->eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
        $this->processProxy = $this->prophesize(ProcessProxy::class);
    }

    public function testItShowsRootInstructions(): void
    {
        $environment = $this->getFakeEnvironment();

        $this->systemManager->getActiveEnvironment()->shouldBeCalledOnce()->willReturn($environment);
        $this->dockerCompose->setActiveEnvironment($environment)->shouldBeCalledOnce();
        $this->dockerCompose->getRequiredVariables()->shouldBeCalledOnce()->willReturn(
            [
                'COMPOSE_FILE' => "{$environment->getLocation()}/var/docker/docker-compose.yml",
                'COMPOSE_PROJECT_NAME' => $environment->getType().'_'.$environment->getName(),
                'DOCKER_PHP_IMAGE' => 'default',
                'PROJECT_LOCATION' => $environment->getLocation(),
            ]
        );

        $command = new RootCommand(
            $this->systemManager->reveal(),
            $this->validator->reveal(),
            $this->dockerCompose->reveal(),
            $this->eventDispatcher->reveal(),
            $this->processProxy->reveal(),
        );

        $commandTester = new CommandTester($command);
        $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $display = $commandTester->getDisplay();

        static::assertDisplayIsVerbose($environment, $display);
        static::assertStringContainsString('export COMPOSE_FILE="~/Sites/origami/var/docker/docker-compose.yml"', $display);
        static::assertStringContainsString('export COMPOSE_PROJECT_NAME="symfony_origami"', $display);
        static::assertStringContainsString('export DOCKER_PHP_IMAGE="default"', $display);
        static::assertStringContainsString('export PROJECT_LOCATION="~/Sites/origami"', $display);
        static::assertSame(CommandExitCode::SUCCESS, $commandTester->getStatusCode());
    }

    public function testItGracefullyExitsWhenAnExceptionOccurred(): void
    {
        $this->systemManager->getActiveEnvironment()
            ->willThrow(new InvalidEnvironmentException('Dummy exception.'))
        ;

        $command = new RootCommand(
            $this->systemManager->reveal(),
            $this->validator->reveal(),
            $this->dockerCompose->reveal(),
            $this->eventDispatcher->reveal(),
            $this->processProxy->reveal(),
        );

        static::assertExceptionIsHandled($command, '[ERROR] Dummy exception.');
    }
}
