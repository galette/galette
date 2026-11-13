# Upgrading plugins to Galette 1.4 {#upgrade14}

This page lists what a plugin must change to run on Galette 1.4. It is
updated with the code: every change that breaks plugins documents itself
here, in the same pull request.

For plugins development in general, see [Plugin development](@ref plugins).

[TOC]

## Requirements

PHP 8.3 minimum, as for 1.3.

The compatibility version is now `1.4.0`: plugins must declare ``compver: '1.4.0'``,
those still declaring `1.3.0` are disabled.

## Removed methods and classes

These were deprecated, unused by the core, or both. Search your plugin for
them and use the replacement when there is one.

| Removed | Use instead |
|---------|-------------|
| `PluginControllerTrait::getModuleRoute()` | ``$this->module_info['module']['route']`` |
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

## Container aliases

Deprecated since 1.2.1, the string aliases of the container are removed.
Get services, or inject them, by their class name:

| Removed | Use instead |
|---------|-------------|
| `zdb` | `Galette\Core\Db` |
| `login` | `Galette\Core\Login` |
| `preferences` | `Galette\Core\Preferences` |
| `plugins` | `Galette\Core\Plugins` |
| `history` | `Galette\Core\History` |
| `i18n` | `Galette\Core\I18n` |
| `l10n` | `Galette\Core\L10n` |
| `logo` | `Galette\Core\Logo` |
| `print_logo` | `Galette\Core\PrintLogo` |
| `translator` | `Galette\Core\Translator` |
| `fields_config` | `Galette\Entity\FieldsConfig` |
| `lists_config` | `Galette\Entity\ListsConfig` |
| `flash` | `Slim\Flash\Messages` |
| `event_manager` | `League\Event\EventDispatcher` |

```php
// Before
$login = $container->get('login');

// After
$login = $container->get(\Galette\Core\Login::class);
```

`LightSlimApp` is removed as well.

## Dynamic fields

Since Galette 1.3.3, `DynamicField::check()` requires a non blank
`field_name` for every field type, separators included. A plugin creating dynamic fields from code must
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

The rights preferences are removed from `Preferences`: reading one of
them now fails on an undefined property. Their values are only read when
upgrading, to set the system roles permissions. Check the permission
instead:

| Removed preference | Permission |
|--------------------|------------|
| `pref_bool_create_member` | `member:create-child` |
| `pref_bool_groupsmanagers_create_member` | `member:create` |
| `pref_bool_groupsmanagers_edit_member` | `member:edit` |
| `pref_bool_groupsmanagers_edit_groups` | `group:edit` |
| `pref_bool_groupsmanagers_mailings` | `mailing:send` |
| `pref_bool_groupsmanagers_exports` | `member:export`, `member:print`, `group:export` |
| `pref_bool_groupsmanagers_create_contributions` | `contribution:create` |
| `pref_bool_groupsmanagers_see_contributions` | `contribution:read` |
| `pref_bool_groupsmanagers_create_transactions` | `transaction:create` |
| `pref_bool_groupsmanagers_see_transactions` | `transaction:read` |

Permissions cover administrators and staff members as well, so the whole
condition goes:

```php
// Before
if (
    !$this->login->isAdmin()
    && !$this->login->isStaff()
    && !$this->preferences->pref_bool_groupsmanagers_exports
) {
    // access denied
}

// After
if (!$this->accessControl->can('member:export')) {
    // access denied
}
```

Without a subject, `can()` grants a group manager who has the permission
on at least one group; pass the object to check it against its groups.

See [Permissions](@ref plugins_permissions).

## Routes

Routes can be declared with `#[Route]` attributes on the plugin
controllers, under `lib/{Namespace}/Controllers/`, with patterns relative
to the plugin route. `_routes.php` becomes optional; it is still included
when present, with `$app`, `$module`, `$module_id` and `$container`.

Before, in `_routes.php`:

```php
$app->get(
    '/list',
    [MyController::class, 'list']
)->setName('myplugin_list')->add(Authenticate::class);
```

After, on the controller method:

```php
use Galette\Controllers\Attributes\Route;

#[Route(name: 'myplugin_list', pattern: '/list')]
public function list(Request $request, Response $response): Response
```

Authentication is required by default (`requiresAuth: true`); routes still
need their ACL in the plugin definition. See [Routes](@ref plugins_routes).

Core routes are only declared from attributes: the `includes/routes/`
files have been removed.

## Menus, dashboards, actions and news

The static methods of `Galette\Core\Galette` building menus, dashboards,
members actions, news and release notices are removed. Services of the
`Galette\Navigation` namespace replace them: inject the one you need, or
call its Twig function from templates.

