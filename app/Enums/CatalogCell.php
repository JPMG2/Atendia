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

    /** A symbol, a link or a date in monospace at body weight: the web's `catalog-cell-sym`. */
    case Symbol = 'symbol';

    /** A yes or no said in words, with no status dot. */
    case Flag = 'flag';

    /** The status tag, coloured by the row's `color` key. */
    case Tag = 'tag';

    /** On or off, said in words with a dot. */
    case Active = 'active';

    /** A word on a tinted ground and no dot; the column names the word and tone of each value. */
    case Badge = 'badge';

    /** A list of short words, each a small pill that wraps: the web's `catalog-chip`. */
    case List = 'list';

    /** Several tags in one cell, no dot: the row brings each word with its tone, already decided by the model. */
    case Badges = 'badges';
}
