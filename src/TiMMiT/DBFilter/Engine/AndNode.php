<?php

/**
 * N-ary AND node.
 *
 * This is used to make term parsing stable: a term is always represented as an AND node with a
 * non-empty list of children (even if it contains only one factor).
 */

declare(strict_types=1);

namespace TiMMiT\DBFilter\Engine;

/**
 * N-ary AND node.
 *
 * This is used to make term parsing stable: a term is always represented as an AND node with a
 * non-empty list of children (even if it contains only one factor).
 */
final class AndNode implements iNode
{
    /**
     * @var iNode[]
     */
    public readonly array $children;

    /**
     * Constructor for the AndNode class.
     *
     * @param iNode[] $children
     *
     * @return void
     *
     * @throws \InvalidArgumentException If the children are not a non-empty array.
     */
    public function __construct(array $children)
    {
        if (count($children) === 0) {
            throw new \InvalidArgumentException('Invalid group filter: AND node must have at least one child.');
        }
        $this->children = array_values($children);
    }
}
