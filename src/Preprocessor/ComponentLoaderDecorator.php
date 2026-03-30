<?php

declare(strict_types=1);

namespace OpenSolid\SugarTwig\Preprocessor;

use Twig\Loader\LoaderInterface;
use Twig\Source;

final readonly class ComponentLoaderDecorator implements LoaderInterface
{
    public function __construct(
        private LoaderInterface $inner,
        private ComponentPreprocessor $preprocessor,
    ) {
    }

    public function getSourceContext(string $name): Source
    {
        $source = $this->inner->getSourceContext($name);
        $code = $this->preprocessor->process($source->getCode());

        return new Source($code, $source->getName(), $source->getPath());
    }

    public function getCacheKey(string $name): string
    {
        return $this->inner->getCacheKey($name);
    }

    public function isFresh(string $name, int $time): bool
    {
        return $this->inner->isFresh($name, $time);
    }

    public function exists(string $name): bool
    {
        return $this->inner->exists($name);
    }
}
