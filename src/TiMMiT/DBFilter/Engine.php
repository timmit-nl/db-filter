<?php

/**
 * DB Filter Engine – parses filter expressions into Collection/Param clauses.
 */

declare(strict_types=1);

namespace TiMMiT\DBFilter;

use TiMMiT\DBFilter\Engine\iNode;
use TiMMiT\DBFilter\Engine\AtomNode;
use TiMMiT\DBFilter\Engine\NotNode;
use TiMMiT\DBFilter\Engine\AndNode;
use TiMMiT\DBFilter\Engine\OrNode;

use TiMMiT\MySQL\Where\Collection;
use TiMMiT\MySQL\Where\Param;

/**
 * Engine class for the group filter.
 *
 * Parses a compact filter expression language into a SQL fragment targeting a specific field and table.
 *
 * Supported operators/syntax:
 * - `&` : AND
 * - `|` or `,` : OR
 * - `!` : NOT
 * - parentheses `(...)` for grouping
 * - `/pattern/` : REGEXP match
 * - `~text` : contains (LIKE '%text%')
 * - `prefix*` : prefix match (LIKE 'prefix%')
 * - `a-b` : range (BETWEEN 'a' AND 'b')
 * - `token` : exact match (=`token`)
 *
 * Internal AST nodes (used by the parser/compiler):
 *
 * The parser produces a small object graph (instead of arrays) so the output is strict and stable:
 * - `EngineAtomNode` for atoms (single token)
 * - `EngineNotNode` for unary NOT
 * - `EngineAndNode` for n-ary AND (always produced by `parseTerm()`)
 * - `EngineOrNode` for binary OR (produced by `parseExpression()`)
 *
 * Example AST:
 * - Input: `!(abc|def)&~x`
 * - AST: `EngineAndNode([ EngineNotNode(EngineOrNode(Atom('abc'), Atom('def'))), Atom('~x') ])`
 */
class Engine
{
    /**
     * Parse an input expression into SQL (and parameters).
     *
     * @param string $input
     * @param string $field
     * @param string|null $table
     *
     * @return Collection
     */
    public function parse(string $input, string $field, ?string $table = null): Collection
    {
        /* Split the input into tokens. */
        $tokens = $this->tokenize($input);
        var_dump($tokens);

        /* Build a small AST representing the boolean expression. */
        $ast = $this->parseExpression($tokens);
        var_dump($ast);
        /* Compile the AST into a Where expression. */
        $Collection = $this->compile($ast, $field, $table);

        return $Collection;
    }

    /* ================= TOKENIZER ================= */

    /**
     * Tokenize the input string.
     *
     * @param string $input
     *
     * @return array<int, string>
     */
    private function tokenize(string $input): array
    {
        /* Match ~identifier (and ~identifier*) as one token; standalone ~ is matched later in the alternation. */
        $pattern = '/(\(|\)|\&|\||,|!|~[A-Za-z0-9_-]+\*?|~|\/[^\/]+\/*|[A-Za-z0-9_-]+\*?|[A-Za-z0-9_-]+-[A-Za-z0-9_-]+)/';
        preg_match_all($pattern, $input, $matches);
        return array_map('trim', $matches[0]);
    }

    /* ================= PARSER ================= */

    /**
     * Peek the next token without consuming it.
     *
     * @param array<int, string> $tokens
     *
     * @return string|null
     */
    private function peek(array $tokens): ?string
    {
        return $tokens[0] ?? null;
    }

    /**
     * Parse an expression: term ( (OR) term )*
     *
     * @param array<int, string> $tokens Token stream (passed by reference; consumed while parsing).
     *
     * @return iNode Root AST node for the expression.
     */
    private function parseExpression(array &$tokens): iNode
    {
        $node = $this->parseTerm($tokens);
        while (count($tokens) > 0 && in_array((string)$this->peek($tokens), ['|', ','], true)) {
            /* Consume OR operator and build a binary OR node. */
            array_shift($tokens);
            $node = new OrNode($node, $this->parseTerm($tokens));
        }
        return $node;
    }

    /**
     * Parse a term: factor ( AND factor )*
     *
     * @param array<int, string> $tokens Token stream (passed by reference; consumed while parsing).
     *
     * @return AndNode Stable: always returns an AND node with a non-empty list of child nodes.
     */
    private function parseTerm(array &$tokens): AndNode
    {
        $items = [];
        $items[] = $this->parseFactor($tokens);

        while (count($tokens) > 0 && '&' === (string)$this->peek($tokens)) {
            /* Consume AND operator and append the next factor. */
            array_shift($tokens);
            $items[] = $this->parseFactor($tokens);
        }

        /* Stable output: always an n-ary AND node. */
        return new AndNode($items);
    }

