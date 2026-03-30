<?php

declare(strict_types=1);

namespace OpenSolid\SugarTwig\Tests\Command;

use OpenSolid\SugarTwig\Command\InstallCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class InstallCommandTest extends TestCase
{
    private string $tempDir;
    private string $registryDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir().'/sugar_twig_test_'.bin2hex(random_bytes(4));
        $this->registryDir = $this->tempDir.'/registry';
        $this->filesystem->mkdir([$this->tempDir, $this->registryDir]);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tempDir);
    }

    private function createCommandTester(string $collectionUrl): CommandTester
    {
        $command = new InstallCommand(
            projectDir: $this->tempDir,
            filesystem: $this->filesystem,
            collectionUrl: $collectionUrl,
        );

        $app = new Application();
        $app->addCommand($command);

        return new CommandTester($app->find('sugar:install'));
    }

    private function createRegistry(string $collection, array $namespace): string
    {
        $collectionDir = $this->registryDir.'/'.$collection;
        $this->filesystem->mkdir($collectionDir);

        $registry = [
            'name' => $collection,
            'path' => 'file://'.$collectionDir.'/',
            'namespace' => $namespace,
        ];

        file_put_contents($collectionDir.'/registry.json', json_encode($registry));

        return $collectionDir;
    }

    private function createTemplate(string $dir, string $path, string $content = '<div>test</div>'): void
    {
        $fullPath = $dir.'/'.$path;
        $this->filesystem->mkdir(\dirname($fullPath));
        file_put_contents($fullPath, $content);
    }

    #[Test]
    public function it_installs_a_single_element(): void
    {
        $collectionDir = $this->createRegistry('shadcn', [
            'button' => ['components/button.html.twig'],
        ]);
        $this->createTemplate($collectionDir, 'components/button.html.twig', '<button>Click</button>');

        $tester = $this->createCommandTester('file://'.$this->registryDir.'/%s/registry.json');
        $tester->execute(['names' => ['shadcn/button']]);

        $tester->assertCommandIsSuccessful();
        $this->assertFileExists($this->tempDir.'/templates/components/button.html.twig');
        $this->assertStringContainsString('<button>Click</button>', file_get_contents($this->tempDir.'/templates/components/button.html.twig'));
        $this->assertStringContainsString('Installed 1 element.', $tester->getDisplay());
    }

    #[Test]
    public function it_installs_an_element_with_multiple_paths(): void
    {
        $collectionDir = $this->createRegistry('shadcn', [
            'card' => ['components/card.html.twig', 'components/card-header.html.twig'],
        ]);
        $this->createTemplate($collectionDir, 'components/card.html.twig');
        $this->createTemplate($collectionDir, 'components/card-header.html.twig');

        $tester = $this->createCommandTester('file://'.$this->registryDir.'/%s/registry.json');
        $tester->execute(['names' => ['shadcn/card']]);

        $tester->assertCommandIsSuccessful();
        $this->assertFileExists($this->tempDir.'/templates/components/card.html.twig');
        $this->assertFileExists($this->tempDir.'/templates/components/card-header.html.twig');
        $this->assertStringContainsString('Installed 2 elements.', $tester->getDisplay());
    }

    #[Test]
    public function it_installs_multiple_elements(): void
    {
        $collectionDir = $this->createRegistry('shadcn', [
            'button' => ['components/button.html.twig'],
            'badge' => ['components/badge.html.twig'],
        ]);
        $this->createTemplate($collectionDir, 'components/button.html.twig');
        $this->createTemplate($collectionDir, 'components/badge.html.twig');

        $tester = $this->createCommandTester('file://'.$this->registryDir.'/%s/registry.json');
        $tester->execute(['names' => ['shadcn/button', 'shadcn/badge']]);

        $tester->assertCommandIsSuccessful();
        $this->assertFileExists($this->tempDir.'/templates/components/button.html.twig');
        $this->assertFileExists($this->tempDir.'/templates/components/badge.html.twig');
        $this->assertStringContainsString('Installed 2 elements.', $tester->getDisplay());
    }

    #[Test]
    public function it_installs_from_multiple_collections(): void
    {
        $shadcnDir = $this->createRegistry('shadcn', [
            'button' => ['components/button.html.twig'],
        ]);
        $this->createTemplate($shadcnDir, 'components/button.html.twig');

        $lucideDir = $this->createRegistry('lucide', [
            'heart-pulse' => ['icon/heart-pulse.html.twig'],
        ]);
        $this->createTemplate($lucideDir, 'icon/heart-pulse.html.twig');

        $tester = $this->createCommandTester('file://'.$this->registryDir.'/%s/registry.json');
        $tester->execute(['names' => ['shadcn/button', 'lucide/heart-pulse']]);

        $tester->assertCommandIsSuccessful();
        $this->assertFileExists($this->tempDir.'/templates/components/button.html.twig');
        $this->assertFileExists($this->tempDir.'/templates/icon/heart-pulse.html.twig');
    }

    #[Test]
    public function it_fails_with_invalid_name_format(): void
    {
        $tester = $this->createCommandTester('file://'.$this->registryDir.'/%s/registry.json');
        $tester->execute(['names' => ['invalid-name']]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Invalid name "invalid-name"', $tester->getDisplay());
    }

    #[Test]
    public function it_fails_when_element_not_found(): void
    {
        $this->createRegistry('shadcn', [
            'button' => ['components/button.html.twig'],
        ]);

        $tester = $this->createCommandTester('file://'.$this->registryDir.'/%s/registry.json');
        $tester->execute(['names' => ['shadcn/nonexistent']]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Element "nonexistent" not found in the "shadcn" collection registry.', $tester->getDisplay());
    }

    #[Test]
    public function it_uses_custom_target_dir(): void
    {
        $collectionDir = $this->createRegistry('shadcn', [
            'button' => ['components/button.html.twig'],
        ]);
        $this->createTemplate($collectionDir, 'components/button.html.twig');

        $tester = $this->createCommandTester('file://'.$this->registryDir.'/%s/registry.json');
        $tester->execute(['names' => ['shadcn/button'], '--target-dir' => 'custom/dir']);

        $tester->assertCommandIsSuccessful();
        $this->assertFileExists($this->tempDir.'/custom/dir/components/button.html.twig');
    }
}
