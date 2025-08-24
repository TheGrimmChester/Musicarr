<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        dirname(__DIR__) . '/src',
        dirname(__DIR__) . '/tests',
    ])
    ->exclude('var')
    ->exclude('vendor');

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        // Custom rules that extend Symfony standards
        'global_namespace_import' => ['import_classes' => true, 'import_constants' => false, 'import_functions' => false],
        'fully_qualified_strict_types' => [
            'leading_backslash_in_global_namespace' => true,
            'import_symbols' => true,
        ],
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'mb_str_functions' => true,
        'trim_array_spaces' => true,
        'array_indentation' => true,
        'strict_comparison' => true,
        'trailing_comma_in_multiline' => true,
        'phpdoc_scalar' => true,
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => true,
        ],
        'align_multiline_comment' => true,
        'declare_strict_types' => true,
        'method_chaining_indentation' => true,
        'standardize_not_equals' => true,
        'strict_param' => true,
        'concat_space' => ['spacing' => 'one'],
        'whitespace_after_comma_in_array' => true,
    ])
    ->setFinder($finder);
