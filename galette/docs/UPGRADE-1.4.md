# Upgrading plugins to Galette 1.4 {#upgrade14}

This page lists what a plugin must change to run on Galette 1.4. It is
updated with the code: every change that breaks plugins documents itself
here, in the same pull request.

For plugins development in general, see [Plugin development](@ref plugins).

[TOC]

## Requirements

Nothing changes yet: PHP 8.3 minimum, and plugins declaring
`compver: '1.3.0'` in `_define.php` are still loaded.

## Removed methods and classes

These were deprecated, unused by the core, or both. Search your plugin for
them and use the replacement when there is one.

| Removed | Use instead |
|---------|-------------|
| `PluginControllerTrait::getModuleRoute()` | `$this->module_info['module']['route']` |
| `Plugins::getDisabledModule($id)` | `$plugins->getDisabledModules()[$id] ?? null` |
| `Preferences::cleanHtmlValue($value)` | `Galette\Util\Html::clean($value)` |
| `Galette\Util\Text::convertHtmlToText($html)` | `Galette\Util\Html::convertToText($html)` |
| `Galette\IO\PdfMembersCardsAdaptative` | `Galette\IO\PdfMembersCards` |
| `AbstractEntity::getPkField()` | `static::PK`, or `MyEntity::PK` |
| `ScheduledPayments::getListFromContribution($id)` | set `$filters->from_contribution = $id`, then `getList(as_object: true)` |

Removed without replacement:

- `Picture::missingInDb()`
- `FieldsConfig::migrateRequired()`
- `Mailing::getAttachmentErrorMessage()`
- `Csv::getAcceptedSeparators()`, `Csv::getAcceptedQuotes()`
- `Reminder::isSuccess()`
- `FeatureFlagManager::getAccessedFlags()`
- `I18n::updateTranslation()` (protected trait method)
- `FakeData::addSuccess()`, `FakeData::addWarning()`

`config/local_acls.inc.php` is no longer looked for. It was never included,
so ACLs overrides must still be declared in the plugin `_define.php`.

## Dynamic fields

`DynamicField::check()` requires a non blank `field_name` for every field
type, separators included. A plugin creating dynamic fields from code must
give them a name.

## Plugin definition

`_define.php` now returns a `Galette\Core\Plugins\PluginDefinition`
instead of calling `$this->register()`, which is deprecated (a notice is
logged in debug mode). Arguments are the same; CSRF exclusions move from
`$this->setCsrfExclusions()` to the `csrf_exclusions` argument.

Before:

```php
/** @var \Galette\Core\Plugins $this */
$this->register(
    name: 'Galette My Plugin',
    desc: 'My plugin description',
    author: 'Your Name',
    version: '1.0.0',
    compver: '1.3.0',
    route: 'myplugin',
    date: '2026-01-15',
    acls: ['myplugin_list' => 'member'],
    dbver: 1.0
);
$this->setCsrfExclusions(['/myplugin_notify/']);
```

After:

```php
use Galette\Core\Plugins\PluginDefinition;

return new PluginDefinition(
    name: 'Galette My Plugin',
    desc: 'My plugin description',
    author: 'Your Name',
    version: '1.0.0',
    compver: '1.4.0',
    route: 'myplugin',
    date: '2026-01-15',
    acls: ['myplugin_list' => 'member'],
    dbver: 1.0,
    csrf_exclusions: ['/myplugin_notify/']
);
```

Do not forget the `return`: a definition file that neither returns a
definition nor calls `$this->register()` disables the plugin as invalid,
as does a definition file that throws an error. A plugin that must still
run on Galette 1.3 keeps calling `$this->register()`.

See [Registration](@ref plugins_define).

## Permissions

Roles made of permissions replace access levels and group managers
preferences. Plugins ACLs levels (`member`, `groupmanager`, `staff`,
`admin`, `superadmin`) still work, but:

- a route can require a permission instead, `domain:action`, or several
  separated with a pipe;
- a plugin can declare its own permissions, implementing
  `Galette\Core\Plugins\PermissionsProviderInterface`, and check them with
  `AccessControl::can()` in PHP or `can()` in Twig;
- plugin objects implement `Galette\Interfaces\AccessSubjectInterface` so
  that owners and group managers rules apply to them.

Checks such as `$login->isGroupManager()` or `$login->isStaff()` still
exist, but prefer permissions: they follow what administrators set in
roles.

See [Permissions](@ref plugins_permissions).

## Coming changes

The following changes are not merged yet. Each of them will complete its
section when it is.

### Routes, menus and dashboards

Routes can be declared with `#[Route]` attributes on plugin controllers.
Menus, dashboard entries, actions and news move to a `Navigation` service.
Core listeners go through the event dispatcher.

### PDF

TCPDF is replaced by tc-lib-pdf. Plugin classes extending `Galette\IO\Pdf`
or calling TCPDF methods will have to be ported.
