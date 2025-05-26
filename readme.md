# Chophper - A simple text truncation utility for HTML

Chophper is a PHP utility for truncating text within HTML to a given length without breaking the HTML tags.

Support for:

- Truncate chars, optionally respecting word boundaries
- Truncate words, optionally respecting sentence boundaries
- Truncate sentences, optionally respecting block boundaries
- Truncate blocks (paragraphs, lists, etc.)
- Preserving HTML tags
- Preserving HTML entities

**Note: This is an alpha version. Use at your own risk, and expect API changes towards simple and more flexible usage before the first stable release.**

## Installation

Install via composer:

```bash
composer require code-atlantic/chophper
```

## Usage

```php
// Full is built to fully support HTML5 without breaking the HTML structure.
use Chophper\Full as Chophper; 

$options [
    // ... see options below.
];

Chophper::truncate($html, $length, $options);
```

## Options ( current, very subject to change )

| Option | Type | Default | Description |
| --- | --- | --- | --- |
| `ellipsis` | string | `…` | The string to append to the truncated text. |
| `truncateBy` | string | `words` | Whether to break the text by chars, words, sentences or blocks |
| `preserveWords` | boolean | `false` | Whether to preserve words when using chars truncation. |

## Options (wisthlist)

| Option | Type | Default | Description |
| --- | --- | --- | --- |
| `wordBreak` | boolean | `false` | Whether to break the text at word boundaries. |
| `preserveTags` | boolean | `false` | Whether to preserve HTML tags. |
| `tagsWhitelist` | array | `[]` | A list of HTML tags to preserve. |
| `tagsBlacklist` | array | `[]` | A list of HTML tags to remove. |
| `tagsIngoreLength` | array | `[]` | A list of HTML tags to not count towards the length. |
| `preserveEntities` | boolean | `false` | Whether to preserve HTML entities. |
| `entitiesWhitelist` | array | `[]` | A list of HTML entities to preserve. |
| `entitiesBlacklist` | array | `[]` | A list of HTML entities to remove. |
| `preserveImages` | boolean | `false` | Whether to preserve images. |
