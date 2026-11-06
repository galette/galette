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

## Coming changes

The following changes are not merged yet. Each of them will complete its
section when it is.

### Plugin definition

Plugins will be declared with a `#[PluginDefinition]` attribute on their
main class, rather than calling `$this->register()` in `_define.php`.

### Routes, menus and dashboards

Routes can be declared with `#[Route]` attributes on plugin controllers.
Menus, dashboard entries, actions and news move to a `Navigation` service.
Core listeners go through the event dispatcher.

### Permissions

Roles and permissions replace access levels. Route ACLs such as `'staff'` or
`'groupmanager'` will have to name permissions, and plugins will be able to
declare their own.

### PDF

TCPDF is replaced by tc-lib-pdf. Plugin classes extending `Galette\IO\Pdf`
or calling TCPDF methods will have to be ported.
