# Check Specification

## 1. Purpose, Scope, and Non-Goals

This document provides the formal portability specification for the `studeo/check` static-analysis engine. It specifies the engine architecture, execution model, violation record format, baseline behavior, output schemas, and rule semantics required for an independent implementation to produce identical analysis findings.

The specification divides the system into three tiers:
- **Tier 1 (Core Contracts and Engine)**: Language-neutral concepts including source sets, class indices, routine representations, rule sets, baseline management, runners, and violation records.
- **Tier 2 (CAP 1 Architectural Rules)**: The six structural rules (`method-complexity`, `file-length`, `method-naming`, `step-count`, `nesting-depth`, `condition-extraction`). Their measures and judgments represent universal clean-code principles that port directly across programming languages.
- **Tier 3 (Laravel Convention Rules)**: The six framework convention rules (`action-shape`, `action-authorization`, `migration`, `system-action-usage`, `dto-shape`, `no-dto-in-livewire-state`). These rules are specific to PHP and Laravel application conventions and are documented here for completeness of the reference implementation.

Non-goals: This specification makes no commitment to provide or maintain alternative language implementations. Runtime execution of analyzed code is prohibited; all analysis is performed strictly on parsed Abstract Syntax Trees (AST). The principles behind these choices are in [`DESIGN.md`](DESIGN.md).

## 2. Specification Version

Specification version: `0.1.0` (draft).

During the `0.x` version line, any change to a rule identifier, the violation record structure, the JSON schema, the baseline key format, process exit codes, or an option name constitutes a change to this specification and must be recorded in `CHANGELOG.md` under a dedicated "Specification" section.

## 3. Conformance

An implementation conforms to this specification for a test fixture when the following match the golden fixture artifacts:
1. The `rule`, `file`, `line`, `subject`, and `severity` fields of every emitted violation in the JSON output.
2. The exact process exit code (`0`, `1`, or `2`).

A run with no findings is pinned by `spec/clean/passes`. The `message` text and the human-readable text output format are informative and do not govern machine conformance. Golden fixtures located in the `spec/` directory define the authoritative conformance test suite.

## 4. The Violation Record

Every analysis finding is represented as a Violation record containing six fields:

| Field | Type | Description |
| --- | --- | --- |
| `rule` | `string` | The permanent rule identifier or pseudo-rule name (`syntax`, `baseline`). |
| `file` | `string` | The relative display path of the file containing the finding. |
| `line` | `int` | The 1-indexed source line number where the violation begins. |
| `subject` | `string` | The target code construct identifier. |
| `severity` | `string` | The severity level: `"error"` or `"warning"`. |
| `message` | `string` | Human-readable explanatory text describing the finding. |

Severities:
- `error`: Represents an actionable policy violation that causes process failure.
- `warning`: Represents an advisory finding (such as exceeding target file length below the hard cap) that does not trigger process failure unless baselined.

Deterministic Ordering:
Violations collected across all executed rules are sorted using a stable comparison tuple:
`[file, line, rule, subject]` in ascending lexicographical order.

## 5. Output

Check produces two output formats selected by the `--format` option.

### Text Format

Violations are printed one per line, followed by a summary line:

```text
<file>:<line> [<rule>] <message>
<error_count> <error|errors>, <warning_count> <warning|warnings>.
```

Stale baseline entries in text output are formatted as:
```text
<file>:1 [baseline] Stale baseline entry for [<rule>] <subject>: no longer violated.
```

The summary line calculates errors as active error violations plus stale baseline entries:
- If errors equal 1, singular `error` is used; otherwise `errors`.
- If warnings equal 1, singular `warning` is used; otherwise `warnings`.

### JSON Schema

When invoked with `--format=json`, the output is a JSON array of violation objects formatted with pretty printing and unescaped slashes and unicode:

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

The indentation is not part of conformance.

Stale baseline entries in JSON format carry:
- `rule`: `"baseline"`
- `file`: the file path recorded in the baseline entry (or `"unknown"`)
- `line`: `1`
- `subject`: the subject recorded in the baseline entry (or `""`)
- `severity`: `"error"`
- `message`: `"Stale baseline entry for [<rule>]: no longer violated."`

## 6. Exit Codes

Check reports completion status through three standard exit codes:

Without `--baseline`:
- `0`: Success. No error-severity violations were found. Warnings alone do not cause exit code 1.
- `1`: Violations found. One or more error-severity violations exist.
- `2`: Command or configuration failure. The CLI encountered an invalid option, missing path, malformed `composer.json`, or invalid rule set configuration.

