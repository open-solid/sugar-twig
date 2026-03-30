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
    public function __invoke(SymfonyStyle $io, #[Argument] array $names, #[Argument] string $targetDir = 'templates'): int
    {
        $registries = [];
        $installed = [];

        foreach ($names as $name) {
            $parts = explode('/', $name);
            if (2 !== \count($parts)) {
                $io->error(sprintf('Invalid name "%s". Expected format: collection/element (e.g. shadcn/button).', $name));

                return 1;
            }

            [$collection, $element] = $parts;

            if (!isset($registries[$collection])) {
                $io->comment(sprintf('Fetching %s registry...', $collection));
                $registryUrl = sprintf($this->collectionUrl, $collection);
                $registries[$collection] = json_decode(file_get_contents($registryUrl), true);
            }

            $registry = $registries[$collection];
            if (!isset($registry['namespace'][$element])) {
                $io->error(sprintf('Element "%s" not found in the "%s" collection registry.', $element, $collection));

                return 1;
            }

            $io->comment(sprintf('Installing %s...', $name));
            foreach ($registry['namespace'][$element] as $path) {
                $fileUrl = $registry['path'].$path;
                $targetFile = $this->projectDir.DIRECTORY_SEPARATOR.$targetDir.DIRECTORY_SEPARATOR.$path;
                $this->filesystem->copy($fileUrl, $targetFile, true);
                $installed[] = $targetFile;
            }
        }

        $io->success(sprintf('Installed to:%s', "\n - ".implode("\n - ", $installed)));

        return 0;
    }
}