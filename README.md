# PHP Performance Profiler

A lightweight, dependency-free **PHP static performance analyzer** for identifying potential performance problems, code complexity, database anti-patterns, and other issues in PHP applications.

> **Current version: v0.2.1**

The profiler analyzes PHP source code without executing the application.

---

## Features

### Code Metrics

- PHP file count
- PHP line count
- Largest PHP files
- Function analysis
- Class analysis
- Cyclomatic complexity
- Large function detection
- Large class detection

### Performance Analysis

Detects potential performance problems such as:

- Nested loops
- Database calls inside loops
- Possible N+1 query patterns
- Shell/process execution
- `eval()` usage
- Debugging statements in production code

### Health Score

Each project receives a performance score:

```text
100/100 = Excellent
90-99   = Good
70-89   = Needs Attention
0-69    = Critical
```

The score is based on detected performance and code-quality risks.

### Exclude Files and Directories

You can exclude specific directories or files using a single `--exclude` option.

Example:

```bash
php php-profiler analyze \
    --exclude=vendor,storage,composer.json,composer.lock \
    /path/to/project
```

Excluded directories are skipped recursively.

For example:

```text
--exclude=bamkocore_defaults
```

will exclude:

```text
bamkocore_defaults/
├── phpMailer/
│   ├── PHPMailer.php
│   ├── SMTP.php
│   └── ...
├── other.php
└── ...
```

You can also exclude individual files:

```bash
--exclude=.user.ini,.htaccess,composer.json
```

Multiple exclusions are separated by commas.

---

# Requirements

- PHP 8.0+
- Linux, macOS, or Windows
- PHP CLI

No Python is required.

No external runtime services are required.

The profiler uses PHP's native tokenizer and filesystem APIs.

---

# Installation

Clone the repository:

```bash
git clone https://github.com/YOUR-USERNAME/php-performance-profiler.git
```

Enter the project:

```bash
cd php-performance-profiler
```

Install dependencies if the project uses Composer:

```bash
composer install
```

Make the CLI executable on Linux/macOS:

```bash
chmod +x php-profiler
```

---

# Usage

## Basic Analysis

Analyze the current directory:

```bash
php php-profiler analyze .
```

Analyze a specific project:

```bash
php php-profiler analyze /var/www/html
```

Example:

```bash
php php-profiler analyze /samba/core/CORE_OPTIMIZATION/
```

---

# Excluding Files and Directories

Use:

```bash
--exclude=
```

### Exclude one directory

```bash
php php-profiler analyze \
    --exclude=vendor \
    /var/www/html
```

### Exclude multiple directories

```bash
php php-profiler analyze \
    --exclude=vendor,storage,cache \
    /var/www/html
```

### Exclude individual files

```bash
php php-profiler analyze \
    --exclude=.user.ini,.htaccess,composer.json,composer.lock \
    /var/www/html
```

### Mix directories and files

```bash
php php-profiler analyze \
    --exclude=vendor,storage,.vscode,.claude,.user.ini,.htaccess,composer.json,composer.lock \
    /var/www/html
```

### Real-world example

```bash
php php-profiler analyze \
    --exclude=vendor,php-performance-profiler-v0.2.0,bamkocore_uploads,bamkocore_libraries,bamkocore_assets,bamkocore_defaults,.vscode,.lh,.claude,tmp,.user.ini,.htaccess,composer.json,composer.lock \
    /samba/core/CORE_OPTIMIZATION/
```

---

# How Exclusions Work

The profiler uses relative paths from the project root.

For example:

```text
Project:
    /samba/core/CORE_OPTIMIZATION/

Excluded:
    bamkocore_defaults
```

The following are excluded:

```text
bamkocore_defaults/
bamkocore_defaults/test.php
bamkocore_defaults/phpMailer/
bamkocore_defaults/phpMailer/PHPMailer.php
bamkocore_defaults/phpMailer/SMTP.php
```

But a similarly named directory is not excluded:

```text
bamkocore_defaults_backup/
my_bamkocore_defaults/
```

Excluded directories are skipped during recursive traversal, so their child files are not analyzed.

---

# What Is Analyzed?

The profiler currently analyzes PHP files:

```text
*.php
```

For every PHP file it can inspect:

```text
Lines
Functions
Classes
Complexity
Loops
Database calls
Shell calls
eval()
Debug calls
```

---

# Performance Checks

## Large Functions

Functions larger than 100 lines are reported.

Example:

```text
Function: processOrders()
Lines: 245
```

Recommendation:

```text
Consider splitting responsibilities.
```

---

## Cyclomatic Complexity

Functions with complexity of 10 or greater are flagged.

Example:

```text
Function: generateReport()
Complexity: 17
```

This indicates that the function may be difficult to maintain and test.

---

## Nested Loops

The profiler detects functions containing nested loops.

Example:

```php
foreach ($users as $user) {
    foreach ($orders as $order) {
        // ...
    }
}
```

These patterns can create significant algorithmic complexity.

---

## Database Calls Inside Loops

The profiler identifies database calls inside functions containing loops.

Example:

```php
foreach ($users as $user) {
    $orders = $db->query(
        "SELECT * FROM orders WHERE user_id = {$user['id']}"
    );
}
```

This is reported as a possible N+1 query candidate.

