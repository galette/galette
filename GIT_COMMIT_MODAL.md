# Git Commit Message - Fix Database Report Modal

## Commit Title
```
Fix: Database installation report modal not showing
```

## Commit Description
```
The database installation report modal was not appearing after
DatabaseInstallStep because the StepResult data was being lost
in installer.php.

The issue occurred because the code was creating a new empty
StepResult when requiresDisplay() returned false, which overwrote
the original StepResult containing the modal flag and report data.

This commit simplifies the logic by always preserving the original
StepResult, regardless of the requiresDisplay() value. A step can
be auto-advancing (requiresDisplay = false) AND still have data
for the view (e.g., modal display flags, report content).

Changes:
- Simplified installer.php lines 222-240 to 222-230
- Removed unnecessary conditional logic that was creating new StepResult
- Preserved original StepResult data including show_report_modal flag

The fix reduces code complexity (19 lines → 7 lines) while
preserving all functionality.
```

## Files Changed
- `galette/webroot/installer.php` (1 file, -12/+7 lines)

## Testing
Before committing:
1. Test complete installation (MySQL)
2. Verify modal appears with database report
3. Test database upgrade mode
4. Test with PostgreSQL (if available)
5. Remove debug logs if present

## Related Issues
Fixes #[ISSUE_NUMBER] (if applicable)

---

## Full Commit Command

```bash
git add galette/webroot/installer.php

git commit -m "Fix: Database installation report modal not showing

The database installation report modal was not appearing after
DatabaseInstallStep because the StepResult data was being lost
in installer.php.

The issue occurred because the code was creating a new empty
StepResult when requiresDisplay() returned false, which overwrote
the original StepResult containing the modal flag and report data.

This commit simplifies the logic by always preserving the original
StepResult, regardless of the requiresDisplay() value. A step can
be auto-advancing (requiresDisplay = false) AND still have data
for the view (e.g., modal display flags, report content).

Changes:
- Simplified installer.php lines 222-240 to 222-230
- Removed unnecessary conditional logic that was creating new StepResult
- Preserved original StepResult data including show_report_modal flag

The fix reduces code complexity (19 lines → 7 lines) while
preserving all functionality."

git push origin develop
```

---

## BEFORE Committing

### 1. Remove Debug Logs

**File 1: DatabaseInstallStep.php**
Remove all lines containing:
```php
error_log("[DatabaseInstallStep]
```

**File 2: installer.php**
Remove lines ~410-440 containing:
```php
error_log("[MODAL DEBUG]
```

### 2. Verify Clean State

```bash
# Check for debug statements
grep -r "MODAL DEBUG" galette/
grep -r "DatabaseInstallStep\]" galette/

# Should return nothing (or only the removed files)
```

### 3. Test One More Time

```bash
rm -f galette/config/config.inc.php
# Run installation
# Verify modal appears
```

### 4. Check Code Quality

```bash
# PHP syntax
php -l galette/webroot/installer.php

# Code style (optional)
vendor/bin/php-cs-fixer fix galette/webroot/installer.php --dry-run
```

---

## AFTER Committing

### Update Issue/Ticket

If there's a GitHub issue or bug tracker entry:

1. Reference commit SHA
2. Mark as "Fixed in develop"
3. Add "Pending release" label
4. Close issue OR leave open until released

### Update CHANGELOG

Add entry to CHANGELOG or CHANGES file:

```markdown
### Fixed
- Database installation report modal not showing after installation (#ISSUE)
```

### Update Documentation (Optional)

If this pattern might be useful for other developers:

```markdown
## StepResult Best Practices

A Step can be auto-advancing (requiresDisplay = false) AND have
data for the view. Always preserve the original StepResult:

✅ DO:
if ($result !== null) {
    $stepResult = $result;
}

❌ DON'T:
$stepResult = StepResult::success([...], false); // Loses original data
```

---

## Timeline

1. **Now**: Apply fix, remove debug logs
2. **+5 min**: Test installation one more time
3. **+10 min**: Commit and push
4. **+15 min**: Update issue/ticket
5. **+20 min**: Update CHANGELOG
6. **Next release**: Include in release notes

---

✅ **Ready to commit once validation is complete!**