With `--baseline`:
- `0`: Success. All active violations (including warnings) are present in the baseline file, and no stale baseline entries exist.
- `1`: Violations found. One or more unbaselined violations of any severity exist, or one or more recorded baseline entries are no longer violated (stale baseline).
- `2`: Command or configuration failure.

## 7. The Baseline

The baseline mechanism allows adoption of rules on existing codebases without immediate failure.

### Key Format

A baseline key consists of three space-separated fields:
`<rule> <file> <subject>`

Example:
`method-naming app/Services/OrderService.php OrderService::process`

Limitation: Because keys are delimited by single spaces, file paths or subjects containing spaces break key parsing and are unsupported.

### File Format

The baseline file contains plain text entries, one key per line:
- Lines are trimmed of surrounding whitespace.
- Empty lines are discarded.
- Duplicate entries are removed.
- Entries are sorted alphabetically.
- The file ends with a trailing newline, or is empty (0 bytes) when no violations are baselined.

Updating the baseline with `--update-baseline --baseline=<file>` records all current violations (including warnings) to the specified path without writing to stdout and exits with code 0.

### Shrink-Only Behavior

Baselines can only shrink over time. When a previously baselined violation is fixed, the unused entry becomes a stale baseline violation reported under pseudo-rule `baseline` with line 1, severity `error`, and the original subject. This produces exit code 1 until the baseline file is regenerated. Conformance is pinned by `spec/baseline/suppresses-recorded-violation`, `spec/baseline/reports-new-violation` and `spec/baseline/reports-stale-entry`.

### Path Spelling

The baseline key uses the displayed file path. The displayed path, and so the baseline key, has no leading `./` whatever the spelling of the path argument (`.`, a directory, or an individual file). Relative paths have lexical `.` segments removed during path resolution. Conformance is pinned by golden fixtures `spec/baseline/suppresses-with-dot-argument` and `spec/method-naming/flags-dot-argument`. A directory-scoped rule applies to files under the scope base when `.` is the path argument, pinned by `spec/step-count/flags-dot-argument`.

## 8. Pseudo-Rules

Check defines two pseudo-rules emitted by engine components rather than rule classes:

### Syntax Pseudo-Rule
Emitted by the runner when a source file cannot be parsed into an AST. The line and the message come from the parser, so they depend on the parser used; `spec/syntax/flags-unparsable-file` pins the behaviour of the reference parser (the expected line is 6 for a 5-line file).
- Rule: `syntax`
- File: Display path of the unparsable file.
- Line: Start line of the parser error (minimum 1).
- Subject: `"file"`
- Severity: `error`
- Message: `does not parse: <parser_error>. Fix the syntax; no other rule can check this file until it parses.`

### Baseline Pseudo-Rule
Emitted when a baseline entry no longer matches any active violation.
- Rule: `baseline`
- File: Display path of the file from the baseline entry.
- Line: `1`
- Subject: Subject from the baseline entry.
- Severity: `error`
- Message: The JSON message is `Stale baseline entry for [<rule>]: no longer violated.` The text line is the one in Section 5.

## 9. Rule Identifiers and Option Schema

| Rule ID | Tier | Options with Type and Default | Default Scope | Severity |
| --- | --- | --- | --- | --- |
| `method-complexity` | 2 | `int $max = 2`<br>`array $scope = Scope::APP_CODE` | `Scope::APP_CODE` | `error` |
| `file-length` | 2 | `int $target = 230`<br>`int $hard = 300`<br>`array $scope = Scope::APP_CODE` | `Scope::APP_CODE` | `error` (hard)<br>`warning` (target) |
| `method-naming` | 2 | `array $banned = ['process', 'processData', 'handleData', 'doStuff', 'doWork']`<br>`array $scope = Scope::ALL` | `Scope::ALL` | `error` |
| `step-count` | 2 | `int $max = 8`<br>`array $scope = Scope::APP_CODE` | `Scope::APP_CODE` | `error` |
| `nesting-depth` | 2 | `int $max = 1`<br>`array $scope = Scope::APP_CODE` | `Scope::APP_CODE` | `error` |
| `condition-extraction` | 2 | `array $scope = Scope::APP_CODE` | `Scope::APP_CODE` | `error` |
| `action-shape` | 3 | `array $scope = ['Actions']` | `['Actions']` | `error` |
| `action-authorization` | 3 | `string $systemAttribute`<br>`array $scope = ['Actions']`<br>`string $attributionHint = 'carry attribution in an Input field'` | `['Actions']` | `error` |
| `migration` | 3 | `array $forbidden = ['\\Models\\', '\\Actions\\', '\\Data\\', '\\Enums\\']`<br>`array $scope = ['database/migrations']` | `['database/migrations']` | `error` |
| `system-action-usage` | 3 | `string $systemAttribute`<br>`array $forbiddenIn = ['Controllers', 'Middleware', 'ApiResources', 'Livewire']`<br>`array $scope = Scope::ALL` | `Scope::ALL` | `error` |
| `dto-shape` | 3 | `string $inputBase`<br>`string $outputBase`<br>`array $scope = ['Data']` | `['Data']` | `error` |
| `no-dto-in-livewire-state` | 3 | `string $inputBase`<br>`string $outputBase`<br>`array $scope = ['Livewire']` | `['Livewire']` | `error` |

