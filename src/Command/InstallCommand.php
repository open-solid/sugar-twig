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
        [$collection, $element] = explode('/', $name);
        $io->comment(\sprintf('Fetching %s registry...', $collection));
        $registryUrl = sprintf(self::COLLECTION_URL, $collection);

        $registry = json_decode(file_get_contents($registryUrl), true);
        if (!isset($registry['namespace'][$element])) {
            $io->error(sprintf('Element "%s" not found in the "%s" collection registry.', $element, $collection));
        }

        $io->comment(sprintf('Installing %s...', $name));
        $fileUrl = $registry['path'].$registry['namespace'][$element];
        $targetFile = $this->projectDir.DIRECTORY_SEPARATOR.$targetDir.DIRECTORY_SEPARATOR.$registry['namespace'][$element];
        $this->filesystem->copy($fileUrl, $targetFile, true);

        $io->success(sprintf('"%s" installed to "%s".', $name, $targetFile));

        return 0;
    }
}