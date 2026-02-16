<?php

/**
 * Unary NOT node.
 */

declare(strict_types=1);

namespace TiMMiT\DBFilter\Engine;

/**
 * Unary NOT node.
 */
final class NotNode implements iNode
{
    /**
     * @var iNode
     */
    public readonly iNode $child;

    /**
     * Constructor for the NotNode class.
     *
     * @param iNode $child
     *
     * @return void
     */
    public function __construct(iNode $child)
    {
        $this->child = $child;
    }
}
