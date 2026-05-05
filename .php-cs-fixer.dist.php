<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = new Finder()
    ->in([
        __DIR__ . '/sources/lib',
        __DIR__ . '/sources/tests',
    ])
    ->notPath('config.php')
    ->notPath('config.github.php');

return new Config()
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PHP84Migration' => true,

        // Imports
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_line_after_imports' => true,
        'no_leading_import_slash' => true,

        // Array / syntax
        'array_syntax' => ['syntax' => 'short'],
        'trim_array_spaces' => true,
        'no_trailing_comma_in_singleline' => true,

        // Spacing / whitespace
        'no_extra_blank_lines' => [
            'tokens' => ['extra', 'throw', 'use', 'use_trait', 'curly_brace_block'],
        ],
        'no_whitespace_in_blank_line' => true,
        'no_spaces_around_offset' => true,

        // Operators / casts
        'cast_spaces' => ['space' => 'single'],
        'concat_space' => ['spacing' => 'one'],
        'standardize_not_equals' => true,
        'ternary_to_null_coalescing' => true,

        // Types
        'fully_qualified_strict_types' => true,

        // Misc cleanup
        'no_empty_comment' => true,
        'no_empty_phpdoc' => true,
        'no_empty_statement' => true,
        'no_trailing_whitespace_in_comment' => true,
        'single_blank_line_at_eof' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache');