`Scope::ALL` corresponds to `['.']`. `Scope::APP_CODE` corresponds to:
`['Actions', 'Commands', 'Concerns', 'Controllers', 'Data', 'Jobs', 'Listeners', 'Livewire', 'Middleware', 'Policies', 'Services']`.

## 10. Rules

### `method-complexity`
- **Intent**: Limits cyclomatic branching complexity by capping the number of conditional branches in a routine.
- **MEASURE**: Counts conditional branching statements (`if` and `elseif`). In PHP AST, counts `If_` and `ElseIf_` statement nodes within the routine body. Closures and anonymous functions are traversed and their conditional branches count toward the enclosing routine. Classes declared inside a routine body are distinct subjects and are not entered.
- **JUDGE**: Evaluates conditional count `$count` against threshold option `$max` (default: 2). If `$count <= $this->max`, no violation is emitted. If `$count > $this->max`, emits an error severity violation.
- **Subject Format**: Routine subject: `Class::method` for named class methods, `class@anonymous::method` for anonymous class methods, or `<functionName>()` for top-level functions.
- **Fixtures**: `spec/method-complexity/flags-elseif-chain`, `spec/method-complexity/flags-three-ifs`, `spec/method-complexity/passes-two-ifs`.

### `file-length`
- **Intent**: Enforces file length limits to keep source files concise and focused on a single responsibility.
- **MEASURE**: Counts total line count of the physical file. The line count is 0 for an empty file, otherwise the number of newline characters plus 1 if the file does not end with a trailing newline.
- **JUDGE**: Evaluates line count `$lines` against `$hard` and `$target` (defaults in the table of Section 9). If `$lines > $hard`, emits `error` severity; otherwise if `$lines > $target`, emits `warning` severity; otherwise no violation is emitted. A file of exactly `$hard` lines is a warning.
- **Subject Format**: `"file"`.
- **Fixtures**: `spec/file-length/flags-over-hard-cap`, `spec/file-length/passes-at-target`, `spec/file-length/warns-at-hard-cap`, `spec/file-length/warns-one-over-target`, `spec/file-length/warns-over-target`.

### `method-naming`
- **Intent**: Prohibits non-descriptive routine names that obscure domain intent.
- **MEASURE**: Checks the routine name against a list of banned identifiers (default: `process`, `processData`, `handleData`, `doStuff`, `doWork`). Inspects top-level functions and methods of named and anonymous classes.
- **JUDGE**: If the routine name matches any entry in `$banned`, emits an `error` severity violation.
- **Subject Format**: `Class::method`, `class@anonymous::method`, or `<functionName>()`.
- **Fixtures**: `spec/method-naming/flags-process`, `spec/method-naming/flags-function-and-anonymous-class`, `spec/method-naming/orders-by-file-then-line`.

### `step-count`
- **Intent**: Limits top-level statements directly in a routine or closure body to ensure routines read as concise pipelines.
- **MEASURE**: Counts top-level statements in the body of a routine or closure, ignoring empty/no-op statements (`Nop`). Nested control blocks (e.g. an entire `if` block with internal statements) count as a single top-level statement. Arrow functions are not counted. Closures are distinct subjects and their statements do not count toward the enclosing routine.
- **JUDGE**: Compares step count `$steps` against `$max` (default: 8). If `$steps > $this->max`, emits an `error` severity violation. If `$steps <= $this->max`, passes without violation.
- **Subject Format**: `Class::method`, `class@anonymous::method`, `Class::method{closure}` (one `{closure}` per nesting level), or `<functionName>(){closure}`.
- **Fixtures**: `spec/step-count/flags-closure-body`, `spec/step-count/flags-dot-argument`, `spec/step-count/flags-nine-statements`, `spec/step-count/passes-eight-statements`.

