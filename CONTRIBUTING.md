# Contributing

Thank you for considering contributing to `vimatech/laravel-invitation`!

## Reporting Issues

Please open a [GitHub issue](https://github.com/vimatech-io/laravel-invitations/issues) with a clear description of the problem, steps to reproduce, and the expected vs actual behavior.

## Pull Requests

1. Fork the repository and create your branch from `main`:
   ```bash
   git checkout -b feature/my-feature
   ```

2. Write tests for any new behavior. All existing tests must pass:
   ```bash
   composer test
   ```

3. Run static analysis:
   ```bash
   composer analyse
   ```

4. Format your code with Pint:
   ```bash
   composer format
   ```

5. Update `CHANGELOG.md` under the `[Unreleased]` section.

6. Open a pull request against `main` with a clear description of your changes.

## Code Style

This project uses [Laravel Pint](https://laravel.com/docs/pint) for code formatting. Run `composer format` before committing.

## Security

Please do **not** open public issues for security vulnerabilities. See [SECURITY.md](SECURITY.md).
