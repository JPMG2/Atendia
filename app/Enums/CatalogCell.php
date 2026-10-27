<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a master's table draws one cell. The web paints each kind in its own
 * markup; the phone paints it from this name, so a kind is a promise both keep.
 */
enum CatalogCell: string
{
    /** The row's identifying text, set apart from the rest. */
    case Name = 'name';

    /** Plain text. */
    case Text = 'text';

    /** The row's code or abbreviation, set in monospace. */
    case Code = 'code';

    /** A number, aligned apart from the words. */
    case Number = 'number';

    /** A yes or no said in words, with no status dot. */
    case Flag = 'flag';

    /** The status tag, coloured by the row's `color` key. */
    case Tag = 'tag';

    /** On or off, said in words with a dot. */
    case Active = 'active';
}
