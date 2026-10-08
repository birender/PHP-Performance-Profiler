<?php

declare(strict_types=1);

namespace Birender\PhpPerformanceProfiler\Analyzer;

final class CodeMetricsAnalyzer
{
    public function analyze(string $filename, string $code): array
    {
        $tokens = token_get_all($code);
        $functions = [];
        $classes = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_FUNCTION) {
                $open = $this->findNextChar($tokens, $i, '{');
                if ($open === null) continue;
                $close = $this->matchingBrace($tokens, $open);
                if ($close === null) continue;

                $name = $this->nextFunctionName($tokens, $i) ?? 'closure';
                $slice = array_slice($tokens, $open, $close - $open + 1);
                $metrics = $this->metrics($slice);
                $functions[] = array_merge(
                    [
                        'name' => $name,
                        'start_line' => $token[2],
                        'lines' => max(1, $this->tokenEndLine($slice) - $token[2] + 1),
                    ],
                    $metrics
                );
            }

            if (in_array($token[0], [T_CLASS, T_INTERFACE] + (defined('T_TRAIT') ? [T_TRAIT] : []), true)) {
                $open = $this->findNextChar($tokens, $i, '{');
                if ($open === null) continue;
                $close = $this->matchingBrace($tokens, $open);
                if ($close === null) continue;
                $classes[] = [
                    'name' => $this->nextName($tokens, $i) ?? 'anonymous',
                    'start_line' => $token[2],
                    'lines' => max(1, $this->tokenEndLine(array_slice($tokens, $open, $close - $open + 1)) - $token[2] + 1),
                ];
            }
        }

        usort($functions, fn(array $a, array $b): int => $b['complexity'] <=> $a['complexity']);
        usort($classes, fn(array $a, array $b): int => $b['lines'] <=> $a['lines']);

        return ['functions' => $functions, 'classes' => $classes];
    }

    private function metrics(array $tokens): array
    {
        $complexity = 1;
        $loopDepth = 0;
        $maxLoopDepth = 0;
        $loops = 0;
        $dbCalls = 0;
        $dbCallsInLoop = 0;
        $braceDepth = 0;
        $loopBraces = [];

        foreach ($tokens as $token) {
            $id = is_array($token) ? $token[0] : null;
            $text = is_array($token) ? $token[1] : $token;

            if ($this->isComplexityToken($id, $text)) $complexity++;
            if ($this->isDatabaseCall($id, $text)) {
                $dbCalls++;
                if ($loopDepth > 0) $dbCallsInLoop++;
            }

            if ($this->isLoopToken($id, $text)) {
                $loops++;
                $loopDepth++;
                $maxLoopDepth = max($maxLoopDepth, $loopDepth);
                $loopBraces[] = null;
            }

            if ($text === '{') {
                $braceDepth++;
                if ($loopDepth > 0 && $loopBraces !== []) {
                    $last = count($loopBraces) - 1;
                    if ($loopBraces[$last] === null) $loopBraces[$last] = $braceDepth;
                }
            }

            if ($text === '}') {
                foreach (array_keys($loopBraces) as $idx) {
                    if ($loopBraces[$idx] === $braceDepth) {
                        array_splice($loopBraces, $idx, 1);
                        $loopDepth = max(0, $loopDepth - 1);
                        break;
                    }
                }
                $braceDepth--;
            }
        }

        return [
            'complexity' => $complexity,
            'loops' => $loops,
            'max_loop_depth' => $maxLoopDepth,
            'db_calls' => $dbCalls,
            'db_calls_in_loop' => $dbCallsInLoop,
        ];
    }

    private function isComplexityToken(?int $id, string $text): bool
    {
        return in_array($id, [T_IF, T_ELSEIF, T_FOR, T_FOREACH, T_WHILE, T_CASE, T_CATCH], true)
            || $text === '&&' || $text === '||' || $text === '?';
    }

    private function isLoopToken(?int $id, string $text): bool
    {
        return in_array($id, [T_FOR, T_FOREACH, T_WHILE], true) || $text === 'do';
    }

    private function isDatabaseCall(?int $id, string $text): bool
    {
        return $id === T_STRING && preg_match('/^(mysqli_query|pg_query|mysql_query|query|execute|exec)$/i', $text) === 1;
    }

    private function findNextChar(array $tokens, int $start, string $char): ?int
    {
        for ($i = $start + 1, $n = count($tokens); $i < $n; $i++) {
            if ($tokens[$i] === $char) return $i;
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_FUNCTION) return null;
        }
        return null;
    }

    private function matchingBrace(array $tokens, int $open): ?int
    {
        $depth = 0;
        for ($i = $open, $n = count($tokens); $i < $n; $i++) {
            if ($tokens[$i] === '{') $depth++;
            elseif ($tokens[$i] === '}' && --$depth === 0) return $i;
        }
        return null;
    }

    private function nextFunctionName(array $tokens, int $index): ?string
    {
        for ($i = $index + 1, $n = min(count($tokens), $index + 15); $i < $n; $i++) {
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) return $tokens[$i][1];
            if ($tokens[$i] === '(') return null;
        }
        return null;
    }

    private function nextName(array $tokens, int $index): ?string
    {
        for ($i = $index + 1, $n = min(count($tokens), $index + 10); $i < $n; $i++) {
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) return $tokens[$i][1];
            if ($tokens[$i] === '{') return null;
        }
        return null;
    }

    private function tokenEndLine(array $tokens): int
    {
        $line = 1;
        foreach ($tokens as $token) {
            if (is_array($token)) $line = $token[2] + substr_count($token[1], "\n");
            else $line += substr_count($token, "\n");
        }
        return $line;
    }
}
