# Check API Reference

This document provides reference documentation for the `studeo/check` static-analysis engine, including core contracts, rule definitions, rule set profiles, CLI interfaces, and known operational behaviors.

## Table of Contents

<!-- generated:contents -->
- [1. Overview and Conventions](#1-overview-and-conventions)
- [2. Contracts](#2-contracts)
  - [Rule](#rule)
  - [FileRule](#filerule)
  - [SourceSet](#sourceset)
  - [PathSourceSet](#pathsourceset)
  - [SourceFile](#sourcefile)
  - [ClassIndex](#classindex)
  - [Routine](#routine)
  - [RuleSet](#ruleset)
  - [RuleOptions](#ruleoptions)
  - [Violation](#violation)
  - [Severity](#severity)
  - [Scope](#scope)
  - [CheckResult](#checkresult)
  - [Runner](#runner)
  - [Baseline](#baseline)
- [3. Rules](#3-rules)
  - [`method-complexity`](#method-complexity)
  - [`file-length`](#file-length)
  - [`method-naming`](#method-naming)
  - [`step-count`](#step-count)
  - [`nesting-depth`](#nesting-depth)
  - [`condition-extraction`](#condition-extraction)
  - [`action-shape`](#action-shape)
  - [`action-authorization`](#action-authorization)
  - [`migration`](#migration)
  - [`system-action-usage`](#system-action-usage)
  - [`dto-shape`](#dto-shape)
  - [`no-dto-in-livewire-state`](#no-dto-in-livewire-state)
  - [`syntax`](#syntax)
  - [`baseline`](#baseline-1)
- [4. Rule Sets and Profiles](#4-rule-sets-and-profiles)
  - [`Cap1Rules`](#cap1rules)
  - [`ActionRules`](#actionrules)
  - [`LaravelRules`](#laravelrules)
  - [`RuleSet::merge`](#rulesetmerge)
  - [Writing a Provider Class for `check.php`](#writing-a-provider-class-for-checkphp)
- [5. Command Line](#5-command-line)
  - [Usage](#usage)
  - [Arguments](#arguments)
  - [Options](#options)
  - [Path Rules](#path-rules)
  - [Rule Set Discovery Order](#rule-set-discovery-order)
  - [Reading `composer.json`](#reading-composerjson)
  - [Exit Codes and Stderr Messages](#exit-codes-and-stderr-messages)
  - [Text Output Format](#text-output-format)
  - [JSON Schema](#json-schema)
  - [Baseline File Format](#baseline-file-format)
- [6. Known Behaviour](#6-known-behaviour)
- [7. Writing a Rule](#7-writing-a-rule)
<!-- /generated:contents -->

---

## 1. Overview and Conventions

`studeo/check` is a static-analysis engine for Laravel projects. It parses PHP source files using `nikic/php-parser` into Abstract Syntax Trees (AST) and traverses class declarations, methods, statements, and expressions. The engine does not boot the Laravel framework or load application code into PHP runtime memory.

All classes reside under the namespace `Studeo\Check\`. Classes are immutable and `final readonly` where applicable. Rule classes implement `Studeo\Check\Engine\Rule` and are instantiated exclusively through a static `options()` factory method.

Violations emitted by the engine are represented as `Studeo\Check\Engine\Violation` objects with a stable signature: `file`, `line`, `rule`, `subject`, `severity`, and `message`.

---

## 2. Contracts

### Rule

Namespace: `Studeo\Check\Engine\Rule`

The core contract implemented by all analysis rules.

```php
namespace Studeo\Check\Engine;

interface Rule
{
    public function name(): string;

    /**
     * @return list<Violation>
     */
    public function violations(SourceSet $sources): array;
}
```

### FileRule

Namespace: `Studeo\Check\Rules\FileRule`

An abstract base class implementing `Rule` for rules that inspect each file within their designated scope independently.

```php
namespace Studeo\Check\Rules;

use Studeo\Check\Engine\Rule;
use Studeo\Check\Engine\Scope;
use Studeo\Check\Engine\Severity;
use Studeo\Check\Engine\SourceFile;
use Studeo\Check\Engine\SourceSet;
use Studeo\Check\Engine\Violation;

abstract class FileRule implements Rule
{
    /**
     * @param  list<string>  $scope  directories relative to source roots, or "." for the whole root
     */
    protected function __construct(protected readonly array $scope) {}

    public function violations(SourceSet $sources): array;

    /**
     * @return list<Violation>
     */
    abstract protected function inspect(SourceFile $file, SourceSet $sources): array;

    protected function violation(SourceFile $file, int $line, string $subject, string $message, Severity $severity = Severity::Error): Violation;
}
```

### SourceSet

Namespace: `Studeo\Check\Engine\SourceSet`

Represents the collection of source files being analyzed, providing access to parsed files, base paths, namespace prefix resolutions, and a shared class index.

```php
namespace Studeo\Check\Engine;

interface SourceSet
{
    public function basePath(): string;

    /**
     * @return list<string>
     */
    public function roots(): array;

    /**
     * @return list<SourceFile>
     */
    public function files(?Scope $scope = null): array;

    public function displayPath(string $absolute): string;

    /**
     * The named classes of the set's files by full name, built once.
     */
    public function classIndex(): ClassIndex;

    /**
     * The first namespace segment of a class name below the namespace root that owns it,
     * null when no root owns the name.
     */
    public function directoryOf(string $name): ?string;
}
```

### PathSourceSet

Namespace: `Studeo\Check\Engine\PathSourceSet`

Concrete implementation of `SourceSet` traversing file paths on disk.

```php
namespace Studeo\Check\Engine;

final class PathSourceSet implements SourceSet
{
    /**
     * @param  list<string>  $roots
     * @param  list<string>  $namespaceRoots  namespace prefixes with a trailing backslash
     */
    public function __construct(
        private readonly string $basePath,
        private readonly array $roots,
        private readonly ?string $scopeBase = null,
        private readonly array $namespaceRoots = [],
    );

    public function basePath(): string;
    public function roots(): array;
    public function files(?Scope $scope = null): array;
    public function displayPath(string $absolute): string;
    public function classIndex(): ClassIndex;
    public function directoryOf(string $name): ?string;
}
```

- `$basePath`: Absolute path to the repository or project root.
- `$roots`: List of absolute directory or file paths to inspect.
- `$scopeBase`: Optional directory path (typically `app/`) against which file paths are evaluated for scope filtering.
- `$namespaceRoots`: List of PSR-4 namespace prefixes (each ending with a trailing backslash, e.g. `['App\\']`) used to map fully qualified class names to top-level directories via `directoryOf()`.
- `directoryOf(string $name): ?string`: Identifies the first namespace segment under the owning namespace root. If `$namespaceRoots` is empty or no prefix matches, returns `null`.

### SourceFile

Namespace: `Studeo\Check\Engine\SourceFile`

Represents a single parsed PHP file. Names are resolved to fully qualified class names via `PhpParser\NodeVisitor\NameResolver`.

```php
namespace Studeo\Check\Engine;

use PhpParser\Error;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Parser;

final class SourceFile
{
    public function __construct(
        public readonly string $relativePath,
        public readonly string $displayPath,
        private readonly string $absolutePath,
        private readonly Parser $parser,
    );

    public function lineCount(): int;
    public function syntaxError(): ?Error;

    /**
     * @return list<Stmt> empty when the file does not parse
     */
    public function ast(): array;

    /**
     * Every method and top-level function in the order they appear.
     * @return list<Routine>
     */
    public function routines(): array;

    /**
     * The routines plus every closure body, each closure named after the routine it sits in.
     * @return list<Routine>
     */
    public function subjects(): array;

    /**
     * @return list<Class_> named classes that are not abstract
     */
    public function concreteClasses(): array;

    /**
     * @return list<Class_> every class, abstract and anonymous ones included
     */
    public function classes(): array;

    /**
     * Every class name the file mentions, imports included, with the first line it appears on.
     * @return array<string, int>
     */
    public function references(): array;
}
```

### ClassIndex

Namespace: `Studeo\Check\Engine\ClassIndex`

An index of all named classes discovered across the source set's parsed files, allowing rules to inspect inheritance hierarchies without loading classes.

```php
namespace Studeo\Check\Engine;

use PhpParser\Node\Stmt\ClassMethod;

final class ClassIndex
{
    /**
     * @param  list<SourceFile>  $files
     */
    public static function of(array $files): self;

    public function descendsFrom(string $name, string $base): bool;

    /**
     * The nearest constructor of the class or of a parent defined in the source set.
     */
    public function constructorOf(string $name): ?ClassMethod;
}
```

### Routine

Namespace: `Studeo\Check\Engine\Routine`

Represents a method, function, or closure body with a stable subject label for reporting.

```php
namespace Studeo\Check\Engine;

use PhpParser\Node\FunctionLike;

final readonly class Routine
{
    public function __construct(
        public string $subject,
        public string $name,
        public FunctionLike $node,
    );

    public function line(): int;
}
```

Subject labeling conventions:
- Class method: `Class::method`
- Anonymous class method: `class@anonymous::method`
- Top-level function: `<functionName>()` (for example `doStuff()`)
- Nested closure: `Class::method{closure}` (appends `{closure}` per nesting level)

### RuleSet

Namespace: `Studeo\Check\Engine\RuleSet`

An immutable collection of uniquely typed rules.

```php
namespace Studeo\Check\Engine;

final readonly class RuleSet
{
    /**
     * @param  Rule  ...$rules  each built with a rule class's options(); a class may appear once
     */
    public static function of(mixed ...$rules): self;

    /**
     * @return list<Rule>
     */
    public function rules(): array;

    /**
     * Return a new rule set holding this set's rules followed by the rules of each other set.
     */
    public function merge(self ...$others): self;
}
```

`RuleSet::of()` enforces two invariants:
1. Every argument must implement `Rule`; otherwise throws `InvalidRuleConfigurationException::notARule($rule)`.
2. Each rule class may appear at most once; duplicate classes throw `InvalidRuleConfigurationException::duplicate($class)`.

### RuleOptions

Namespace: `Studeo\Check\Engine\RuleOptions`

Validation helpers used within `options()` factory methods. Each helper throws `InvalidRuleConfigurationException::option($rule, $option, $problem)` on validation failure.

```php
namespace Studeo\Check\Engine;

final class RuleOptions
{
    public static function positive(string $rule, string $option, int $value): int;

    /**
     * @param  array<int|string, mixed>  $values
     * @return list<string> the entries with surrounding whitespace removed
     */
    public static function names(string $rule, string $option, array $values): array;

    /**
     * @param  array<int|string, mixed>  $values
     * @return list<string> single directory names, never paths
     */
    public static function directories(string $rule, string $option, array $values): array;

    /**
     * @param  array<int|string, mixed>  $scope
     * @return list<string>
     */
    public static function scope(string $rule, array $scope): array;

    public static function className(string $rule, string $option, string $value): string;

    public static function text(string $rule, string $option, string $value): string;
}
```

Validation error messages:
- `positive`: `"must be a positive integer."`
- `names`: `"must not be empty."` or `"must hold non-empty strings only."`
- `directories`: `"must hold directory names, not paths."`
- `className`: `"must be a fully qualified class name without a leading backslash."`
- `text`: `"must not be empty."`

Behavioral notes:
- `RuleOptions::names` trims surrounding whitespace from each entry (`trim(...)`).
- `RuleOptions::scope` trims whitespace and directory separators (`/`) from each entry (`trim($directory, " \t\n\r/")`) before delegating to `names`.

### Violation

Namespace: `Studeo\Check\Engine\Violation`

Represents an individual analysis finding.

```php
namespace Studeo\Check\Engine;

final readonly class Violation
{
    public function __construct(
        public string $rule,
        public string $file,
        public int $line,
        public string $subject,
        public Severity $severity,
        public string $message,
    );

    /**
     * @return array{rule: string, file: string, line: int, subject: string, severity: string, message: string}
     */
    public function toArray(): array;
}
```

### Severity

Namespace: `Studeo\Check\Engine\Severity`

Enumeration of violation severities.

```php
namespace Studeo\Check\Engine;

enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
}
```

### Scope

Namespace: `Studeo\Check\Engine\Scope`

Defines path-filtering patterns for rules.

```php
namespace Studeo\Check\Engine;

final readonly class Scope
{
    public const ALL = ['.'];

    public const APP_CODE = [
        'Actions',
        'Commands',
        'Concerns',
        'Controllers',
        'Data',
        'Jobs',
        'Listeners',
        'Livewire',
        'Middleware',
        'Policies',
        'Services',
    ];

    /**
     * @param  list<string>  $patterns
     */
    public function __construct(
        public array $patterns = self::ALL,
    );

    public static function all(): self;
    public static function appCode(): self;
    public function matches(string $relativePath): bool;
}
```

Matching semantics:
- If a pattern contains glob characters (`*`, `?`, `[`), matching uses PHP's `fnmatch($pattern, $relativePath)`.
- Otherwise, a pattern matches if `$pattern === '.'` or `$relativePath` begins with `$pattern . '/'`.

### CheckResult

Namespace: `Studeo\Check\Engine\CheckResult`

Encapsulates analysis findings and determines process exit status.

```php
namespace Studeo\Check\Engine;

final readonly class CheckResult
{
    /**
     * @param  list<Violation>  $violations  ordered by file, line and rule
     * @param  list<string>  $staleBaseline  baseline entries that are no longer violated
     */
    public function __construct(
        public array $violations,
        public array $staleBaseline = [],
        public bool $hasBaseline = false,
    );

    public function errorCount(): int;
    public function warningCount(): int;
    public function failed(): bool;
}
```

- `errorCount()`: Returns total errors plus count of stale baseline entries.
- `failed()`: When a baseline is present, returns `true` if any active violations or stale entries exist. Without a baseline, returns `true` if `count(Severity::Error) > 0`.

### Runner

Namespace: `Studeo\Check\Engine\Runner`

Orchestrates rule execution across a `SourceSet`, collecting syntax errors and rule violations.

```php
namespace Studeo\Check\Engine;

final readonly class Runner
{
    public function __construct(
        private RuleSet $rules,
        private ?Baseline $baseline = null,
    );

    public function run(SourceSet $sources): CheckResult;
}
```

Violations are deterministically ordered by:
```php
[$a->file, $a->line, $a->rule, $a->subject] <=> [$b->file, $b->line, $b->rule, $b->subject];
```

### Baseline

Namespace: `Studeo\Check\Engine\Baseline`

Manages suppression of known violations and detection of stale baseline entries.

```php
namespace Studeo\Check\Engine;

final readonly class Baseline
{
    /**
     * @param  list<string>  $entries  sorted unique "rule file subject"
     */
    public function __construct(public array $entries = []);

    public static function fromFile(string $path): self;

    /**
     * @param  list<Violation>  $violations
     */
    public static function fromViolations(array $violations): self;

    public function key(Violation $violation): string;

    /**
     * @param  list<Violation>  $violations
     * @return array{new: list<string>, stale: list<string>}
     */
    public function differences(array $violations): array;

    /**
     * @param  list<string>  $actualKeys
     * @return array{new: list<string>, stale: list<string>}
     */
    public function diffKeys(array $actualKeys): array;

    /**
     * @param  list<Violation>  $violations
     * @return array{active: list<Violation>, stale: list<string>}
     */
    public function apply(array $violations): array;

    public function save(string $path): void;
}
```

A baseline key is formatted as `{$violation->rule} {$violation->file} {$violation->subject}`.

Behavioral notes:
- `Baseline::fromFile` trims whitespace, drops blank lines, de-duplicates entries, and sorts them alphabetically.
- Running with `--update-baseline` generates and saves the baseline file without printing to stdout and exits with code 0.

---

## 3. Rules

The examples were produced in a Laravel-style skeleton whose `check.php` returns the provider class of section 4, unless the line above the example says otherwise.

### `method-complexity`

- **Intent**: Limits cyclomatic complexity by capping the number of `if` and `elseif` branching statements in routines (methods and top-level functions). Closures are not their own subjects; their `if` and `elseif` statements are counted in the enclosing routine.
- **Subject**: Routine subject (`Class::method`, `class@anonymous::method`, or `<functionName>()`)
- **Options signature**:
  ```php
  public static function options(int $max = 2, array $scope = Scope::APP_CODE): self
  ```
- **Validation errors**:
  - `$max < 1`: `Rule [method-complexity] option [max] must be a positive integer.`
  - Invalid `$scope`: `Rule [method-complexity] option [scope] must not be empty.` / `must hold non-empty strings only.`
- **Default scope**: `Scope::APP_CODE`
- **Message template**:
  ```text
  {$count} if/elseif statements (max {$this->max}). Use an early return, match(), or extract a named helper.
  ```
- **Severity**: `Severity::Error`
- **Example output**:
  Command: `vendor/bin/check`
  check.php: the provider of section 4
  ```text
  app/Services/ComplexityService.php:7 [method-complexity] 3 if/elseif statements (max 2). Use an early return, match(), or extract a named helper.
  1 error, 0 warnings.
  ```

### `file-length`

- **Intent**: Enforces file length limits, emitting a warning when exceeding target lines and an error when exceeding the hard cap.
- **Subject**: `file` (for both target threshold warning and hard cap error)
- **Options signature**:
  ```php
  public static function options(int $target = 230, int $hard = 300, array $scope = Scope::APP_CODE): self
  ```
- **Validation errors**:
  - `$target < 1`: `Rule [file-length] option [target] must be a positive integer.`
  - `$hard < 1`: `Rule [file-length] option [hard] must be a positive integer.`
  - `$target > $hard`: `Rule [file-length] option [target] must not be above hard ({$hard}).`
- **Default scope**: `Scope::APP_CODE`
- **Message templates**:
  - Hard cap exceeded:
    ```text
    {$lines} lines exceeds the hard cap of {$this->hard}. Split by responsibility.
    ```
  - Target exceeded:
    ```text
    {$lines} lines exceeds the {$this->target}-line target. Extract a collaborator or helper class.
    ```
- **Severity**: `Severity::Error` (hard cap); `Severity::Warning` (target threshold)
- **Example output**:
  Command: `vendor/bin/check`
  check.php: the provider of section 4
  ```text
  app/Services/LongService.php:1 [file-length] 307 lines exceeds the hard cap of 300. Split by responsibility.
  1 error, 0 warnings.
  ```

### `method-naming`

- **Intent**: Flags vague method names that do not communicate business intent or domain context.
- **Subject**: Routine subject (`Class::method`, `class@anonymous::method`, or `<functionName>()`)
- **Options signature**:
  ```php
  public static function options(array $banned = ['process', 'processData', 'handleData', 'doStuff', 'doWork'], array $scope = Scope::ALL): self
  ```
- **Validation errors**:
  - Empty or invalid `$banned`: `Rule [method-naming] option [banned] must not be empty.` / `must hold non-empty strings only.`
- **Default scope**: `Scope::ALL` (`['.']`)
- **Message template**:
  ```text
  method name '{$name}' says nothing about intent. Use context-rich phrasing such as sanitizeNameParameter() or verifyActiveSubscriptionStatus().
  ```
- **Severity**: `Severity::Error`
- **Example output**:
  Command: `vendor/bin/check`
  check.php: the provider of section 4
  ```text
  app/Services/OrderService.php:7 [method-naming] method name 'process' says nothing about intent. Use context-rich phrasing such as sanitizeNameParameter() or verifyActiveSubscriptionStatus().
  1 error, 0 warnings.
  ```

### `step-count`

- **Intent**: Restricts the number of top-level statements directly in a method, function, or closure body to ensure routines read as concise pipelines. Unlike `method-complexity`, closures are treated as distinct subjects.
- **Subject**: Routine or closure subject (`Class::method`, `class@anonymous::method`, `Class::method{closure}`, `<functionName>()`, `<functionName>(){closure}`)
- **Options signature**:
  ```php
  public static function options(int $max = self::DEFAULT_MAX, array $scope = Scope::APP_CODE): self
  ```
- **Validation errors**:
  - `$max < 1`: `Rule [step-count] option [max] must be a positive integer.`
- **Default scope**: `Scope::APP_CODE`
- **Message template**:
  ```text
  {$steps} statements (max {$this->max}). Group steps into named private helpers so the method reads as a short pipeline.
  ```
- **Severity**: `Severity::Error`
- **Example output**:
  Command: `vendor/bin/check`
  check.php: the provider of section 4
  ```text
  app/Services/BusyService.php:7 [step-count] 10 statements (max 8). Group steps into named private helpers so the method reads as a short pipeline.
  1 error, 0 warnings.
  ```

### `nesting-depth`

- **Intent**: Restricts nesting of control structures (loops, conditionals, try-catch, switch) to maintain readable flow. Closures are treated as distinct subjects.
- **Subject**: Routine or closure subject (`Class::method`, `class@anonymous::method`, `Class::method{closure}`, `<functionName>()`, `<functionName>(){closure}`)
- **Options signature**:
  ```php
  public static function options(int $max = 1, array $scope = Scope::APP_CODE): self
  ```
- **Validation errors**:
  - `$max < 1`: `Rule [nesting-depth] option [max] must be a positive integer.`
- **Default scope**: `Scope::APP_CODE`
- **Message template**:
  ```text
  control blocks nest to depth {$depth} (max {$this->max}). Move the inner block into a named helper.
  ```
- **Severity**: `Severity::Error`
- **Example output**:
  Command: `vendor/bin/check`
  check.php: the provider of section 4
  ```text
  app/Services/NestedService.php:7 [nesting-depth] control blocks nest to depth 2 (max 1). Move the inner block into a named helper.
  1 error, 0 warnings.
  ```

### `condition-extraction`

- **Intent**: Ensures boolean logical operators (`&&`, `||`, `and`, `or`) appear only in methods or functions whose whole body is a single `return` statement.
- **Subject**: Routine subject (`Class::method`, `class@anonymous::method`, or `<functionName>()`)
- **Options signature**:
  ```php
  public static function options(array $scope = Scope::APP_CODE): self
  ```
- **Validation errors**:
  - Invalid `$scope`: `Rule [condition-extraction] option [scope] must not be empty.` / `must hold non-empty strings only.`
- **Default scope**: `Scope::APP_CODE`
- **Message template**:
  ```text
  {$count} boolean {$noun} outside a one-line helper. Move the expression into a private method named for what it means, such as hasActiveSubscription(), whose body is return …;
  ```
  (`$noun` is `operator` or `operators`)
- **Severity**: `Severity::Error`
- **Example output**:
  Command: `vendor/bin/check`
  check.php: the provider of section 4
  ```text
  app/Services/ConditionService.php:7 [condition-extraction] 1 boolean operator outside a one-line helper. Move the expression into a private method named for what it means, such as hasActiveSubscription(), whose body is return …;
  1 error, 0 warnings.
  ```

### `action-shape`

- **Intent**: Verifies that Action classes are final, named with the `Action` suffix, and expose only `__invoke()` as their public entry point.
- **Subject**: Class short name (for example `UpdateUserAction`)
- **Options signature**:
  ```php
  public static function options(array $scope = ['Actions']): self
  ```
- **Validation errors**:
  - Invalid `$scope`: `Rule [action-shape] option [scope] must not be empty.`
- **Default scope**: `['Actions']`
- **Message templates**:
  - `Actions must be final classes. Declare it \`final class\`.`
  - `class name must end with 'Action'. Rename it, for example CreatePostAction.`
  - `Actions expose exactly one public method, __invoke(); found: <methods>. Make the others private or move them to another Action.`
  - `must not use AsInvokable; Actions are plain __invoke() classes. Remove the trait.`
  - `Actions must not implement ShouldQueue. Remove it and dispatch the Action from a Job.`
- **Severity**: `Severity::Error`
- **Example output**:
  Command: `vendor/bin/check`
  check.php: the provider of section 4
  ```text
  app/Actions/UpdateUserAction.php:7 [action-shape] Actions must be final classes. Declare it `final class`.
  1 error, 0 warnings.
  ```

### `action-authorization`

- **Intent**: Verifies that Actions perform user authorization via an authenticatable actor and `Gate::forUser($actor)->authorize(...)`, or declare a system attribute when intended to run without an actor.
- **Subject**: Namespaced class name (for example `App\Actions\CreateUserAction`)
- **Options signature**:
  ```php
  public static function options(
      string $systemAttribute,
      array $scope = ['Actions'],
      string $attributionHint = 'carry attribution in an Input field',
  ): self
  ```
- **Validation errors**:
  - `$systemAttribute`: `Rule [action-authorization] option [systemAttribute] must be a fully qualified class name without a leading backslash.`
  - `$attributionHint`: `Rule [action-authorization] option [attributionHint] must not be empty.`
- **Default scope**: `['Actions']`
- **Message templates**:
  - Normal Action missing authorization:
    ```text
    an Action must authorize but is missing <missing>. Add them, or mark the Action #[<Attribute>] if it must run without an actor.
    ```
    `<missing>` is composed of: `an Authenticatable $actor parameter on __invoke()` and/or `a Gate::forUser($actor)->authorize(...) call` (joined by ` and `).
  - System Action attempting authorization:
    ```text
    a system Action does no authorization of its own but <present>. Remove the authorization and <hint>, or remove #[<Attribute>] to make it an authorizing Action.
    ```
    `<present>` is composed of: `takes an Authenticatable parameter`, `calls Gate::`, and/or `calls ->authorize()` (joined by ` and `).
    `<hint>` is the `attributionHint` option (default: `carry attribution in an Input field`).
    `<Attribute>` is the short class name of `systemAttribute` (for example `SystemAction`).
- **Severity**: `Severity::Error`
- **Example output**:
  Command: `vendor/bin/check`
  check.php: the provider of section 4
  ```text
  app/Actions/CreateUserAction.php:5 [action-authorization] an Action must authorize but is missing an Authenticatable $actor parameter on __invoke() and a Gate::forUser($actor)->authorize(...) call. Add them, or mark the Action #[SystemAction] if it must run without an actor.
  1 error, 0 warnings.
  ```

### `migration`

- **Intent**: Prevents migrations from using `enum()` column definitions or referencing domain classes (models, actions, DTOs, enums).
- **Subject**: `enum()` or the referenced domain class name (for example `App\Models\User`)
- **Options signature**:
  ```php
  public static function options(
      array $forbidden = ['\\Models\\', '\\Actions\\', '\\Data\\', '\\Enums\\'],
      array $scope = ['database/migrations'],
  ): self
  ```
- **Validation errors**:
  - Invalid `$forbidden` / `$scope`: `Rule [migration] option [forbidden] must not be empty.` / `must hold non-empty strings only.`
- **Default scope**: `['database/migrations']`
- **Path scoping note**: When `vendor/bin/check` runs without path arguments, it scans only `app/` by default, so migration files are not scanned. Because directory roots resolve paths relative to that directory (for example `vendor/bin/check database/migrations` yields `2026_01_01_000000_create_users_table.php`), passing directory arguments like `database/migrations` or `database` does not match the rule's `database/migrations` scope. The `migration` rule fires only when the migration file is passed explicitly as a file path argument (`vendor/bin/check database/migrations/2026_01_01_000000_create_users_table.php`) or when the project root is scanned (`vendor/bin/check .`).
- **Message templates**:
  - `uses ->enum(). Use ->string('column', 30)->index() with a backed enum cast.`
  - `references domain class {$name}. Migrations must not use models, actions, data objects or enums; write the values out.`
- **Severity**: `Severity::Error`
- **Example output**:
  Command: `vendor/bin/check database/migrations/2026_01_01_000000_create_users_table.php`
  check.php: the provider of section 4
  ```text
  database/migrations/2026_01_01_000000_create_users_table.php:12 [migration] uses ->enum(). Use ->string('column', 30)->index() with a backed enum cast.
  1 error, 0 warnings.
  ```

### `system-action-usage`

- **Intent**: Restricts system-attributed Actions from being referenced (imported or used) from request entry points (Controllers, Middleware, ApiResources, Livewire). Recognizes system Actions only in a directory named `Actions`.
- **Subject**: The Action's full class name (for example `App\Actions\PurgeUsersAction`)
- **Options signature**:
  ```php
  public static function options(
      string $systemAttribute,
      array $forbiddenIn = ['Controllers', 'Middleware', 'ApiResources', 'Livewire'],
      array $scope = Scope::ALL,
  ): self
  ```
- **Validation errors**:
  - `$systemAttribute`: `Rule [system-action-usage] option [systemAttribute] must be a fully qualified class name without a leading backslash.`
  - `$forbiddenIn`: `Rule [system-action-usage] option [forbiddenIn] must hold directory names, not paths.`
- **Default scope**: `Scope::ALL` (`['.']`)
- **Message template**:
  ```text
  references the system Action {$action->name}. System Actions are callable only from Actions, Listeners and Jobs. Call an authorizing Action instead, or move this call into a Job or Listener.
  ```
- **Severity**: `Severity::Error`
- **Example output**:
  Command: `vendor/bin/check`
  check.php: the provider of section 4
  ```text
  app/Controllers/UserController.php:5 [system-action-usage] references the system Action App\Actions\PurgeUsersAction. System Actions are callable only from Actions, Listeners and Jobs. Call an authorizing Action instead, or move this call into a Job or Listener.
  1 error, 0 warnings.
  ```

### `dto-shape`

- **Intent**: Enforces DTO architectural constraints: classes must be final readonly, properties readonly, named `*Input` extending inputBase with protected constructors or `*Data` extending outputBase, and never implementing Wireable.
- **Subject**: Class short name (for example `UserData`)
- **Options signature**:
  ```php
  public static function options(
      string $inputBase,
      string $outputBase,
      array $scope = ['Data'],
  ): self
  ```
- **Validation errors**:
  - `$inputBase` / `$outputBase`: `Rule [dto-shape] option [<option>] must be a fully qualified class name without a leading backslash.`
- **Default scope**: `['Data']`
- **Message templates**:
  - `DTO classes must be final. Declare it \`final readonly class\`.`
  - `DTO properties must be readonly; mutable: $<name1>, $<name2>. Declare the class readonly.` (the list holds every mutable property)
  - `the name must end in Input or Data: *Input for a class extending <base-short>, *Data for one extending <base-short>. Rename it, for example <name>Data.`
  - `is named *<suffix> but extends <other-short>. Rename it to end in <expected-suffix> or extend <expected-short>.`
  - `*<suffix> classes must extend <base-full>. Add \`extends <base-short>\`.`
  - `an Input constructor must be protected so the only way in is fromValidated(). Declare \`protected function __construct(...)\`.`
  - `DTOs are never Wireable. Remove Livewire\Wireable; hold plain snake_case state in the component and build the Input in toInput().`
- **Severity**: `Severity::Error`
- **Example output**:
  Command: `vendor/bin/check`
  check.php: the provider of section 4
  ```text
  app/Data/UserData.php:5 [dto-shape] *Data classes must extend App\Data\BaseData. Add `extends BaseData`.
  1 error, 0 warnings.
  ```

### `no-dto-in-livewire-state`

- **Intent**: Prevents Livewire components from holding DTOs in public state properties to ensure browser-transported state remains scalar or array data.
- **Subject**: `Class::$variable` (for example `UserProfile::$user`)
- **Options signature**:
  ```php
  public static function options(
      string $inputBase,
      string $outputBase,
      array $scope = ['Livewire'],
  ): self
  ```
- **Validation errors**:
  - `$inputBase` / `$outputBase`: `Rule [no-dto-in-livewire-state] option [<option>] must be a fully qualified class name without a leading backslash.`
- **Default scope**: `['Livewire']`
- **Namespace root note**: The `Data` directory check requires `App\` to be declared in `autoload.psr-4` in `composer.json`.
- **Message template**:
  ```text
  public property \${$member->variable} is typed {$dtos}, a DTO. Hold plain snake_case state (strings, numbers, arrays) and build the Input in toInput().
  ```
- **Severity**: `Severity::Error`
- **Example output**:
  Command: `vendor/bin/check`
  check.php: the provider of section 4
  ```text
  app/Livewire/UserProfile.php:9 [no-dto-in-livewire-state] public property $user is typed App\Data\UserData, a DTO. Hold plain snake_case state (strings, numbers, arrays) and build the Input in toInput().
  1 error, 0 warnings.
  ```

### `syntax`

- **Intent**: Pseudo-rule emitted when a source file cannot be parsed by `nikic/php-parser`.
- **Subject**: `file`
- **Message template**:
  ```text
  does not parse: {$error->getRawMessage()}. Fix the syntax; no other rule can check this file until it parses.
  ```
- **Severity**: `Severity::Error`
- **Example output**:
  Command: `vendor/bin/check`
  ```text
  app/Services/BrokenService.php:6 [syntax] does not parse: Syntax error, unexpected EOF. Fix the syntax; no other rule can check this file until it parses.
  1 error, 0 warnings.
  ```

### `baseline`

- **Intent**: Pseudo-rule emitted when an entry in the baseline file no longer matches any active violation.
- **Subject**: The subject of the original violation being baselined
- **Message template**:
  - Text output:
    ```text
    {$file}:1 [baseline] Stale baseline entry for [{$rule}] {$subject}: no longer violated.
    ```
  - JSON output:
    ```text
    Stale baseline entry for [{$parts[0]}]: no longer violated.
    ```
- **Severity**: `Severity::Error`
- **Example output**:
  Command: `vendor/bin/check --baseline=baseline.txt`
  ```text
  app/Services/OrderService.php:1 [baseline] Stale baseline entry for [method-naming] OrderService::process: no longer violated.
  1 error, 0 warnings.
  ```

---

## 4. Rule Sets and Profiles

Check organizes rules into profiles:

### `Cap1Rules`

Namespace: `Studeo\Check\Profiles\Cap1Rules`

Holds the six core CAP 1 rules configured with their default options:
- `MethodComplexity::options()`
- `FileLength::options()`
- `MethodNaming::options()`
- `StepCount::options()`
- `NestingDepth::options()`
- `ConditionExtraction::options()`

Factory method:
```php
public static function make(): self
```

`Cap1Rules` has a public parameterless constructor. It can be invoked directly from the CLI via `--rules=Studeo\Check\Profiles\Cap1Rules`.

### `ActionRules`

Namespace: `Studeo\Check\Profiles\ActionRules`

Holds the three Action-related rules:
- `ActionShape::options()`
- `SystemActionUsage::options(systemAttribute: $this->systemAttribute)`
- `ActionAuthorization`: when `$this->attributionHint === null`, configured via `ActionAuthorization::options(systemAttribute: $this->systemAttribute)` without passing an attribution hint; when `$this->attributionHint !== null`, configured via `ActionAuthorization::options(systemAttribute: $this->systemAttribute, attributionHint: $this->attributionHint)`. Passing `null` allows the rule's default hint (`carry attribution in an Input field`) to apply.

Factory method:
```php
public static function make(string $systemAttribute, ?string $attributionHint = null): self
```

`ActionRules` has a `private` constructor requiring parameters. It cannot be directly specified in `--rules` (doing so causes CLI failure with exit code 2). It must be composed inside a `check.php` configuration provider.

### `LaravelRules`

Namespace: `Studeo\Check\Profiles\LaravelRules`

Holds the three Laravel convention rules:
- `DtoShape::options(inputBase: $this->inputBase, outputBase: $this->outputBase)`
- `Migration::options()`
- `NoDtoInLivewireState::options(inputBase: $this->inputBase, outputBase: $this->outputBase)`

Factory method:
```php
public static function make(string $inputBase, string $outputBase): self
```

`LaravelRules` has a `private` constructor requiring parameters. It cannot be directly specified in `--rules`. It must be configured inside a `check.php` configuration provider.

### `RuleSet::merge`

Rule sets are composed by calling the instance method `$ruleSet->merge(self ...$others)` (signature `public function merge(self ...$others): self`). Merging returns a new `RuleSet` holding the current set's rules followed by each other set's rules, preserving order and enforcing unique class constraints.

### Writing a Provider Class for `check.php`

To configure profiles that require constructor options or combine multiple rule sets, create a `check.php` file in the project root returning the provider class name:

```php
<?php

use Studeo\Check\Engine\RuleSet;
use Studeo\Check\Profiles\ActionRules;
use Studeo\Check\Profiles\Cap1Rules;
use Studeo\Check\Profiles\LaravelRules;

final class AppCheckRules
{
    public function rules(): RuleSet
    {
        return Cap1Rules::make()->rules()->merge(
            ActionRules::make(
                systemAttribute: 'App\Attributes\SystemAction',
                attributionHint: 'carry attribution in an Input field',
            )->rules(),
            LaravelRules::make(
                inputBase: 'App\Data\BaseInput',
                outputBase: 'App\Data\BaseData',
            )->rules(),
        );
    }
}

return AppCheckRules::class;
```

---

## 5. Command Line

### Usage

```text
vendor/bin/check [options] [--] [<paths>...]
```

### Arguments

- `paths`: Optional array of file or directory paths to analyze. When omitted, Check resolves the `app/` directory if present; otherwise, it resolves the current working directory.

### Options

- `--rules`: Fully qualified rule set provider class name.
- `--baseline`: Path to the baseline file.
- `--update-baseline`: Update or generate the baseline file at `--baseline`.
- `--format`: Output format: `text` (default) or `json`.

### Path Rules

- When no paths are supplied, Check inspects `app/` if it exists on disk, establishing `app/` as the scope base. If `app/` does not exist, Check analyzes the repository base path.
- With no path arguments, the only analyzed root is `app/` (or the base directory). Consequently, Check does not scan `database/migrations` by default. Passing directory arguments such as `database/migrations` or `database` resolves entry relative paths relative to that directory, so they do not start with `database/migrations/` and do not fire. The `migration` rule fires only when the migration file is passed as an explicit file argument or when the project root is scanned (`vendor/bin/check .`).
- When paths are explicitly given, each path is resolved relative to the base directory, though absolute paths are accepted as given. Paths must exist on disk.
- Directory roots recursively collect `*.php` files while skipping `vendor`, `node_modules`, and `*.blade.php` files.
- File paths below the scope base receive relative paths evaluated against the scope base; files outside the scope base retain paths relative to their directory root or project display path.

### Rule Set Discovery Order

Check determines the active rule set in this order:
1. `--rules=<class>` option.
2. `check.php` in the project root returning a provider class name.
3. Default `Studeo\Check\Profiles\Cap1Rules`.

### Reading `composer.json`

Check reads `composer.json` from the project base directory on every execution to discover PSR-4 namespace roots from both `autoload.psr-4` and `autoload-dev.psr-4`.

- Only keys ending with a single trailing backslash (`Namespace\`) are recognized as valid namespace roots. Keys with inner backslashes but no trailing backslash (such as `App\Sub`) or with double trailing backslashes (`Namespace\\`) are ignored.
- If `composer.json` contains malformed JSON or a top-level JSON array instead of an object, Check exits with code 2.

### Exit Codes and Stderr Messages

- `0`: Success (no violations found, or all active violations are suppressed by the baseline). Warnings alone do not cause exit code 1:
  Command: `vendor/bin/check`
  ```text
  app/Services/BigService.php:1 [file-length] 252 lines exceeds the 230-line target. Extract a collaborator or helper class.
  0 errors, 1 warning.
  ```
  When all violations (including warnings) are baselined:
  Command: `vendor/bin/check --baseline=b.txt`
  ```text
  0 errors, 0 warnings.
  ```
- `1`: Violations found. Without `--baseline`, exit 1 occurs only when an error-severity violation exists, while warnings alone do not affect the exit code. With `--baseline`, any remaining violation of any severity, or any stale baseline entry, produces exit 1:
  Command: `vendor/bin/check --baseline=b.txt`
  ```text
  app/Services/BigService.php:1 [baseline] Stale baseline entry for [file-length] file: no longer violated.
  1 error, 0 warnings.
  ```
- `2`: Command or configuration failure. The command catches exceptions and writes their message to stderr before exiting with code 2. The following exact errors are produced by the command and its engine components:
  - Invalid format:
    ```text
    The --format option must be text or json, got [<value>].
    ```
  - Missing baseline with update flag:
    ```text
    --update-baseline requires --baseline=<file>.
    ```
  - Baseline file not found:
    ```text
    Baseline file [<path>] was not found.
    ```
    Command: `vendor/bin/check --baseline=missing.txt`
    ```text
    Baseline file [missing.txt] was not found.
    ```
  - Baseline file unreadable:
    ```text
    Unable to read baseline file: <path>
    ```
  - Path does not exist:
    ```text
    Path [<path>] does not exist.
    ```
  - Malformed composer.json:
    ```text
    File [composer.json] is not valid JSON: Syntax error.
    ```
  - composer.json not holding an object:
    ```text
    File [composer.json] must hold a JSON object.
    ```
  - Missing rule set class:
    ```text
    Rule set class [<class>] does not exist.
    ```
  - Missing rules() method:
    ```text
    Class [<class>] is not a rule set: missing rules() method.
    ```
    Command: `vendor/bin/check` (with `check.php` containing `<?php return stdClass::class;`)
    ```text
    Class [stdClass] is not a rule set: missing rules() method.
    ```
  - Invalid return type from rules():
    ```text
    Class [<class>] rules() must return a RuleSet.
    ```
  - Duplicate rule in rule set:
    ```text
    Rule class <class> is listed twice in the rule set. List each rule class once and configure it through its options().
    ```
    Command: `vendor/bin/check`
    ```text
    Rule class Studeo\Check\Rules\StepCount is listed twice in the rule set. List each rule class once and configure it through its options().
    ```
  - Non-rule entry in rule set:
    ```text
    A rule set holds rules built with a rule class's options(); got <type>.
    ```
  - Invalid rule option configuration:
    ```text
    Rule [<rule>] option [<option>] <problem>
    ```
  - Profile requiring constructor options passed to `--rules`:
    ```text
    Call to private Studeo\Check\Profiles\ActionRules::__construct() from scope Studeo\Check\Console\RuleSetResolver
    ```

### Text Output Format

Violations are printed one per line, followed by a summary:

```text
app/Services/OrderService.php:7 [method-naming] method name 'process' says nothing about intent. Use context-rich phrasing such as sanitizeNameParameter() or verifyActiveSubscriptionStatus().
1 error, 0 warnings.
```

### JSON Schema

When running with `--format=json`, output is a formatted JSON array of violation objects:

```json
[
    {
        "rule": "method-naming",
        "file": "app/Services/OrderService.php",
        "line": 7,
        "subject": "OrderService::process",
        "severity": "error",
        "message": "method name 'process' says nothing about intent. Use context-rich phrasing such as sanitizeNameParameter() or verifyActiveSubscriptionStatus()."
    }
]
```

Fields:
- `rule` (`string`): The rule identifier or pseudo-rule (`syntax`, `baseline`).
- `file` (`string`): Relative display path of the file.
- `line` (`int`): 1-indexed starting line number of the violation.
- `subject` (`string`): The subject name (`Class::method`, `class@anonymous::method`, `Class::method{closure}`, `<functionName>()`, `<functionName>(){closure}`, `file`, class short name, namespaced class name, `enum()`, referenced class name, or `Class::$variable`).
- `severity` (`string`): `"error"` or `"warning"`.
- `message` (`string`): Explanatory finding text.

### Baseline File Format

The baseline file contains one entry per line, sorted alphabetically:

```text
method-naming app/Services/OrderService.php OrderService::process
step-count app/Services/OrderService.php OrderService::process
```

Fields are space-separated: `<rule> <file> <subject>`. Running with `--update-baseline` generates this format sorted alphabetically, without writing to stdout, and exits with code 0. `Baseline::fromFile` trims surrounding whitespace, drops blank lines, de-duplicates entries, and sorts them. Stale entries that are no longer violated trigger exit code 1 under the `baseline` pseudo-rule.

---

## 6. Known Behaviour

- `Cap1Rules` on a tree without an `app/` directory scans the repository base path, and its default scope names (`Actions`, `Services`, ...) are matched against paths relative to that root: a top-level `Services/` directory (such as `Services/BusyService.php`) is checked, while directories nested under other prefixes (such as `src/Services/BusyService.php`) are not checked; a file that does not parse is still reported as `syntax`.
- A baseline key is space separated, so a path or subject containing a space breaks the key.
- `check lib` and `check lib/Foo.php` can scope differently. Inspecting a directory root resolves entry relative paths relative to that root (`Foo.php`), whereas specifying a file path resolves relative paths against the project display path (`lib/Foo.php`).
- `check.php` must return a rule-set class name (an object, array, `false` or empty string gives exit 2). The class must implement `rules(): RuleSet`.
- `composer.json` is read on every run even for `Cap1Rules`, and an invalid file gives exit 2.
- Without PSR-4 roots in `composer.json`, the `Data` directory check of `no-dto-in-livewire-state` does not fire.
- `action-shape` matches `AsInvokable` and `ShouldQueue` by the last segment of the name only, regardless of namespace.
- `method-complexity` counts a named function declared inside a method twice: once as part of the enclosing method and once as its own routine.
- Glob ranges such as `[A-Z]` in scope patterns depend on libc and locale, because scope globs are evaluated with PHP's `fnmatch()`.
- `system-action-usage` recognises system Actions only in a directory named `Actions`; a system attribute on a class elsewhere is ignored.
- Spelling of paths in display paths and baseline keys is the same for `.`, a directory and a file argument. `CheckCommand::resolvePath()` removes lexical `.` segments from relative path arguments, so `check .`, `check lib` and `check lib/Foo.php` print `lib/Foo.php` and never `./lib/Foo.php`, and a baseline key never starts with `./`. A baseline holds the files that were checked: a baseline recorded with `.` holds the keys of every file under the project, so checking only a subdirectory against it reports the other keys as stale. Check a baseline against the same paths it was recorded from:
  Command: `vendor/bin/check --update-baseline --baseline=b-dot.txt .`
  ```text
  ==> b-dot.txt <==
  step-count app/Services/StepService.php StepService::run
  ```
  Command: `vendor/bin/check --baseline=b-dot.txt lib`
  ```text
  app/Services/StepService.php:1 [baseline] Stale baseline entry for [step-count] StepService::run: no longer violated.
  1 error, 0 warnings.
  ```

---

## 7. Writing a Rule

A rule is a `final` class that implements `Studeo\Check\Engine\Rule`, or extends `Studeo\Check\Rules\FileRule` when it inspects each file in its scope on its own. The smallest complete example is `Rules/MethodNaming.php`.

```php
final class MethodNaming extends FileRule
{
    public const NAME = 'method-naming';

    private function __construct(array $scope, private readonly array $banned)
    {
        parent::__construct($scope);
    }

    public static function options(array $banned = ['process', 'doWork'], array $scope = Scope::ALL): self
    {
        return new self(RuleOptions::scope(self::NAME, $scope), RuleOptions::names(self::NAME, 'banned', $banned));
    }

    public function name(): string
    {
        return self::NAME;
    }

    protected function inspect(SourceFile $file, SourceSet $sources): array
    {
        // measure the file, then return Violation objects built with $this->violation(...)
    }
}
```

- **Identifier.** `NAME` is lowercase kebab-case and permanent: baseline keys contain it.
- **Construction.** The constructor is private. `options()` takes named arguments with neutral defaults and validates each with a `RuleOptions` helper, which throws `InvalidRuleConfigurationException` with a message naming the rule and option. A project-specific name (a marker attribute, a base class) is a required option, never a default.
- **Measure and judge.** Count or detect in one step, compare with the option thresholds in another, and report through `$this->violation($file, $line, $subject, $message, $severity)`.
- **Registration.** Add the rule to a profile, or build a rule set with `RuleSet::of(...)` in your own provider class (section 4). A rule class may appear once per rule set; compose rule sets with `merge()`, do not subclass a concrete rule.
- **Tests.** Tests are Pest unit tests and need no framework. Cover a flagged case, a clean case, each option, and the boundary of every threshold.
- **Conformance.** Add golden fixtures under `spec/<rule-id>/<case>/` and record them with `sh spec/replay.sh --record`, then review the generated files before keeping them. The fixture layout is in [`SPEC.md`](SPEC.md), Section 11.
- **Documentation.** List the rule in the README table, in the option table and rule list of `SPEC.md`, and in section 3 of this document.
