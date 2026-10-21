# Check

A static-analysis engine for Laravel projects that inspects code structure and architectural conventions. Check parses PHP source files into syntax trees with `nikic/php-parser` and never boots the application or loads analysed code.

It is deliberately opinionated: [`DESIGN.md`](DESIGN.md) states the principles and the reason for each.

## Status

Published for the maintainer's own use and offered as-is under the MIT license. The version line is `0.x`: a minor release may break, so pin `^0.1`. Issues and pull requests may go unanswered; forks are welcome. Security reports go through the private reporting route in [`SECURITY.md`](SECURITY.md).

## Installation

```bash
composer require --dev studeo/check
```

Supported PHP and dependency versions are declared in `composer.json`. The executable is installed at `vendor/bin/check`.

## Quick Start

Run from the project root:

```bash
vendor/bin/check
```

Without path arguments Check inspects `app/` when it exists, otherwise the current directory. Files outside that root, such as database migrations, are scanned only when passed as file arguments or when the project root `.` is the argument.

Pass files or directories as positional arguments:

```bash
vendor/bin/check app/Services
vendor/bin/check database/migrations/2026_01_01_000000_create_users_table.php
```

Request JSON output with `--format=json`. A clean run prints:

```text
0 errors, 0 warnings.
```

## Exit Codes

- `0`: no violations, or all violations suppressed by the baseline.
- `1`: violations found. Without `--baseline`, only error-severity findings count; warnings never change the exit code. With `--baseline`, any remaining violation of any severity, or any stale baseline entry, counts.
- `2`: unusable input or configuration: an invalid option, an unreadable path, an invalid `composer.json`, or an invalid rule set.

## Choosing a Rule Set

Check resolves the rule set in this order:

1. `--rules=<class>`: a fully qualified rule set provider class.
2. `check.php`: a file in the project root that returns a provider class name.
3. `Studeo\Check\Profiles\Cap1Rules`: the default profile.

```bash
vendor/bin/check --rules="App\Support\CustomCheckRules"
```

Profiles that need arguments, such as `ActionRules` and `LaravelRules`, are composed in a provider class returned from `check.php`; see [`API.md`](API.md).

## Baseline

A baseline lets an existing codebase adopt the rules without failing on known violations. Later runs fail only on new violations, or when a recorded violation no longer occurs.

```bash
vendor/bin/check --update-baseline --baseline=check-baseline.txt
vendor/bin/check --baseline=check-baseline.txt
```

Baselines only shrink. A recorded violation that has been fixed is reported as a stale entry under the pseudo-rule `baseline` until the baseline is regenerated.

## Rules

Rule ids are permanent. Options and defaults are listed in [`SPEC.md`](SPEC.md) and [`API.md`](API.md).

| Rule ID | Summary |
| --- | --- |
| `method-complexity` | Limits `if` and `elseif` statements per routine; closures count toward the enclosing routine. |
| `file-length` | Warns above a target line count and errors above a hard cap. |
| `method-naming` | Bans non-descriptive method names such as `process` and `doWork`. |
| `step-count` | Limits top-level statements per method or closure body. |
| `nesting-depth` | Limits nesting of control blocks within a routine. |
| `condition-extraction` | Bans boolean operators outside a routine whose body is a single `return`. |
| `action-shape` | Requires Action classes to be final, named `*Action`, and expose only `__invoke()`. |
| `action-authorization` | Requires Actions to authorize an authenticated actor, or declare an explicit system attribute. |
| `migration` | Forbids `enum()` columns and domain class references in migrations. |
| `system-action-usage` | Forbids references to system-marked Actions from controllers, middleware, API resources and Livewire components. |
| `dto-shape` | Requires final data classes whose properties are readonly, named `*Input` or `*Data`, extending the configured base classes. |
| `no-dto-in-livewire-state` | Forbids public Livewire properties that hold data objects. |

## Documentation

- [`DESIGN.md`](DESIGN.md): principles and non-goals.
- [`API.md`](API.md): public classes, options, extension points, command line.
- [`SPEC.md`](SPEC.md): the behavioural contract: output formats, exit codes, baseline, rules.
- [`CONTRIBUTING.md`](CONTRIBUTING.md): the contribution stance and how to run the tests.
- [`CHANGELOG.md`](CHANGELOG.md): breaking and notable changes per release.

## License

[MIT](LICENSE).
