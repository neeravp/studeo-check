# Contributing

Check is published for its maintainer's own use. Issues and pull requests may go unanswered, and changes are unlikely to be merged unless they fit the design in [`DESIGN.md`](DESIGN.md).

Forks are welcome. To add or change a rule for your own needs, see "Writing a Rule" in [`API.md`](API.md).

To run the checks the project's CI runs: `composer install`, then `vendor/bin/pest`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse -c phpstan.neon.dist` and `sh spec/replay.sh`.
