<?php

declare(strict_types=1);

namespace App\Tests\Command\Main;

use App\Command\Main\UninstallCommand;
use App\Environment\Configuration\ConfigurationUninstaller;
use App\Environment\EnvironmentEntity;
use App\Helper\CommandExitCode;
use App\Helper\CurrentContext;
use App\Middleware\Binary\DockerCompose;
use App\Tests\Command\TestCommandTrait;
use App\Tests\TestLocationTrait;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 *
 * @covers \App\Command\AbstractBaseCommand
 * @covers \App\Command\Main\UninstallCommand
 *
 * @uses \App\Event\AbstractEnvironmentEvent
 */
final class UninstallCommandTest extends WebTestCase
{
    use ProphecyTrait;
    use TestCommandTrait;
    use TestLocationTrait;

    public function testItUninstallsTheCurrentEnvironment(): void
    {
        $environment = $this->createEnvironment();
        $environment->deactivate();
        $this->installEnvironmentConfiguration($environment);

        [$currentContext, $dockerCompose, $uninstaller, $eventDispatcher] = $this->prophesizeUninstallCommandArguments();

        $currentContext->getEnvironment(Argument::type(InputInterface::class))->shouldBeCalledOnce()->willReturn($environment);
        $currentContext->setActiveEnvironment($environment)->shouldBeCalledOnce();
        $dockerCompose->removeServices()->shouldBeCalledOnce()->willReturn(true);
        $eventDispatcher->dispatch(Argument::any())->shouldBeCalledOnce();
        $uninstaller->uninstall($environment)->shouldBeCalledOnce();

        $command = new UninstallCommand($currentContext->reveal(), $dockerCompose->reveal(), $uninstaller->reveal(), $eventDispatcher->reveal());
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();
        static::assertStringContainsString('[OK] ', $display);
        static::assertSame(CommandExitCode::SUCCESS, $commandTester->getStatusCode());
    }

    public function testItDoesNotUninstallARunningEnvironment(): void
    {
        $environment = $this->createEnvironment();
        $environment->activate();
        $this->installEnvironmentConfiguration($environment);

        [$currentContext, $dockerCompose, $uninstaller, $eventDispatcher] = $this->prophesizeUninstallCommandArguments();

        $currentContext->getEnvironment(Argument::type(InputInterface::class))->shouldBeCalledOnce()->willReturn($environment);
        $currentContext->setActiveEnvironment(Argument::type(EnvironmentEntity::class))->shouldNotBeCalled();
        $dockerCompose->removeServices()->shouldNotBeCalled();
        $eventDispatcher->dispatch(Argument::any())->shouldNotBeCalled();
        $uninstaller->uninstall($environment)->shouldNotBeCalled();

        $command = new UninstallCommand($currentContext->reveal(), $dockerCompose->reveal(), $uninstaller->reveal(), $eventDispatcher->reveal());
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();
        static::assertStringContainsString('[ERROR] ', $display);
        static::assertSame(CommandExitCode::EXCEPTION, $commandTester->getStatusCode());
    }

    public function testItGracefullyExitsWhenAnExceptionOccurred(): void
    {
        $environment = $this->createEnvironment();
        $this->installEnvironmentConfiguration($environment);

        [$currentContext, $dockerCompose, $uninstaller, $eventDispatcher] = $this->prophesizeUninstallCommandArguments();

        $currentContext->getEnvironment(Argument::type(InputInterface::class))->shouldBeCalledOnce()->willReturn($environment);
        $currentContext->setActiveEnvironment($environment)->shouldBeCalledOnce();
        $dockerCompose->removeServices()->shouldBeCalledOnce()->willReturn(false);
        $eventDispatcher->dispatch(Argument::any())->shouldBeCalledOnce();
        $uninstaller->uninstall($environment)->shouldBeCalledOnce();

        $command = new UninstallCommand($currentContext->reveal(), $dockerCompose->reveal(), $uninstaller->reveal(), $eventDispatcher->reveal());
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();
        static::assertStringContainsString('[WARNING] ', $display);
        static::assertStringContainsString('[OK] ', $display);
        static::assertSame(CommandExitCode::SUCCESS, $commandTester->getStatusCode());
    }

    /**
     * Prophesizes arguments needed by the \App\Command\Main\UninstallCommand class.
     */
    private function prophesizeUninstallCommandArguments(): array
    {
        return [
            $this->prophesize(CurrentContext::class),
            $this->prophesize(DockerCompose::class),
            $this->prophesize(ConfigurationUninstaller::class),
            $this->prophesize(EventDispatcher::class),
        ];
    }
}
