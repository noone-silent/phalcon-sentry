<?php

$finder = (new PhpCsFixer\Finder())
    ->in(['src/', 'tests/']);

return (new PhpCsFixer\Config())
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setRules(
        [
            '@PSR12'       => true,
            'array_syntax' => ['syntax' => 'short'],
        ]
    )
    ->setRiskyAllowed(true)
    ->setUsingCache(false)
    ->setFinder($finder);