| Removed | Service method | Twig function |
|---------|----------------|---------------|
| `Galette::getAllMenus()` | `MainMenu::getAllMenus()` | `main_menus()` |
| `Galette::getMenus($public)` | `MainMenu::getMenus($public)` | |
| `Galette::getPublicMenus()` | `PublicMenu::getMenus()` | `public_menus()` |
| `Galette::getDashboards()` | `Dashboards::getEntries()` | `dashboards()` |
| `Galette::getMyDashboards()` | `Dashboards::getMyEntries()` | `my_dashboards()` |
| `Galette::getListActions($member)` | `MemberActions::getListActions($member)` | `member_list_actions(member)` |
| `Galette::getDetailedActions($member)` | `MemberActions::getDetailedActions($member)` | `member_detailed_actions(member)` |
| `Galette::getBatchActions()` | `MemberActions::getBatchActions()` | `members_batch_actions()` |
| `Galette::getNews()` | `NewsFeed::getEntries()` | |
| `Galette::hasNews()` | `NewsFeed::hasEntries()` | |
| `Galette::getNewRelease()` | `ReleaseNotices::getNewRelease()` | `new_release()` |
| `Galette::getPluginsUpdates()` | `ReleaseNotices::getPluginsUpdates()` | |

In a template, before:

```twig
{% set menus = callstatic('\\Galette\\Core\\Galette', 'getAllMenus') %}
```

After:

```twig
{% set menus = main_menus() %}
```

Menus, entries and actions are now objects, no longer arrays:

- `Menu`: `title`, `icon` and `items`, a list of `MenuEntry`;
- `MenuEntry`: `label`, `route`, `title`, `icon`, `badge`, `children` and
  `extraClass`, for menu items, dashboard cards and members actions;
- `RouteTarget`: `name`, `args` and `aliases`;
- `BatchAction`: `name`, `label`, `icon` and `title`.

Templates read their properties as they read the arrays keys
(`entry.label`, `entry.route.name`), except:

- `extra_class` is now `extraClass`;
- an entry grouping children has a null `route`. Check `entry.route` or
  `entry.children`: `is defined` is always true on an object property,
  null or not.

Plugin interfaces do not change: `MenuProviderInterface`,
`DashboardProviderInterface` and `MemberActionProviderInterface` still
return arrays, the core builds the objects from them. Invalid entries now
throw an `\InvalidArgumentException`, where they used to break, or not,
when the template was rendered:

- a menu entry, dashboard card or member action needs a `label`, and a
  `route` with a `name` or `children`;
- a batch action needs a `name`, a `label` and an `icon`;
- route `args` keys must be strings, other ones are ignored.

A plugin menu named after a core one (`myaccount`, `members`,
`contributions`, `management`, `configuration`) adds its items to it;
its `title` and `icon` are only used for a menu of its own.

Methods deprecated since 1.2.2 are no longer called: implement the
interfaces instead.

| No longer called | Interface |
|------------------|-----------|
| `getMenusContents()`, `getPublicMenusItemsList()` | `MenuProviderInterface` |
| `getDashboardsContents()`, `getMyDashboardsContents()` | `DashboardProviderInterface` |
| `getListActionsContents()`, `getDetailedActionsContents()` | `MemberActionProviderInterface` |
| `getNews()` without the interface | `NewsProviderInterface` |

`GalettePlugin` no longer defines `getMenus()`, `getDashboards()`,
`getMyDashboards()`, `getListActions()`, `getDetailedActions()` nor
`getBatchActions()`: do not call their parent implementation.

To work with the plugins implementing an interface, use
`Plugins::getProviders()`; it returns the installed ones only:

```php
foreach ($plugins->getProviders(MenuProviderInterface::class) as $plugin) {
    $menus = $plugin->getMenus();
}
```

## Content Security Policy

