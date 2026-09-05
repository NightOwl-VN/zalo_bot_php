# Contributing to Zalo Bot PHP SDK

> Language: [English 🇺🇸](./CONTRIBUTING.md) | [Tiếng Việt 🇻🇳](./docs/vi/contributing.md)

Thank you for your interest in contributing to **Zalo Bot PHP SDK**! We welcome contributions from developers of all skill levels. Whether you are reporting a bug, improving documentation, or adding new features, your help is appreciated.

---

## 📜 Code of Conduct

We are committed to providing a welcoming, inclusive, and harassment-free experience for everyone. Please be respectful, constructive, and professional in all interactions across issues, pull requests, and discussions.

---

## 🛠️ How to Contribute

### 1. Reporting Bugs
- Search existing [GitHub Issues](https://github.com/NightOwl-VN/zalobot-sdk-php/issues) first to ensure the bug hasn't already been reported.
- If not, create a new issue using the **Bug Report** template.
- Provide a clear title, reproduction steps, expected vs. actual behavior, and environment details (PHP version, OS, SDK version).
- Include relevant error logs or minimal code snippets without exposing sensitive access tokens or secret keys.

### 2. Suggesting Enhancements
- Open an issue using the **Feature Request** template.
- Clearly describe the proposed feature, user motivation, and potential API design.
- Discuss breaking changes or architectural modifications before implementing them.

### 3. Submitting Pull Requests (PRs)
- Keep PRs focused on a single feature or bug fix.
- Ensure all existing and new code adheres to our style guidelines and passes local testing.

---

## 💻 Local Development Setup

Follow these steps to set up the development environment locally:

```bash
# 1. Clone the repository
git clone https://github.com/NightOwl-VN/zalobot-sdk-php.git
cd zalobot-php-sdk

# 2. Install dependencies
composer install

# 3. Create a local environment file
cp .env.example .env

# 4. Fill in your test credentials in .env (never commit this file)
# ZALO_BOT_TOKEN=your_test_token
# ZALO_BOT_SECRET=your_test_secret
```

### Running Tests
```bash
# Run the full PHPUnit test suite
composer test
# or
vendor/bin/phpunit

# Run a specific test file
vendor/bin/phpunit tests/ConfigTest.php
```


### Composer Lockfile Policy

This is a **library** repository. In general, Composer lockfiles are **not
committed** for library repositories: consumers run `composer install` using
the `composer.json` constraints, and the lockfile is generated in their
project. The `composer.lock` currently committed here is retained only to
keep CI reproducible during active development; it will be removed (or
become optional) before a stable release. If you are a consumer, run
`composer update` to get the latest compatible versions, not `install`.

### Running Examples
```bash
# Set up your .env in the examples directory first
cp examples/.env.example examples/.env

# Execute the send-message example
php examples/send-message.php
```

---

## 🎨 Coding & Style Guidelines

To keep the codebase maintainable and consistent, please follow these rules:

1. **PHP 8.1+ Modern Features**:
   - Use named arguments for clarity.
   - Use typed properties and `readonly` where appropriate.
   - Use `match` expressions instead of complex `switch` blocks.
   - Use enums for fixed sets of values.

2. **PSR-12 Coding Standard**:
   - Follow [PSR-12: Extended Coding Style Guide](https://www.php-fig.org/psr/psr-12/).
   - 4 spaces for indentation.
   - One `declare(strict_types=1);` at the top of every PHP file.

3. **PHPDoc Documentation**:
   - Every public class, method, and parameter **must** include comprehensive PHPDoc comments.
   - Use `@param`, `@return`, and `@throws` annotations consistently.

4. **Zero Hardcoded Secrets & Localhost**:
   - Never commit API keys, access tokens, secret keys, or internal IP addresses.
   - All URLs and endpoints must be dynamically configurable via `src/Config.php` and `.env`.

5. **Error Handling**:
   - Use custom exception classes from `src/Exceptions/` (`ApiException`, `AuthException`, `ValidationException`).
   - All error messages must be in clear Technical English.
   - Use the most specific exception type for each error.

---

## 🔀 Git Commit Conventions

We enforce the [Conventional Commits](https://www.conventionalcommits.org/) specification:

| Prefix | Usage | Example |
|---|---|---|
| `feat:` | A new feature | `feat: add support for interactive carousel templates` |
| `fix:` | A bug fix | `fix: handle edge case in webhook signature parser` |
| `docs:` | Documentation changes | `docs: add webhook troubleshooting guide` |
| `refactor:` | Code change that neither fixes a bug nor adds a feature | `refactor: optimize token refresh helper` |
| `test:` | Adding or updating tests | `test: add unit tests for message module` |
| `chore:` | Build process, auxiliary tools, dependencies | `chore: update dependencies` |

---

## 🚀 Pull Request Process

1. **Fork & Branch**:
   Create a new branch from `main`:
   ```bash
   git checkout -b feat/your-feature-name
   # or
   git checkout -b fix/issue-description
   ```

2. **Develop & Test**:
   - Write your code following the style guide (PSR-12).
   - Add/update PHPDoc comments.
   - Run `composer test` and ensure all tests pass.

3. **Commit & Push**:
   ```bash
   git add .
   git commit -m "feat: description of changes"
   git push origin feat/your-feature-name
   ```

4. **Open a Pull Request**:
   - Open a PR against the `main` branch of `NightOwl-VN/zalobot-sdk-php`.
   - Provide a clear summary of changes and reference related issue numbers (e.g., `Closes #12`).
   - A maintainer will review your PR and provide feedback.

---

## 📄 License

By contributing to Zalo Bot PHP SDK, you agree that your contributions will be licensed under the [MIT License](./LICENSE).