> Detection is heuristic. The profiler reports candidates for investigation; it does not claim that every finding is definitely an N+1 query.

---

## Shell / Process Calls

The profiler detects:

```php
shell_exec()
exec()
system()
passthru()
proc_open()
```

These calls can block PHP workers and may have security and performance implications.

---

## eval()

The profiler detects:

```php
eval()
```

and reports it as a risk requiring review.

---

## Debugging Code

The profiler detects:

```php
var_dump()
print_r()
debug_print_backtrace()
```

These should generally not remain in production code.

---

# Example Output

A typical analysis looks like:

```text
PHP PERFORMANCE PROFILER
============================================

Project
--------------------------------------------
Path                 : /samba/core/CORE_OPTIMIZATION/
PHP Files            : 842
PHP Lines            : 185432

Largest File
--------------------------------------------
CoreController.php   : 4,821 lines

Performance
--------------------------------------------
SQL Calls            : 1,482
Shell Calls          : 7
eval() Calls         : 0
Debug Calls          : 14

Functions
--------------------------------------------
Large Functions      : 18
Complex Functions    : 27
Nested Loop Functions: 9
N+1 Candidates       : 12

Classes
--------------------------------------------
Large Classes        : 6

Score
--------------------------------------------
Performance Score    : 72/100

Recommendations
--------------------------------------------
18 function(s) exceed 100 lines.
27 function(s) have high cyclomatic complexity.
9 function(s) contain nested loops.
12 function(s) contain database calls inside loops.
6 class(es) exceed 500 lines.
Remove debugging output from production code.
```

---

# CLI Commands

### Analyze

```bash
php php-profiler analyze .
```

### Analyze a specific project

```bash
php php-profiler analyze /path/to/project
```

### Analyze with exclusions

```bash
php php-profiler analyze \
    --exclude=vendor,storage,cache \
    /path/to/project
```

### Help

```bash
php php-profiler help
```

or:

```bash
php php-profiler --help
```

### Version

```bash
php php-profiler --version
```

---

# Architecture

The project is designed to keep file discovery, analysis, and reporting separated.

```text
php-performance-profiler
│
├── CLI
│   └── php-profiler
│
├── Analyzer
│   ├── CodeMetricsAnalyzer
│   ├── Function Analyzer
│   └── Class Analyzer
│
├── Scanner
│   └── Recursive PHP File Scanner
│
├── Performance Detection
│   ├── SQL calls
│   ├── Nested loops
│   ├── N+1 candidates
│   ├── Shell execution
│   ├── eval()
│   └── Debug calls
│
└── Report
    ├── Text
    └── JSON
```

---

# Important: Static Analysis

`php-performance-profiler` is a **static analyzer**.

It does not execute your PHP application.

It analyzes source code to identify potential performance problems.

Therefore:

```text
Detected issue ≠ confirmed production bottleneck
```

For example, a database call inside a loop may be perfectly acceptable in some applications.

Use the findings as an investigation guide.

---

# Roadmap

## v0.2.0

- [x] PHP file scanning
- [x] Function analysis
- [x] Class analysis
- [x] Line metrics
- [x] Cyclomatic complexity
- [x] Large function detection
- [x] Large class detection
- [x] Nested loop detection
- [x] Database call detection
- [x] N+1 candidates
- [x] Shell/process detection
- [x] `eval()` detection
- [x] Debug code detection
- [x] Performance score
- [x] File exclusions
- [x] Directory exclusions
- [x] Recursive exclusion handling

## v0.3.0

Planned:

- [ ] Better SQL detection
- [ ] Improved N+1 analysis
- [ ] Query complexity detection
- [ ] More accurate function complexity
- [ ] Config file support
- [ ] JSON schema
- [ ] HTML report
- [ ] Top performance offenders
- [ ] Configurable thresholds

## Future

Potential features:

```text
Runtime profiling
MySQL query analysis
Laravel-specific analysis
Symfony-specific analysis
Framework-aware rules
CI/CD integration
GitHub Actions
Baseline comparison
Performance regression detection
```

---

# CI/CD Integration

The profiler is intended to eventually work in CI/CD pipelines.

Example future workflow:

```bash
php php-profiler analyze . --format=json
```

This can be integrated into:

```text
GitHub Actions
GitLab CI
Jenkins
Docker
Deployment pipelines
```

A future version will support configurable failure thresholds.

---

# Contributing

Contributions are welcome.

1. Fork the repository.
2. Create a feature branch.

```bash
git checkout -b feature/my-feature
```

3. Make your changes.
4. Add or update tests.
5. Run the test suite.
6. Submit a pull request.

---

# License

MIT License.

See [LICENSE](LICENSE) for details.

---

# Author

**Birender Rana**

PHP Developer / Senior Developer

---

## Project

Part of the **Health System** project.

```text
Health System
│
├── php-performance-profiler
│   └── PHP source-code performance analysis
│
└── php-server-health
    └── Linux/PHP server health monitoring
```

The two projects address different layers:

```text
PHP Application
       │
       ▼
php-performance-profiler
       │
       ▼
Code / Query / Complexity Analysis
       │
       ▼
PHP Runtime
       │
       ▼
php-server-health
       │
       ▼
Server / PHP-FPM / Apache / MySQL / Network
```
