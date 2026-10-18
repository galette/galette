# Feature Flags - Quick Reference

## 📋 In 30 Seconds

### Add a flag
```php
// galette/includes/sys_config/feature_flags.inc.php
'my-flag' => 'Description',
```

### Add a flag with dependencies
```php
'my-flag' => [
    'description' => 'Description',
    'requires' => ['dependency1', 'dependency2'],
],
```

### Activate

Every registered flag can be turned on by the super administrator, from the
*Experimental features* tab of *Advanced configuration*.
Turning a flag on turns on the ones it requires; turning it off turns off the
ones requiring it.

It can also be declared in a file or the environment:
```php
// galette/config/behavior.inc.php
define('GALETTE_FEATURE_FLAGS', ['my-flag']);
```

When defined, the constant takes precedence over the features turned on from
the interface, which can then no longer be changed there.

Lifecycle: registered → stable (flag removed from the registry).

### Turn features on or off from the command line
```bash
bin/console galette:feature:enable my-flag
bin/console galette:feature:disable my-flag
bin/console galette:feature:disable --all
```

Both need an installed Galette: like every command using the database, they
are not listed while `config.inc.php` is missing.

### Usage in code
```php
if ($featureFlags->isEnabled('my-flag')) {
    // Code
}
```

### Usage in Twig
```twig
{% if is_feature_enabled('my-flag') %}
    <!-- Contenu -->
{% endif %}
```

### Check
```bash
bin/console galette:feature:status
```

---

## 🔗 Dependencies

### Example: API v2 depends on ACLs and OAuth2

**Registry** :
```php
'acls' => 'New ACL management',
'oauth2' => [
    'description' => 'OAuth2 auth',
    'requires' => ['acls'],
],
'api-v2' => [
    'description' => 'REST API v2',
    'requires' => ['acls', 'oauth2'],
],
```

**Config** :
```php
define('GALETTE_FEATURE_FLAGS', ['acls', 'oauth2', 'api-v2']);
```

**Code** :
```php
// No need to check for dependencies here!
if ($featureFlags->isEnabled('api-v2')) {
    // Deps are active
}
```

---

## ⚠️ Important

- ✅ **ALWAYS** ajouter au registry avant utilisation
- ✅ **Dependencies** = automatic checks
- ❌ **Do not** forget to activate dependencies

---

## 🚀 Result

```bash
$ bin/console galette:feature:status

 Galette Feature Flags Status
===================================


All Feature Flags
-----------------

 --------------- ------------ --------------------------------------------------- ---------------- ------------- ----------------------------------- 
  Flag Name       Status       Description                                         Dependencies     State         Note                               
 --------------- ------------ --------------------------------------------------- ---------------- ------------- ----------------------------------- 
  acls            ✗ DISABLED   New Access Control Lists (RBAC) management system                    [available]   (not enabled in behavior.inc.php)  
  oauth2          ✗ DISABLED   OAuth2 authentication system for API                → acls           [available]   (missing: acls)                    
  new-dashboard   ✗ DISABLED   Redesigned admin dashboard with modern UI                            [available]   (not enabled in behavior.inc.php)  
  api-v2          ✗ DISABLED   RESTful API version 2 with OAuth2 support           → acls, oauth2   [available]   (missing: acls, oauth2)            
 --------------- ------------ --------------------------------------------------- ---------------- ------------- ----------------------------------- 


Statistics:
  - Total registered flags: 4
  - Enabled flags: 0
  - Declared in config: 0
  - Used in code: 0

```

