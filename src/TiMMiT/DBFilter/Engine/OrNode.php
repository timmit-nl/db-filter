<?php

/**
 * Bestand welke de OrNode Class bevat
 */

declare(strict_types=1);

namespace TiMMiT\DBFilter\Engine;

/**
 * Binary OR node.
 */
final class OrNode implements iNode
{
    /**
     * @var iNode
     */
    public readonly iNode $left;

    /**
     * @var iNode
     */
    public readonly iNode $right;

    /**
     * Constructor for the OrNode class.
     *
     * @param iNode $left
     * @param iNode $right
     *
     * @return void
     */
    public function __construct(
        iNode $left,
        iNode $right
    ) {
        $this->left = $left;
        $this->right = $right;
    }
}
