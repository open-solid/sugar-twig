<?php

declare(strict_types=1);

namespace OpenSolid\SugarTwig\Command;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class InstallCommand
{
    private const string URL = 'https://github.com/opensolid/sugar-twig/archive/refs/heads/main.zip';

    public function __construct(
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument] string $name): int
    {


        return 0;
    }
}