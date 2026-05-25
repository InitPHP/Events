<?php

/**
 * PHP-CS-Fixer configuration for initphp/events.
 *
 * Baseline: PSR-12. The handful of additions below codify the style
 * the existing source already uses (short array syntax, ordered
 * imports, single quotes, trailing commas in multi-line arrays).
 *
 * Important: rules that would inject modern type-system syntax
 * (declare_strict_types, return type declarations, void return,
 * nullable type declarations) are intentionally *off* because the
 * runtime contract in composer.json is `php: >= 5.6` and those
 * constructs would silently break that promise.
 */

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setUsingCache(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setFinder($finder)
    ->setRules([
        '@PSR12'                                  => true,

        // PSR-12 makes constant visibility (`public const`) mandatory,
        // but that syntax requires PHP 7.1+. We support PHP 5.6, so
        // we restrict the modifier-keywords fixer (the new name for
        // the deprecated `visibility_required`) to method/property
        // only and leave bare `const` declarations alone.
        'modifier_keywords'                       => ['elements' => ['method', 'property']],

        // Keep empty closure bodies (`function () {}`) on one line —
        // they read better than the 2-line equivalent. Non-empty
        // single-line bodies still get expanded by PSR-12's
        // statement_indentation rule, which is fine.
        'single_line_empty_body'                  => true,
        'braces_position'                         => [
            'anonymous_functions_opening_brace' => 'same_line',
        ],

        // PHPDoc separation produces noisy blank lines around
        // @return / @throws / @param. The existing house style packs
        // them together; keep that.
        'phpdoc_separation'                       => false,

        // Array & syntax preferences (match the existing source).
        'array_syntax'                            => ['syntax' => 'short'],
        'trailing_comma_in_multiline'             => ['elements' => ['arrays']],
        'no_whitespace_before_comma_in_array'     => true,
        'whitespace_after_comma_in_array'         => true,

        // Imports.
        'no_unused_imports'                       => true,
        'ordered_imports'                         => [
            'sort_algorithm' => 'alpha',
            'imports_order'  => ['class', 'function', 'const'],
        ],

        // Strings.
        'single_quote'                            => true,

        // Whitespace.
        'blank_line_after_opening_tag'            => true,
        'no_extra_blank_lines'                    => [
            'tokens' => [
                'extra',
                'throw',
                'use',
                'curly_brace_block',
                'parenthesis_brace_block',
                'square_brace_block',
            ],
        ],
        'no_trailing_whitespace'                  => true,
        'no_trailing_whitespace_in_comment'       => true,
        'single_blank_line_at_eof'                => true,

        // Operators.
        'binary_operator_spaces'                  => ['default' => 'single_space'],
        'concat_space'                            => ['spacing' => 'one'],
        'not_operator_with_successor_space'       => false,
        'unary_operator_spaces'                   => true,

        // Phpdoc.
        'phpdoc_align'                            => ['align' => 'left'],
        'phpdoc_indent'                           => true,
        'phpdoc_no_useless_inheritdoc'            => false, // @inheritDoc is meaningful for IDE/PHPStan in our interface impls.
        'phpdoc_scalar'                           => true,
        'phpdoc_single_line_var_spacing'          => true,
        'phpdoc_trim'                             => true,
        'phpdoc_trim_consecutive_blank_line_separation' => true,
    ]);