### `nesting-depth`
- **Intent**: Restricts nesting depth of control flow blocks to maintain readable linear logic.
- **MEASURE**: Measures the maximum nesting depth of control blocks within a routine or closure body. Control structures include `if`, `foreach`, `for`, `while`, `do-while`, `try-catch`, and `switch`. Branches (`else`, `elseif`, `catch`, `finally`) do not add additional depth levels. `match` is an expression and does not add block depth. Closures, arrow functions, and nested classes restart depth calculation at 0.
- **JUDGE**: Compares deepest block nesting `$depth` against `$max` (default: 1). If `$depth > $this->max`, emits an `error` severity violation. If `$depth <= $this->max`, passes without violation.
- **Subject Format**: Routine or closure subject (`Class::method`, `class@anonymous::method`, `Class::method{closure}`, `<functionName>()`, `<functionName>(){closure}`).
- **Fixtures**: `spec/nesting-depth/flags-depth-two`, `spec/nesting-depth/passes-depth-one`.

### `condition-extraction`
- **Intent**: Requires compound boolean logic to be extracted into named helper methods whose whole body is a single return statement.
- **MEASURE**: Scans for boolean logical operators (`&&`, `||`, `and`, `or`). In PHP AST, counts `BooleanAnd`, `BooleanOr`, `LogicalAnd`, `LogicalOr`. The exemption applies to a method or function whose whole body is one `return` statement; closures and arrow functions are not exempt.
- **JUDGE**: If any boolean operators occur outside a single-return helper routine, emits an `error` severity violation.
- **Subject Format**: `Class::method`, `class@anonymous::method`, or `<functionName>()`.
- **Fixtures**: `spec/condition-extraction/flags-boolean-operator`, `spec/condition-extraction/passes-single-return-helper`.

### `action-shape`
- **Intent**: Enforces structural consistency for Action classes in application architectures.
- **MEASURE**: Inspects concrete classes within the configured scope (default: `['Actions']`). Checks:
  1. Class is declared `final`.
  2. Class name ends with suffix `Action`.
  3. Class exposes exactly one public method (`__invoke`), excluding `__construct`.
  4. Class does not use the `AsInvokable` trait.
  5. Class does not implement the `ShouldQueue` interface.
  `AsInvokable` and `ShouldQueue` are matched by the last segment of the name, whatever its namespace.
- **JUDGE**: If any condition is violated, emits an `error` severity violation for each failure.
- **Subject Format**: Short class name (e.g. `UpdateUserAction`).
- **Fixtures**: `spec/action-shape/flags-extra-public-method`, `spec/action-shape/flags-non-final-action`.

### `action-authorization`
- **Intent**: Requires Action classes to either perform user authorization with an authenticatable actor or declare an explicit system attribute.
- **MEASURE**: Identifies whether the class carries the designated `$systemAttribute`.
  - For normal Actions: verifies that `__invoke()` declares a parameter typed with `Illuminate\Contracts\Auth\Authenticatable` and that the class contains a `Gate::forUser($actor)->authorize(...)` invocation.
  - For system Actions: verifies that the class does not take an authenticatable parameter, does not call `Gate::`, and does not invoke `->authorize()`.
- **JUDGE**: Emits an `error` severity violation if a normal Action lacks authorization components or if a system Action attempts authorization.
- **Subject Format**: Fully qualified class name (e.g. `App\Actions\CreateUserAction`).
- **Fixtures**: `spec/action-authorization/flags-missing-authorization`.

