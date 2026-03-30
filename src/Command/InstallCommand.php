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
        'sugar:install lucide/heart-pulse',
    ],
)]
final readonly class InstallCommand
{
    private const string COLLECTION_URL = 'https://raw.githubusercontent.com/open-solid/sugar-twig/refs/heads/main/collections/%s/registry.json';

    public function __construct(
        private string $projectDir,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument] string $name, #[Argument] string $targetDir = 'templates'): int
    {
        $parts = explode('/', $name);
        if (2 !== \count($parts)) {
            $io->error(sprintf('Invalid name "%s". Expected format: collection/element (e.g. shadcn/button).', $name));

            return 1;
        }

        [$collection, $element] = $parts;
        $io->comment(sprintf('Fetching %s registry...', $collection));
        $registryUrl = sprintf(self::COLLECTION_URL, $collection);

        $registry = json_decode(file_get_contents($registryUrl), true);
        if (!isset($registry['namespace'][$element])) {
            $io->error(sprintf('Element "%s" not found in the "%s" collection registry.', $element, $collection));
        }

        $io->comment(sprintf('Installing %s...', $name));
        $basePath = $registry['path'];
        $installed = [];
        foreach ($registry['namespace'][$element] as $path) {
            $fileUrl = $basePath.$path;
            $targetFile = $this->projectDir.DIRECTORY_SEPARATOR.$targetDir.DIRECTORY_SEPARATOR.$path;
            $this->filesystem->copy($fileUrl, $targetFile, true);
            $installed[] = $targetFile;
        }

        $io->success(sprintf('"%s" installed to:%s', $name, "\n - ".implode("\n - ", $installed)));

        return 0;
    }
}