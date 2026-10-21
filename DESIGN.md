# Design

Check is deliberately opinionated. This document states the principles behind it and why each one exists. Rule ids, options and output formats are specified in [`SPEC.md`](SPEC.md); the public classes are in [`API.md`](API.md).

## What Check is for

Check keeps a Laravel codebase readable by enforcing a small set of structural rules on code shape (how long, how branched, how nested a routine is) and a second set of conventions for Actions, data objects, migrations and Livewire state. It reports findings; it does not rewrite code.

## Principles

### 1. Analysis never runs the analysed code

Files are parsed into syntax trees. The application is never booted and no analysed class is loaded.
*Why:* a check must be safe and fast on any tree, including one that does not boot, and must give the same answer on every machine.

### 2. Code that reads like a short narrative

The structural rules (`method-complexity`, `step-count`, `nesting-depth`, `condition-extraction`, `method-naming`, `file-length`) push a routine toward a flat list of steps, each delegating to a well-named helper.
*Why:* the reader should understand a method a year later without reconstructing what a clever construct does. Limits are rule options with documented defaults; the defaults are the opinion.

### 3. Conventions are separate from structure

The structural tier knows nothing about Laravel. The convention tier (`action-shape`, `action-authorization`, `migration`, `system-action-usage`, `dto-shape`, `no-dto-in-livewire-state`) depends on the structural tier and the engine, never the reverse.
*Why:* the structural rules are useful on any PHP project, and a convention that does not fit a codebase must be removable without touching the rest.

### 4. The package names no project

A rule that needs a project's own marker (a system attribute, DTO base classes) takes it as a required option. Defaults are neutral.
*Why:* a rule that hard-codes one project's names is that project's code, not a reusable rule.

### 5. Measure is separate from judge

Each rule counts or detects something (the measure, language specific) and then compares it to a threshold and picks a severity (the judge).
*Why:* the measures are the part that depends on the language; keeping the judgement apart keeps the rules specifiable and portable.

### 6. Fail closed

Unreadable input, an invalid configuration, an unusable rule set, a malformed `composer.json` or an unknown option ends the run with a dedicated exit code. It never degrades to "no findings".
*Why:* a check that silently passes when it could not check is worse than no check.

### 7. A baseline can only shrink

A baseline records known violations so a legacy codebase can adopt the rules. A new violation fails, and so does a recorded violation that no longer occurs.
*Why:* a baseline that tolerates stale entries hides progress and lets the debt list rot.

### 8. Identifiers and formats are contracts

Rule ids, the violation record, the JSON output, the baseline key format and the exit codes are stable within a release line. Output is sorted so it does not depend on the order of the rules.
*Why:* baselines, CI scripts and other tools key on these values. Changing one is a breaking change and is announced as one.

### 9. Configuration is code

A rule set is a PHP class returning rules built with typed `options()` factories. Rules are `final`; they are composed, never subclassed. Profiles are frozen: a revised profile is a new profile.
*Why:* typed options fail early with a clear message, composition is explicit, and a profile that changes under a user changes their build.

### 10. Paths mean the same thing however they are spelled

A file is reported, and baselined, under one path whether the run was started with `.`, a directory or the file itself.
*Why:* otherwise a baseline recorded one way would not match a run started another way, and scoped rules could silently skip files.

### 11. The package follows its own rules

Check runs its structural rules over its own source with an empty baseline.
*Why:* a rule set its authors cannot live with is not worth shipping.

## Non-goals

- Other languages. The specification keeps the structural tier portable, but no other implementation is provided or promised.
- Executing, type-resolving or booting the analysed application.
- Automatic fixes.
- Per-path overrides and per-rule severity; a project that needs them composes its own rule sets.
- Replacing a general static analyser or a code formatter. Check complements tools such as PHPStan and Pint.
