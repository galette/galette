<?php

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/galette/lib',
        __DIR__ . '/galette/includes',
        //__DIR__ . '/galette/install',
    ])
;

return (new PhpCsFixer\Config())
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setCacheFile(sys_get_temp_dir() . '/php-cs-fixer.galette.cache')
    ->setRules([
        '@PSR12' => true,
        '@PER-CS' => true,
        '@PHP82Migration' => true,
        'trailing_comma_in_multiline' => false,
        'cast_spaces' => false,
        'single_line_empty_body' => false
    ])
    ->setFinder($finder)
;
