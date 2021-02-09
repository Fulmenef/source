<?php

declare(strict_types=1);

namespace App\Tests\Environment\EnvironmentMaker;

use App\Environment\EnvironmentMaker\RequirementsChecker;
use App\Tests\CustomProphecyTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;

/**
 * @internal
 *
 * @covers \App\Environment\EnvironmentMaker\RequirementsChecker
 */
final class RequirementsCheckerTest extends TestCase
{
    use CustomProphecyTrait;

    public function testItDetectsMandatoryBinaryStatus(): void
    {
        [$executableFinder] = $this->prophesizeObjectArguments();

        $executableFinder->find('docker')->shouldBeCalledOnce()->willReturn('/usr/local/bin/docker');
        $executableFinder->find('docker-compose')->shouldBeCalledOnce()->willReturn(null);
        $executableFinder->find('mutagen')->shouldBeCalledOnce()->willReturn('/usr/local/bin/mutagen');

        $requirementsChecker = new RequirementsChecker($executableFinder->reveal());
        static::assertSame([
            [
                'name' => 'docker',
                'description' => 'A self-sufficient runtime for containers.',
                'status' => true,
            ],
            [
                'name' => 'docker-compose',
                'description' => 'Define and run multi-container applications with Docker.',
                'status' => false,
            ],
            [
                'name' => 'mutagen',
                'description' => 'Fast and efficient way to synchronize code to Docker containers.',
                'status' => true,
            ],
        ], $requirementsChecker->checkMandatoryRequirements());
    }

    public function testItDetectsCertificatesBinaryFoundStatus(): void
    {
        [$executableFinder] = $this->prophesizeObjectArguments();
        $executableFinder->find('mkcert')->shouldBeCalledOnce()->willReturn('/usr/local/bin/mkcert');

        $requirementsChecker = new RequirementsChecker($executableFinder->reveal());
        static::assertSame([
            [
                'name' => 'mkcert',
                'description' => 'A simple zero-config tool to make locally trusted development certificates.',
                'status' => true,
            ],
        ], $requirementsChecker->checkNonMandatoryRequirements());
    }

    public function testItDetectsCertificatesBinaryNotFoundStatus(): void
    {
        [$executableFinder] = $this->prophesizeObjectArguments();
        $executableFinder->find('mkcert')->shouldBeCalledOnce()->willReturn(null);

        $requirementsChecker = new RequirementsChecker($executableFinder->reveal());
        static::assertSame([
            [
                'name' => 'mkcert',
                'description' => 'A simple zero-config tool to make locally trusted development certificates.',
                'status' => false,
            ],
        ], $requirementsChecker->checkNonMandatoryRequirements());
    }

    /**
     * {@inheritdoc}
     */
    protected function prophesizeObjectArguments(): array
    {
        return [
            $this->prophesize(ExecutableFinder::class),
        ];
    }
}
