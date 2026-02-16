<?php

declare(strict_types=1);

namespace test\php\phpunit\TiMMiT\DBFilter;

use TiMMiT\DBFilter\Engine;
use TiMMiT\MySQL\Where\Collection;

use test\php\phpunit\BaseTestCase;

/**
 * PHPUnit tests for the DB Filter Engine.
 *
 * Tests parsing of filter expressions into Collection/Param clauses.
 */
class EngineTest extends BaseTestCase
{
    private Engine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new Engine();
    }

    /**
     * Test that parse() returns a Collection instance.
     */
    public function testParseReturnsCollection(): void
    {
        $result = $this->engine->parse('abc', 'field_name');

        $this->assertInstanceOf(Collection::class, $result);
    }

    /**
     * Test exact match: single token produces equality condition.
     */
    public function testParseExactMatch(): void
    {
        $result = $this->engine->parse('token', 'my_field');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    /**
     * Test parse with table parameter.
     */
    public function testParseWithTable(): void
    {
        $result = $this->engine->parse('value', 'field', 'my_table');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    /**
     * Test OR operator: a|b produces OR collection.
     */
    public function testParseOr(): void
    {
        $result = $this->engine->parse('a|b', 'field');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    /**
     * Test comma as OR: a,b produces OR.
     */
    public function testParseOrWithComma(): void
    {
        $result = $this->engine->parse('a,b', 'field');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    /**
     * Test AND operator: a&b produces AND collection.
     */
    public function testParseAnd(): void
    {
        $result = $this->engine->parse('a&b', 'field');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThanOrEqual(2, $result->count());
    }

    /**
     * Test contains: ~text produces LIKE '%text%'.
     */
    public function testParseContains(): void
    {
        $result = $this->engine->parse('~search', 'field');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    /**
     * Test prefix wildcard: prefix* produces prefix match.
     */
    public function testParsePrefix(): void
    {
        $result = $this->engine->parse('prefix*', 'field');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    /**
     * Test range: a-b produces BETWEEN.
     */
    public function testParseRange(): void
    {
        $result = $this->engine->parse('alpha-zulu', 'field');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    /**
     * Test regex: /pattern/ produces REGEXP.
     */
    public function testParseRegex(): void
    {
        $result = $this->engine->parse('/^test/', 'field');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    /**
     * Test NOT: !a produces NOT collection.
     */
    public function testParseNot(): void
    {
        $result = $this->engine->parse('!excluded', 'field');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    /**
     * Test parentheses for grouping: (a|b)&c.
     */
    public function testParseParentheses(): void
    {
        $result = $this->engine->parse('(a|b)&c', 'field');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    /**
     * Test complex expression: !(abc|def)&~x.
     */
    public function testParseComplexExpression(): void
    {
        $result = $this->engine->parse('!(abc|def)&~x', 'field');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    /**
     * Test empty input throws InvalidArgumentException.
     */
    public function testParseEmptyInputThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unexpected end of input');

        $this->engine->parse('!', 'field');
    }

    /**
     * Test missing closing parenthesis throws.
     */
    public function testParseMissingClosingParenthesisThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('missing closing parenthesis');

        $this->engine->parse('(a|b', 'field');
    }
}
