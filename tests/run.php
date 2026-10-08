<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Analyzer/CodeMetricsAnalyzer.php';
require dirname(__DIR__) . '/src/Analyzer/ProjectAnalyzer.php';

use Birender\PhpPerformanceProfiler\Analyzer\ProjectAnalyzer;

$report = (new ProjectAnalyzer())->analyze(__DIR__ . '/fixtures');

assert(count($report['functions']) === 2);
assert(count($report['nested_loop_functions']) === 1);
assert(count($report['n_plus_one_candidates']) === 1);
assert($report['score'] < 100);

fwrite(STDOUT, "v0.2 fixture tests passed.\n");