    /**
     * Parse a factor.
     *
     * This method is intentionally "stable" in its output: it always returns an AST node array
     * with one of the supported shapes:
     * - `['ATOM', '<token>']`
     * - `['NOT', <node>]`
     * - `['AND', [<node>, ...]]` (built in `parseTerm()`)
     * - `['OR', <node>, <node>]` (built in `parseExpression()`)
     *
     * @param array<int, string> $tokens Token stream (passed by reference; consumed while parsing).
     *
     * @return iNode
     *
     * @throws \InvalidArgumentException If the input is invalid.
     */
    private function parseFactor(array &$tokens): iNode
    {
        $next = $this->peek($tokens);
        if (is_null($next)) {
            /* Keep behavior predictable: empty/incomplete input is a hard error. */
            throw new \InvalidArgumentException('Invalid group filter: unexpected end of input.');
        }

        if ('!' === $next) {
            /*
             * Unary NOT binds to the next factor (either "(...)" or an atom).
             * We parse it recursively so sequences like "!!a" behave consistently.
             */
            array_shift($tokens);
            $inner = $this->parseFactor($tokens);
            return new NotNode($inner);
        }

        if ('(' === $next) {
            /* Parenthesized sub-expression. */
            array_shift($tokens);
            $node = $this->parseExpression($tokens);

            $closing = $this->peek($tokens);
            if (')' !== $closing) {
                throw new \InvalidArgumentException('Invalid group filter: missing closing parenthesis ")".');
            }
            /* Consume closing parenthesis. */
            array_shift($tokens);

            return $node;
        }

        /* Atom token (regex, range, wildcard, etc.). */
        $atom = array_shift($tokens);
        if (is_null($atom) | '' === $atom) {
            throw new \InvalidArgumentException('Invalid group filter: empty token.');
        }
        return new AtomNode($atom);
    }

    /* ================= COMPILER ================= */

    /**
     * Compile an AST node into a Where expression object.
     *
     * @param iNode $node
     * @param string $field
     * @param string|null $table
     *
     * @return Collection A `Collection` instance that can render to SQL.
     *
     * @throws \LogicException If the node type is unsupported.
     */
    private function compile(iNode $node, string $field, ?string $table = null): Collection
    {
        if ($node instanceof AtomNode) {
            $Collection = new Collection();
            $Collection->add($this->compileAtom($node->token, $field, $table));
            return $Collection;
        }

        if ($node instanceof NotNode) {
            var_dump($node);
            // TODO: Dit gaat fout!

            /* Unary NOT is represented as a NOT collection with a single child. */
            $NotCollection = new Collection('NOT');
            $NotCollection->add($this->compile($node->child, $field, $table));
            return $NotCollection;
        }

        if ($node instanceof AndNode) {
            $Collection = new Collection('AND');
            foreach ($node->children as $child) {
                $Collection->add($this->compile($child, $field, $table));
            }
            return $Collection;
        }

        if ($node instanceof OrNode) {
            /* OR is binary. */
            $Collection = new Collection('OR');
            $Collection->add($this->compile($node->left, $field, $table));
            $Collection->add($this->compile($node->right, $field, $table));
            return $Collection;
        }

        throw new \LogicException('Invalid group filter: unsupported node type.');
    }

    /**
     * Compile a single token into a Where Param.
     *
     * @param string $token
     * @param string $field
     * @param string|null $table
     *
     * @return Param
     */
    private function compileAtom(string $token, string $field, ?string $table = null): Param
    {
        $Param = new Param($field);
        if (!is_null($table)) {
            $Param->table($table);
        }

        /* Regex: /pattern/ -> REGEXP 'pattern' */
        if ('/' === $token[0] && str_ends_with($token, '/')) {
            $regex = trim($token, '/');
            return $Param->regexMatch($regex);
        }

        /* Contains: ~text -> LIKE '%text%' (rendered via LIKE when escaping is disabled). */
        if (str_starts_with($token, '~')) {
            $val = substr($token, 1);
            return $Param->escapeSpecialCharacters(false)->is('*' . $val . '*');
        }

        /* Prefix wildcard: prefix* -> LIKE 'prefix%' */
        if (str_ends_with($token, '*')) {
            // $val = rtrim($token, '*');
            // var_dump($val);
            return $Param->escapeSpecialCharacters(false)->is($token);
        }

        /* Range: a-b -> BETWEEN 'a' AND 'b' */
        if (preg_match('/^(.+)-(.+)$/', $token, $m)) {
            return $Param->betweenRange($m[1], $m[2]);
        }

        /* Exact match: token -> = 'token' */
        return $Param->is($token);
    }
}
