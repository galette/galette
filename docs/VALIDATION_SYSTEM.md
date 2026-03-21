# Validation System Refactoring

This document describes the centralized validation system implemented in `AbstractEntity` for Galette entities.

## Overview

The validation system has been centralized in `AbstractEntity` to provide a common infrastructure for all entities. This allows for:

- Consistent validation approach across all entities
- Reduced code duplication
- Easier maintenance and testing
- Future integration with validation libraries like `respect/validation`

## Architecture

### AbstractEntity Infrastructure

The `AbstractEntity` class now provides:

1. **Properties**:
   - `protected array $errors = []` - Stores validation errors
   - `protected ?Preferences $preferences` - Access to preferences (with DI fallback)
   - `protected ?Login $login` - Access to login (with DI fallback)

2. **Helper Methods**:
   - `getPreferences(): Preferences` - Get preferences instance (with global fallback)
   - `getLogin(): Login` - Get login instance (with global fallback)
   - `getErrors(): array` - Get validation errors
   - `sanitizeValues(array $values): array` - Sanitize input data
   - `check(array $values, array $required, array $disabled): bool|array` - Validate data

### Entity-Specific Implementation

Each entity can override the `check()` method to implement its specific validation logic:

```php
public function check(array $values, array $required, array $disabled): bool|array
{
    $this->errors = [];
    
    // Sanitize input
    $values = $this->sanitizeValues($values);
    
    // Entity-specific validation logic here
    
    // Check required fields
    foreach ($required as $field => $val) {
        if (empty($values[$field])) {
            $this->errors[] = "Field $field is required";
        }
    }
    
    // Return true if valid, array of errors otherwise
    return count($this->errors) === 0 ? true : $this->errors;
}
```

## Migration Status

### Completed

- ✅ `AbstractEntity` infrastructure added
- ✅ Helper methods for DI (getPreferences, getLogin, getDB)
- ✅ Sanitization helper method
- ✅ Base `check()` method structure
- ✅ Benchmark script created (`bin/benchmark_validation.php`)
- ✅ Tests for `SavedSearch::check()`
- ✅ Tests for `Contribution::check()`
- ✅ Tests for `Adherent::check()`

### Entity Adaptation Status

1. **SavedSearch** - ✅ Has `check()` method, tests added
2. **Contribution** - ✅ Has `check()` method, tests extended
3. **Adherent** - ✅ Has `check()` method, tests extended

## Benchmark Script

### Usage

```bash
# Quick test (100 iterations)
php bin/benchmark_validation.php quick

# Normal test (1000 iterations) - default
php bin/benchmark_validation.php normal

# Intensive test (10000 iterations)
php bin/benchmark_validation.php intensive
```

### Output

The script generates:
- Real-time progress output
- Performance metrics (time, memory, throughput)
- JSON results saved to `tests/benchmark_results.json`

### Metrics Collected

- Total execution time
- Average time per validation
- Memory usage
- Peak memory
- Throughput (validations/second)

## Testing

### Running Tests

```bash
# Run all entity tests
DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/Entity/

# Run specific entity tests
DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/Entity/SavedSearchTest.php
DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/Entity/Contribution.php
DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/Entity/Adherent.php
```

### Test Coverage

Each entity now has tests covering:

- ✅ Valid data validation
- ✅ Missing required fields
- ✅ Invalid field values
- ✅ Duplicate detection (email, login)
- ✅ Format validation (dates, emails, amounts)
- ✅ Business rules (password matching, birthdates, etc.)

## Future Enhancements

### Respect/Validation Integration (Planned)

The architecture is designed to support future integration with `respect/validation`:

1. **Create validator interface**:
```php
interface ValidatorInterface
{
    public function validate(array $values, array $rules): bool|array;
}
```

2. **Implement adapters**:
   - `LegacyValidator` - Current system
   - `RespectValidator` - Using respect/validation library

3. **Benchmark comparison**:
   - Compare performance
   - Evaluate maintainability
   - Decide on migration strategy

### Performance Considerations

Based on benchmark results, acceptable thresholds:
- Maximum +20% execution time vs current system
- Improved code maintainability
- Better error messages
- Type safety

## Best Practices

### For Entity Developers

1. **Always call `sanitizeValues()`** before validation:
```php
$values = $this->sanitizeValues($values);
```

2. **Use helper methods** for dependencies:
```php
$preferences = $this->getPreferences();
$login = $this->getLogin();
```

3. **Clear errors** at start of check():
```php
$this->errors = [];
```

4. **Return consistent types**:
```php
return count($this->errors) === 0 ? true : $this->errors;
```

5. **Write comprehensive tests** covering all validation scenarios

### For Test Writers

1. **Test both success and failure** cases
2. **Test all validation rules** (required, format, uniqueness, etc.)
3. **Use data providers** for similar test cases
4. **Test edge cases** (empty strings, null, special characters)
5. **Verify error messages** are meaningful

## Troubleshooting

### Common Issues

**Issue**: Validation passes but should fail
- **Solution**: Check that fields are properly sanitized
- **Solution**: Verify required fields are correctly defined

**Issue**: Global variables not available
- **Solution**: Ensure helper methods (getDB, getPreferences, getLogin) are used
- **Solution**: Check that Galette is properly initialized

**Issue**: Tests fail with "Call to a member function on null"
- **Solution**: Ensure test setup properly initializes all dependencies
- **Solution**: Use `setUp()` method to configure test environment

## Contributing

When adding or modifying validation:

1. **Update tests** - Add test cases for new validation rules
2. **Run benchmarks** - Ensure performance is acceptable
3. **Update documentation** - Document new validation rules
4. **Follow patterns** - Use established validation patterns
5. **Code style** - Run `vendor/bin/php-cs-fixer fix` before committing

## References

- Main documentation: https://doc.galette.eu/
- PHP-CS-Fixer: `.php-cs-fixer.dist.php`
- PHPStan: `phpstan.neon`
- PHPUnit: `phpunit.xml`

## Questions?

For questions or issues:
- Mailing list: https://lists.mailman3.com/postorius/lists/galette-devel.mailman3.com/
- Bug tracker: https://bugs.galette.eu/projects/galette

