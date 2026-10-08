<?php

declare(strict_types=1);

namespace Birender\PhpPerformanceProfiler;

use Birender\PhpPerformanceProfiler\Analyzer\ProjectAnalyzer;
use Throwable;

final class Application
{
    public function run(array $argv): int
    {
        $command = $argv[1] ?? 'help';

        if ($command === 'analyze') {
            $path = getcwd();
            $format = 'text';
            $excludes = [];

            for ($i = 2; $i < count($argv); $i++) {
                $arg = $argv[$i];

                // --exclude=vendor,storage,file.php
                if (str_starts_with($arg, '--exclude=')) {
                    $value = substr($arg, strlen('--exclude='));

                    $excludes = array_merge(
                        $excludes,
                        array_filter(
                            array_map('trim', explode(',', $value))
                        )
                    );

                    continue;
                }

                // --format=json
                if (str_starts_with($arg, '--format=')) {
                    $format = substr($arg, strlen('--format='));
                    continue;
                }

                // Normal argument = project path
                if (!str_starts_with($arg, '--')) {
                    $path = $arg;
                }
            }

            return $this->analyze($path, $format, $excludes);
        }

        return match ($command) {
            '--help', '-h', 'help' => $this->help(),
            '--version', '-V' => $this->version(),
            default => $this->unknown($command),
        };
    }

    private function analyze(string $path, string $format,array $excludes): int
    {
        try {
            $report = (new ProjectAnalyzer())->analyze($path,$format,$excludes);
        } catch (Throwable $e) {
            fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
            return 1;
        }

        if ($format === '--json' || $format === 'json') {
            echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            return 0;
        }

        echo PHP_EOL . 'PHP Performance Profiler v0.2.0' . PHP_EOL;
        echo str_repeat('=', 40) . PHP_EOL;
        echo 'Project: ' . $report['path'] . PHP_EOL;
        echo PHP_EOL . 'Performance Score' . PHP_EOL;
        echo '  ' . $report['score'] . '/100' . PHP_EOL;

        echo PHP_EOL . 'Project' . PHP_EOL;
        echo '  PHP files          : ' . $report['php_files'] . PHP_EOL;
        echo '  PHP lines          : ' . $report['php_lines'] . PHP_EOL;
        echo '  Largest file       : ' . $report['largest_file'] . PHP_EOL;

        echo PHP_EOL . 'Code Analysis' . PHP_EOL;
        echo '  Functions          : ' . count($report['functions']) . PHP_EOL;
        echo '  Large functions    : ' . count($report['large_functions']) . PHP_EOL;
        echo '  High complexity    : ' . count($report['complex_functions']) . PHP_EOL;
        echo '  Large classes      : ' . count($report['large_classes']) . PHP_EOL;
        echo '  Nested loops       : ' . count($report['nested_loop_functions']) . PHP_EOL;
        echo '  N+1 candidates     : ' . count($report['n_plus_one_candidates']) . PHP_EOL;

        echo PHP_EOL . 'Existing Checks' . PHP_EOL;
        echo '  SQL-like calls     : ' . $report['sql_calls'] . PHP_EOL;
        echo '  shell/process calls: ' . $report['shell_exec'] . PHP_EOL;
        echo '  eval()             : ' . $report['eval'] . PHP_EOL;
        echo '  debug functions    : ' . $report['debug_calls'] . PHP_EOL;

        if ($report['complex_functions']) {
            echo PHP_EOL . 'Top Complex Functions' . PHP_EOL;
            foreach (array_slice($report['complex_functions'], 0, 5) as $function) {
                echo '  - ' . basename($function['file']) . '::' . $function['name']
                    . ' (complexity ' . $function['complexity'] . ', line ' . $function['start_line'] . ')' . PHP_EOL;
            }
        }

        if ($report['large_functions']) {
            echo PHP_EOL . 'Large Functions' . PHP_EOL;
            foreach (array_slice($report['large_functions'], 0, 5) as $function) {
                echo '  - ' . basename($function['file']) . '::' . $function['name']
                    . ' (' . $function['lines'] . ' lines)' . PHP_EOL;
            }
        }

        echo PHP_EOL . 'Recommendations' . PHP_EOL;
        foreach ($report['recommendations'] as $recommendation) {
            echo '  - ' . $recommendation . PHP_EOL;
        }

        echo PHP_EOL;
        return 0;
    }

    private function help(): int
    {
        echo <<<TEXT
PHP Performance Profiler

Usage:
  php-profiler analyze [path]
  php-profiler analyze [path] --json
  php-profiler --help
  php-profiler --version

Commands:
  analyze    Analyze a PHP project for potential performance issues.
TEXT;
        return 0;
    }

    private function version(): int
    {
        echo 'PHP Performance Profiler 0.2.0' . PHP_EOL;
        return 0;
    }

    private function unknown(string $command): int
    {
        fwrite(STDERR, 'Unknown command: ' . $command . PHP_EOL);
        return 1;
    }
}