### `migration`
- **Intent**: Enforces that database migration files declare static schema structures without `enum()` columns or domain model dependencies.
- **MEASURE**: Inspects files in `database/migrations`. Detects:
  1. Method, nullsafe, or static calls named `enum` (case-insensitive).
  2. Class references (imports, type hints, instantiations, static calls) matching forbidden namespace fragments (default: `\Models\`, `\Actions\`, `\Data\`, `\Enums\`).
- **JUDGE**: Emits an `error` severity violation for each enum column call (subject: `"enum()"`) and each forbidden domain reference (subject: referenced class name).
- **Subject Format**: `"enum()"` or referenced class name (e.g. `App\Models\User`).
- **Fixtures**: `spec/migration/flags-domain-reference`, `spec/migration/flags-enum-column`.

### `system-action-usage`
- **Intent**: Prevents request-handling entry points from directly invoking or referencing system-only Actions.
- **MEASURE**: Identifies system Action classes (classes in `Actions/` marked with `$systemAttribute`). Inspects files within forbidden directories (default: `Controllers`, `Middleware`, `ApiResources`, `Livewire`) for any references to those system Actions.
- **JUDGE**: Emits an `error` severity violation if any forbidden file mentions a system Action class.
- **Subject Format**: Fully qualified name of the referenced system Action.
- **Fixtures**: `spec/system-action-usage/flags-controller-reference`.

### `dto-shape`
- **Intent**: Enforces architectural constraints on Data Transfer Objects (DTOs).
- **MEASURE**: Inspects concrete classes in the `Data` scope. Verifies:
  1. Class is declared `final` (the message advises `final readonly class`).
  2. When the class is not `readonly`, every declared property and every promoted constructor parameter must be `readonly`.
  3. Class name ends in `Input` (must extend `$inputBase` with a protected constructor) or `Data` (must extend `$outputBase`).
  4. Class does not implement `Livewire\Wireable`.
- **JUDGE**: Emits an `error` severity violation for each unmet condition.
- **Subject Format**: Short class name (e.g. `UserData`).
- **Fixtures**: `spec/dto-shape/flags-data-without-base`.

### `no-dto-in-livewire-state`
- **Intent**: Prevents Livewire components from exposing DTO instances in public properties that serialize to the client browser.
- **MEASURE**: Inspects public instance properties and public promoted constructor parameters of classes in the `Livewire` scope. Resolves property types to check if they match a known DTO (descendant of `$inputBase`, descendant of `$outputBase`, or residing in a `Data` namespace segment resolved via PSR-4 roots).
- **JUDGE**: Emits an `error` severity violation for each public property holding a DTO type.
- **Subject Format**: `Class::$property` (e.g. `UserProfile::$user`).
- **Fixtures**: `spec/no-dto-in-livewire-state/flags-dto-property`, `spec/no-dto-in-livewire-state/passes-plain-property`.

## 11. Fixtures

Golden fixtures located in `spec/` establish the concrete portability and conformance test suite.

### Fixture Layout

Each test case is organized in a self-contained directory: `spec/<group>/<case>/`.
Groups include rule identifiers, plus `clean`, `syntax`, and `baseline`.

Files within a case directory:
- `check.php.txt`: A configuration provider returning the rule set under test configured via static `options()`.
- Source files with `.php.txt` extension: Placed in required directory structures (e.g. `app/Services/ComplexityService.php.txt`, `database/migrations/...`).
- `composer.json` (optional): Required when testing PSR-4 namespace root resolution.
- `args` (optional): Single line specifying extra CLI options (e.g. `--baseline=baseline.txt`).
- `baseline.txt` (optional): Baseline file for baseline test cases.
- `expected.json`: Authoritative golden JSON output generated by the CLI.
- `expected.exit`: Single line recording the expected integer exit code.

### The `.php.txt` Convention

Fixture source files use the `.php.txt` extension so that static analysis tools, code style fixers, and composer class scanners run on the package itself ignore inert fixture code. When replaying fixtures, the replay tool copies the case directory to a temporary path and renames all `*.php.txt` files to `*.php`.

### CLI Execution and Replay

Fixtures are executed using the `spec/replay.sh` POSIX shell script. For each case:
1. Creates an isolated temporary directory outside the project tree.
2. Copies fixture files into the temporary directory.
3. Renames `*.php.txt` files to `*.php`.
4. Removes `expected.json` and `expected.exit` from the temporary copy.
5. Invokes `<php> <root>/bin/check --format=json $(cat args)` in the temporary directory.
6. Writes stdout and process exit code to temporary files outside the directory.
7. Compares actual stdout against `expected.json` via `cmp -s`.
8. Compares process exit code against `expected.exit`.
9. Prints `ok <group>/<case>` or `FAIL <group>/<case> <json|exit|json+exit>`.
10. Removes the temporary directory.

`replay.sh` compares stdout byte for byte (`cmp -s`), including `message` and indentation, because it is the reference implementation's regression check. A port's own harness parses the JSON and compares the fields of Section 3 and the exit code. Section 3 stays as it is.

Usage:
```bash
sh spec/replay.sh                    # Replay all cases
sh spec/replay.sh spec/<group>/<case> # Replay a single case
sh spec/replay.sh --record          # Re-record expected artifacts from current CLI
PHP=/usr/bin/php8.4 sh spec/replay.sh # Specify custom PHP binary
```

The `SpecFixturesTest` Pest test file executes `spec/replay.sh` for every fixture case within the standard test suite to guarantee continuous conformance on macOS and Linux environments. Expected fixture files must always be recorded from the real CLI with `--record` and reviewed in git diffs prior to committing.
