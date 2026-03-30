<?php

declare(strict_types=1);

namespace OpenSolid\SugarTwig\Command;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'sugar:install',
    description: 'Install a Sugar Twig components.',
    usages: [
        'sugar:install shadcn/button',
        'sugar:install shadcn/button shadcn/card lucide/heart-pulse',
    ],
)]
final readonly class InstallCommand
{
    private const string COLLECTION_URL = 'https://raw.githubusercontent.com/open-solid/sugar-twig/refs/heads/main/collections/%s/registry.json';

    public function __construct(
        private string $projectDir,
        private Filesystem $filesystem = new Filesystem(),
        private string $collectionUrl = self::COLLECTION_URL,
    ) {
    }

    /**
     * @param list<string> $names
     */
    public function __invoke(SymfonyStyle $io, #[Argument] array $names, #[Option] string $targetDir = 'templates'): int
    {
        $registries = [];
        $installed = [];
        $failed = false;

        foreach ($names as $name) {
            $parts = explode('/', $name);
            if (2 !== \count($parts)) {
                $io->error(sprintf('Invalid name "%s". Expected format: collection/element (e.g. shadcn/button).', $name));

                return 1;
            }

            [$collection, $element] = $parts;

            if (!isset($registries[$collection])) {
                $io->write(sprintf("\r\033[2KInstalling %s...", $name));
                $registryUrl = sprintf($this->collectionUrl, $collection);
                $registries[$collection] = json_decode(file_get_contents($registryUrl), true);
            }

            $registry = $registries[$collection];
            if (!isset($registry['namespace'][$element])) {
                $io->writeln('');
                $io->error(sprintf('Element "%s" not found in the "%s" collection registry.', $element, $collection));

                return 1;
            }

            $io->write(sprintf("\r\033[2KInstalling %s...", $name));
            foreach ($registry['namespace'][$element] as $path) {
                $fileUrl = $registry['path'].$path;
                $targetFile = $this->projectDir.DIRECTORY_SEPARATOR.$targetDir.DIRECTORY_SEPARATOR.$path;
                try {
                    $this->filesystem->copy($fileUrl, $targetFile, true);
                    $installed[] = $targetFile;
                    $io->writeln(sprintf("\r\033[2K \xe2\x9c\x94 %s", $path));
                } catch (\Throwable) {
                    $failed = true;
                    $io->writeln(sprintf("\r\033[2K \xe2\x9c\x98 %s", $path));
                }
            }
        }

        $count = \count($installed);
        $io->writeln(sprintf(' Installed %d element%s.', $count, $count > 1 ? 's' : ''));

        return $failed ? 1 : 0;
    }
}