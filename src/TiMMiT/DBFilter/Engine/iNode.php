<?php

/**
 * Bestand welke de iNode Interface bevat
 */

declare(strict_types=1);

namespace TiMMiT\DBFilter\Engine;

/**
 * Marker interface for group filter AST nodes.
 *
 * The parser produces a small object graph (nodes) which is then compiled into
 * `TiMMiT\MySQL\Where\Collection` / `TiMMiT\MySQL\Where\Param`.
 */
interface iNode
{
    // multiline fix
}
