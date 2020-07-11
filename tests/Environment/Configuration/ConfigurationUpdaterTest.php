<?php

declare(strict_types=1);

namespace App\Tests\Environment\Configuration;

use App\Environment\Configuration\AbstractConfiguration;
use App\Environment\Configuration\ConfigurationUpdater;
use App\Environment\EnvironmentEntity;
use App\Environment\EnvironmentMaker\DockerHub;
use App\Exception\FilesystemException;
use App\Exception\InvalidEnvironmentException;
use App\Middleware\Binary\Mkcert;
use App\Tests\TestConfigurationTrait;
use App\Tests\TestLocationTrait;
use Ergebnis\Environment\FakeVariables;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Prophecy\Prophet;

/**
 * @internal
 *
 * @covers \App\Environment\Configuration\AbstractConfiguration
 * @covers \App\Environment\Configuration\ConfigurationUpdater
 */
final class ConfigurationUpdaterTest extends TestCase
{
    use TestConfigurationTrait;
    use TestLocationTrait;

    /** @var Prophet */
    private $prophet;

    /** @var ObjectProphecy */
    private $mkcert;

    /** @var string */
    private $fakePhpVersion = 'azerty';

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->prophet = new Prophet();
        $this->mkcert = $this->prophet->prophesize(Mkcert::class);

        $this->createLocation();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->prophet->checkPredictions();
        $this->removeLocation();
    }

    /**
     * @dataProvider provideMultipleInstallContexts
     *
     * @throws FilesystemException
     * @throws InvalidEnvironmentException
     */
    public function testItUpdatesAnEnvironmentWithPhpImage(string $name, string $type, ?string $domains = null): void
    {
        $environment = new EnvironmentEntity($name, $this->location, $type, $domains);

        $destination = $this->location.AbstractConfiguration::INSTALLATION_DIRECTORY;
        mkdir($destination, 0777, true);
        file_put_contents("{$destination}/.env", "DOCKER_PHP_IMAGE={$this->fakePhpVersion}");

        $updater = new ConfigurationUpdater($this->mkcert->reveal(), FakeVariables::empty());
        $updater->update($environment);

        $this->assertConfigurationIsInstalled($type, $destination, $this->fakePhpVersion);
    }

    /**
     * @dataProvider provideMultipleInstallContexts
     *
     * @throws FilesystemException
     * @throws InvalidEnvironmentException
     */
    public function testItUpdatesAnEnvironmentWithoutPhpImage(string $name, string $type, ?string $domains = null): void
    {
        $environment = new EnvironmentEntity($name, $this->location, $type, $domains);

        $destination = $this->location.AbstractConfiguration::INSTALLATION_DIRECTORY;
        mkdir($destination, 0777, true);
        file_put_contents("{$destination}/.env", 'DOCKER_PHP_IMAGE=');

        $updater = new ConfigurationUpdater($this->mkcert->reveal(), FakeVariables::empty());
        $updater->update($environment);

        $this->assertConfigurationIsInstalled($type, $destination, DockerHub::DEFAULT_IMAGE_VERSION);
    }

    /**
     * @dataProvider provideMultipleInstallContexts
     *
     * @throws FilesystemException
     * @throws InvalidEnvironmentException
     */
    public function testItUpdatesAnEnvironmentWithBlackfireCredentials(string $name, string $type, ?string $domains = null): void
    {
        $environment = new EnvironmentEntity($name, $this->location, $type, $domains);

        $source = __DIR__."/../../../src/Resources/{$type}";
        $destination = $this->location.AbstractConfiguration::INSTALLATION_DIRECTORY;

        mkdir($destination, 0777, true);
        copy("{$source}/.env", "{$destination}/.env");

        $credentials = $this->getFakeBlackfireCredentials();

        $updater = new ConfigurationUpdater($this->mkcert->reveal(), FakeVariables::fromArray($credentials));
        $updater->update($environment);

        $this->assertConfigurationIsInstalled($type, $destination, DockerHub::DEFAULT_IMAGE_VERSION);
        $this->assertConfigurationContainsBlackfireCredentials($destination, $credentials);
    }

    /**
     * @throws FilesystemException
     * @throws InvalidEnvironmentException
     */
    public function testItDoesNotUpdateACustomEnvironment(): void
    {
        $environment = new EnvironmentEntity(basename($this->location), $this->location, EnvironmentEntity::TYPE_CUSTOM, null);

        $destination = $this->location.AbstractConfiguration::INSTALLATION_DIRECTORY;
        mkdir($destination, 0777, true);
        file_put_contents("{$destination}/.env", "DOCKER_PHP_IMAGE={$this->fakePhpVersion}");

        $this->expectExceptionObject(new InvalidEnvironmentException('Unable to update a custom environment.'));

        $updater = new ConfigurationUpdater($this->mkcert->reveal(), FakeVariables::empty());
        $updater->update($environment);
    }

    /**
     * @dataProvider provideMultipleInstallContexts
     *
     * @throws FilesystemException
     * @throws InvalidEnvironmentException
     */
    public function testItDoesNotUpdateARunningEnvironment(string $name, string $type, ?string $domains = null): void
    {
        $environment = new EnvironmentEntity($name, $this->location, $type, $domains, true);

        $destination = $this->location.AbstractConfiguration::INSTALLATION_DIRECTORY;
        mkdir($destination, 0777, true);
        file_put_contents("{$destination}/.env", "DOCKER_PHP_IMAGE={$this->fakePhpVersion}");

        $this->expectExceptionObject(new InvalidEnvironmentException('Unable to update a running environment.'));

        $updater = new ConfigurationUpdater($this->mkcert->reveal(), FakeVariables::empty());
        $updater->update($environment);
    }
}
