# Contributing to Inov-Com

Thank you for your interest in contributing to Inov-Com! This document provides guidelines and instructions for contributors.

## Getting Started

1. Read the [SETUP.md](./SETUP.md) guide to set up your development environment
2. Review the [DEVELOPER_GUIDE.md](./DEVELOPER_GUIDE.md) to understand module development
3. Check existing modules for code patterns and conventions

## Development Workflow

### 1. Before You Start

- Ensure you have the latest code: `git pull`
- Run tests if available: `php artisan test`
- Clear caches: `php artisan cache:clear`

### 2. Making Changes

- Create a feature branch: `git checkout -b feature/your-feature`
- Make your changes following coding standards
- Test your changes thoroughly
- Update documentation if needed

### 3. Code Standards

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards
- Use Laravel conventions and best practices
- Write clear, self-documenting code
- Add comments for complex logic

### 4. Commit Messages

Use clear, descriptive commit messages:

```
feat: Add inventory tracking module
fix: Resolve tenant connection issue
docs: Update setup guide
refactor: Improve module registration
```

### 5. Testing

Before submitting:
- Test your changes manually
- Verify no regressions
- Check error handling
- Test with multiple tenants (if applicable)

## Module Development

When creating a new module:

1. Follow the structure in [DEVELOPER_GUIDE.md](./DEVELOPER_GUIDE.md)
2. Use existing modules as reference
3. Implement proper error handling
4. Add validation for user inputs
5. Use proper database connections
6. Follow naming conventions

## Documentation

- Update relevant documentation files
- Add code comments for complex logic
- Update README if adding new features
- Keep examples up to date

## Pull Request Process

1. Ensure your code follows standards
2. Update documentation
3. Test thoroughly
4. Create a clear PR description
5. Reference any related issues

## Questions?

- Review existing documentation
- Check existing modules for examples
- Ask in team discussions

## Code Review

All contributions will be reviewed for:
- Code quality and standards
- Functionality and correctness
- Documentation completeness
- Test coverage

Thank you for contributing to Inov-Com!
