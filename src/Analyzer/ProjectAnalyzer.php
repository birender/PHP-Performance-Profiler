<?php

declare(strict_types=1);

namespace Birender\PhpPerformanceProfiler\Analyzer;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class ProjectAnalyzer
{

    private function getRelativePath(string $basePath, string $path): string
    {
        $basePath = rtrim(
            str_replace('\\', '/', realpath($basePath)),
            '/'
        );

        $path = str_replace('\\', '/', $path);

        if (str_starts_with($path, $basePath . '/')) {
            return substr($path, strlen($basePath) + 1);
        }

        return basename($path);
    }
    public function analyze(string $path, string $format, array $excludes = []): array
    {
        $path = realpath($path);
        if ($path === false || !is_dir($path)) {
            throw new \InvalidArgumentException('Invalid project directory.');
        }

        $metricsAnalyzer = new CodeMetricsAnalyzer();
        $phpFiles = 0;
        $phpLines = 0;
        $sqlCalls = $shellExec = $eval = $debugCalls = 0;
        $largestFile = ['name' => 'N/A', 'lines' => 0];
        $functions = [];
        $classes = [];
        $nestedLoopFunctions = [];
        $nPlusOneCandidates = [];

        $directoryIterator = new RecursiveDirectoryIterator(
            $path,
            \FilesystemIterator::SKIP_DOTS
        );

        $iterator = new RecursiveIteratorIterator(
            $directoryIterator,
            RecursiveIteratorIterator::SELF_FIRST
        );
        //
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php')
                continue;
            $filename = $file->getPathname();
            if (str_contains($filename, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $relativePath = $this->getRelativePath($path, $file->getPathname());

             if ($this->isExcluded($relativePath, $excludes)) {
                if ($file->isDir()) {
                    $iterator->skipChildren();
                }
                continue;
            }

            $content = @file_get_contents($filename);
            if ($content === false)
                continue;

            $lines = substr_count($content, "\n") + 1;
            $phpFiles++;
            $phpLines += $lines;
            if ($lines > $largestFile['lines'])
                $largestFile = ['name' => $filename, 'lines' => $lines];

            $sqlCalls += preg_match_all('/\b(mysqli_query|mysqli::query|PDO::query|->query\s*\(|->exec\s*\(|execute\s*\()/i', $content) ?: 0;
            $shellExec += preg_match_all('/\b(shell_exec|exec|system|passthru|proc_open)\s*\(/i', $content) ?: 0;
            $eval += preg_match_all('/\beval\s*\(/i', $content) ?: 0;
            $debugCalls += preg_match_all('/\b(var_dump|print_r|debug_print_backtrace|pr|console.log)\s*\(/i', $content) ?: 0;

            $metrics = $metricsAnalyzer->analyze($filename, $content);
            foreach ($metrics['functions'] as $function) {
                $function['file'] = $filename;
                $functions[] = $function;
                if ($function['max_loop_depth'] >= 2)
                    $nestedLoopFunctions[] = $function;
                if ($function['db_calls_in_loop'] > 0)
                    $nPlusOneCandidates[] = $function;
            }
            foreach ($metrics['classes'] as $class) {
                $class['file'] = $filename;
                $classes[] = $class;
            }
        }

        $largeFunctions = array_values(array_filter($functions, fn(array $f): bool => $f['lines'] > 100));
        $complexFunctions = array_values(array_filter($functions, fn(array $f): bool => $f['complexity'] >= 10));
        $largeClasses = array_values(array_filter($classes, fn(array $c): bool => $c['lines'] > 500));

        $penalties = min(25, count($largeFunctions) * 2)
            + min(20, count($complexFunctions) * 2)
            + min(15, count($nestedLoopFunctions) * 3)
            + min(15, count($nPlusOneCandidates) * 3)
            + min(10, $debugCalls)
            + min(10, $shellExec * 2)
            + min(5, $eval * 5)
            + min(10, count($largeClasses));

        $score = max(0, 100 - $penalties);
        $recommendations = [];

        if ($largeFunctions)
            $recommendations[] = count($largeFunctions) . ' function(s) exceed 100 lines; consider splitting responsibilities.';
        if ($complexFunctions)
            $recommendations[] = count($complexFunctions) . ' function(s) have high cyclomatic complexity (10+).';
        if ($nestedLoopFunctions)
            $recommendations[] = count($nestedLoopFunctions) . ' function(s) contain nested loops; review algorithmic complexity.';
        if ($nPlusOneCandidates)
            $recommendations[] = count($nPlusOneCandidates) . ' function(s) contain database calls inside loops; investigate possible N+1 query patterns.';
        if ($largeClasses)
            $recommendations[] = count($largeClasses) . ' class(es) exceed 500 lines; consider separating responsibilities.';
        if ($debugCalls)
            $recommendations[] = 'Remove debugging output such as var_dump(),print_r(),pr(),console.log() from production code.';
        if ($shellExec)
            $recommendations[] = 'Review shell/process calls because they can block PHP workers.';
        if ($eval)
            $recommendations[] = 'Review eval() usage carefully; it is generally unsuitable for production code.';
        if (!$recommendations)
            $recommendations[] = 'No major static performance red flags were detected.';

        return [
            'path' => $path,
            'php_files' => $phpFiles,
            'php_lines' => $phpLines,
            'largest_file' => basename($largestFile['name']) . " ({$largestFile['lines']} lines)",
            'sql_calls' => $sqlCalls,
            'shell_exec' => $shellExec,
            'eval' => $eval,
            'debug_calls' => $debugCalls,
            'functions' => $functions,
            'classes' => $classes,
            'large_functions' => $largeFunctions,
            'complex_functions' => $complexFunctions,
            'large_classes' => $largeClasses,
            'nested_loop_functions' => $nestedLoopFunctions,
            'n_plus_one_candidates' => $nPlusOneCandidates,
            'score' => $score,
            'recommendations' => $recommendations,
        ];
    }

    private function isExcluded(string $relativePath, array $excludes): bool
    {
        $relativePath = trim(
            str_replace('\\', '/', $relativePath),
            '/'
        );

        foreach ($excludes as $exclude) {
            $exclude = trim(
                str_replace('\\', '/', $exclude),
                '/'
            );

            if ($relativePath === $exclude) {
                return true;
            }

            if (str_starts_with($relativePath, $exclude . '/')) {
                return true;
            }
        }

        return false;
    }
}