Galette sends and enforces a [Content Security Policy](https://developer.mozilla.org/docs/Web/HTTP/Guides/CSP)
with its pages: a script that does not follow the rules below is not run.
Violations show up in the browser console and in Galette logs.

Administrators can turn `pref_csp_enforce` off from the advanced configuration
while a page is broken: framing, plugins (`object-src`) and `base-uri` remain
enforced, the complete policy is then sent as `Content-Security-Policy-Report-Only`.

### Scripts

Every `<script>` tag, inline or not, must carry the nonce of the request:

```twig
<script nonce="{{ csp_nonce() }}">
    $('#my_button').on('click', function () { /* ... */ });
</script>
<script nonce="{{ csp_nonce() }}" src="{{ url_for('plugin_res', {'plugin': module_id, 'path': 'myplugin.js'}) }}"></script>
```

Scripts loaded by a script carrying the nonce are allowed as well
(`'strict-dynamic'`): a payment library that loads its own files works as
long as the first one carries it.

What cannot be allowed:

- inline event handlers (`onclick="..."`, `onchange="..."`): use
  `addEventListener()` or jQuery `on()` from a script carrying the nonce;
- `javascript:` URLs: use a `<button type="button">` and an event listener;
- `eval()`, `new Function()`, `setTimeout()` with a string.

### External origins

Images can come from any `https:` origin. Anything else outside of Galette
must be declared: frames of a payment provider, APIs called from the
browser, a checkout page a form posts or redirects to... Implement
`Galette\Core\Plugins\ContentSecurityPolicyProviderInterface` on the plugin
class:

```php
use Galette\Core\GalettePlugin;
use Galette\Core\Plugins\ContentSecurityPolicyProviderInterface;

class PluginGaletteMyplugin extends GalettePlugin implements ContentSecurityPolicyProviderInterface
{
    public function getCspSources(): array
    {
        return [
            'frame-src' => ['https://js.stripe.com', 'https://hooks.stripe.com'],
            'connect-src' => ['https://api.stripe.com'],
            'form-action' => ['https://checkout.example.org'],
        ];
    }
}
```

Directives plugins may add to are listed in `Galette\Core\Csp::PLUGIN_DIRECTIVES`:
`connect-src`, `font-src`, `form-action`, `frame-src`, `img-src`,
`media-src`, `script-src`, `style-src` and `worker-src`. Sources are
`https://` or `wss://` origins, with an optional wildcard on the first label
(`https://*.example.org`) and an optional path, or `data:` and `blob:`
(except for `script-src`). Keywords (`'unsafe-inline'`, `'unsafe-eval'`...)
and wildcards alone are refused: invalid sources are logged and ignored.

Declared sources are added on all pages.

### Checking a plugin

Open the browser console on the plugin pages: each violation is reported
there, and the blocked script or request is the cause of a broken page.
Galette logs them too, as warnings:

```
CSP violation: frame-src blocked https://js.stripe.com/v3/ on https://galette.example.org/plugins/stripe/form
```

## PDF

TCPDF is replaced by [tc-lib-pdf](https://github.com/tecnickcom/tc-lib-pdf).
`Galette\IO\Pdf` now extends `Com\Tecnick\Pdf\Tcpdf`: TCPDF methods are gone,
and there is no compatibility layer. Classes extending `Galette\IO\Pdf` must be
ported to the methods it provides.

Positions are absolute, in millimeters from the top left corner of the page.
tc-lib-pdf has no current position; `Galette\IO\Pdf` keeps one, so documents
can still be written sequentially.

| TCPDF | Galette 1.4 |
|-------|-------------|
| `AddPage()` | `newPage(?string $orientation = null)` |
| `SetMargins()`, `setPageOrientation()` | `setPageMargins()`, `$this->orientation`, in `init()` |
| `SetAutoPageBreak()` | `setAutoPageBreak(bool)`; text cells never break pages, call `checkPageBreak(?float $height)` |
| `SetFont($family, $style, $size)` | `setFont(?string $style, ?float $size)`, the font is always DejaVu Sans |
| `SetTextColor(r, g, b)`, `SetDrawColor()`, `SetFillColor()` | ``setTextColor('#rrggbb')``, ``setDrawColor('#rrggbb')``, `fill` argument of `text()` |
| `Cell()`, `MultiCell()`, `Write()` | `text()`; `wrap: false` keeps it on a single line, as `Cell()` did |
| `writeHTML()`, `writeHTMLCell()`, `MultiCell(..., ishtml: true)` | `writeHTML(string $html, ?float $x, ?float $y, float $w, float $h)` |
| `Image()`, `Rect()`, `Line()` | `image()`, `rect()`, `line()` |
| `GetStringWidth()` | `stringWidth()` |
| `GetX()`, `GetY()`, `SetX()`, `SetY()`, `SetXY()`, `Ln()` | `getX()`, `getY()`, `setXY()`, `ln()` |
| `getNumPages()` | `getNumPages()` |
| `Header()`, `Footer()` overrides | `drawPageHeader(int $page, int $total)`, drawn once all pages exist; the footer comes from the models |
| ``Output($name, 'D')``, ``Output($name, 'S')`` | `getContents()`, the controller writes it in the response |
| ``Output($path, 'F')`` | `storeAs(string $path)` |

Mind PHP methods names are case insensitive: a private `addHtmlCell()` in a
plugin class now clashes with tc-lib-pdf's `addHTMLCell()`.

In HTML contents, images must be given as `data:` URIs (see `Pdf::getDataUri()`);
TCPDF `@` prefixed sources are not supported. Tables are not mirrored for
right-to-left languages, `Pdf::getHTMLTable()` builds a table that is.

The `fullcard` plugin is deprecated and has not been ported.
