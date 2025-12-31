<?php

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/galette/lib',
        __DIR__ . '/galette/includes',
        //__DIR__ . '/galette/install',
        __DIR__ . '/tests',
    ])
;

return (new PhpCsFixer\Config())
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setCacheFile(sys_get_temp_dir() . '/php-cs-fixer.galette.cache')
    ->setRules([
        '@PSR12' => true,
        '@PER-CS' => true,
        '@PHP8x2Migration' => true,
        'trailing_comma_in_multiline' => false,
        'cast_spaces' => ['space' => 'none'],
        'single_line_empty_body' => false,
        'no_unused_imports' => true,
        // rules for phpdoc
        // Removes @param, @return and @var tags that don't provide any useful information - https://mlocati.github.io/php-cs-fixer-configurator/#version:3.90|fixer:no_superfluous_phpdoc_tags
        'no_superfluous_phpdoc_tags' => [
            'allow_mixed' => true,
            'remove_inheritdoc' => true,
        ],
        // require phpdoc for non typed arguments - https://mlocati.github.io/php-cs-fixer-configurator/#version:3.90|fixer:phpdoc_add_missing_param_annotation
        'phpdoc_add_missing_param_annotation' => true,
        // no @access - https://mlocati.github.io/php-cs-fixer-configurator/#version:3.90|fixer:phpdoc_no_access
        'phpdoc_no_access' => true,
        // no @package - https://mlocati.github.io/php-cs-fixer-configurator/#version:3.90|fixer:phpdoc_no_package
        'phpdoc_no_package' => true,
        // order phpdoc tags - https://mlocati.github.io/php-cs-fixer-configurator/#version:3.90|fixer:phpdoc_order
        'phpdoc_order' => ['order' => ['since', 'var', 'see', 'param', 'return', 'throw', 'todo', 'deprecated']],
        // phpdoc param in same order as signature - https://mlocati.github.io/php-cs-fixer-configurator/#version:3.90|fixer:phpdoc_param_order
        'phpdoc_param_order' => true,
        // align tags - https://mlocati.github.io/php-cs-fixer-configurator/#version:3.90|fixer:phpdoc_align
        'phpdoc_align' => [
            'align' => 'vertical',
            'tags' => [
                'param',
                'property',
                'property-read',
                'property-write',
                'phpstan-param',
                'phpstan-property',
                'phpstan-property-read',
                'phpstan-property-write',
                'phpstan-assert',
                'phpstan-assert-if-true',
                'phpstan-assert-if-false',
                'psalm-param',
                'psalm-param-out',
                'psalm-property',
                'psalm-property-read',
                'psalm-property-write',
                'psalm-assert',
                'psalm-assert-if-true',
                'psalm-assert-if-false'
            ],
        ],
        // Check types case - https://mlocati.github.io/php-cs-fixer-configurator/#version:3.90|fixer:phpdoc_types
        'phpdoc_types' => true,
        // Use native scalar types - https://mlocati.github.io/php-cs-fixer-configurator/#version:3.90|fixer:phpdoc_scalar
        'phpdoc_scalar' => true,
        // remove extra empty lines - https://mlocati.github.io/php-cs-fixer-configurator/#version:3.90|fixer:phpdoc_trim
        'phpdoc_trim' => true,
        // remove empty lines inside phpdoc block - https://mlocati.github.io/php-cs-fixer-configurator/#version:3.90|fixer:phpdoc_trim_consecutive_blank_line_separation
        'phpdoc_trim_consecutive_blank_line_separation' => true,
    ])
    ->setFinder($finder)
;
