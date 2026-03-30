<?php

declare(strict_types=1);

namespace OpenSolid\SugarTwig\Preprocessor;

final readonly class ComponentNameResolver
{
    /**
     * Resolves a PascalCase component name to a template path.
     *
     * e.g. 'AlertDialog' -> 'components/alert-dialog.html.twig'
     */
    public function resolve(string $name): string
    {
        $result = strtolower($name[0]);

        for ($i = 1, $len = \strlen($name); $i < $len; ++$i) {
            if (ctype_upper($name[$i])) {
                $result .= '-';
            }
            $result .= strtolower($name[$i]);
        }

        return 'components/'.$result.'.html.twig';
    }
}
