# Testing TitanCore

## Local
1. Ensure module is in `Modules/TitanCore`.
2. Install dev deps in host app (`phpunit/phpunit`, etc.).
3. Run: `php artisan test --testsuite=Unit` and `--testsuite=Feature`.

## Notes
- Feature tests in this module assert route existence and auth behavior only.
- Bind `AI_BINDING_MODE=stub` for tests.
- Seed: `php artisan module:seed TitanCore`.
