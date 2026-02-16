<?php

/**
 * Atom node holding a single token produced by the tokenizer.
 */

declare(strict_types=1);

namespace TiMMiT\DBFilter\Engine;

/**
 * Atom node holding a single token produced by the tokenizer.
 */
final class AtomNode implements iNode
{
    /**
     * @var string
     */
    public readonly string $token;

    /**
     * Constructor for the AtomNode class.
     *
     * @param string $token
     *
     * @return void
     */
    public function __construct(string $token)
    {
        $this->token = $token;
    }
}
