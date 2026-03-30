<?php

declare(strict_types=1);

namespace OpenSolid\SugarTwig\Parser;

enum TagType
{
    case SelfClosing;
    case Opening;
    case Closing;
}
