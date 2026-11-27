# timmit/db-filter

Parses a compact filter expression into a `TiMMiT\MySQL\Where\Collection` for one column.

## Install

```bash
composer require timmit/db-filter
```

Requires PHP >= 8.0 and `timmit/db-mysql` >= v11.0. The return value is a where-collection from that package.

## Usage

```php
use TiMMiT\DBFilter\Engine;

$engine = new Engine();
$where = $engine->parse('!(abc|def)&~x', 'name', 'items');
```

`parse()` takes the expression, the column name, and an optional table name.

| Syntax | Meaning |
| --- | --- |
| `token` | Exact match |
| `~text` | Contains |
| `prefix*` | Prefix match |
| `a-b` | Range |
| `/pattern/` | Regular expression |
| `&` | And |
| `\|` or `,` | Or |
| `!` | Not |
| `(...)` | Grouping |

`!(abc|def)&~x` is "not (`abc` or `def`), and contains `x`".

## License

MIT.
