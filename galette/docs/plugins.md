# Plugin development {#plugins}

A plugin extends Galette with additional features without modifying the
core code. For changes required by a new Galette version, see
[Upgrading plugins to Galette 1.4](@ref upgrade14).

[TOC]

## Plugin structure

A plugin is a directory placed in `galette/plugins/`; the directory name is
the plugin ID (`plugin-events` has ID `plugin-events`).

A plugin **must** contain:

- `_define.php`: plugin metadata and registration;
- `lib/{Namespace}/PluginGalette{Route}.php`: the main plugin class.

A plugin without `_define.php` is not loaded. Routes are declared with
attributes on controllers; `_routes.php` is optional since Galette 1.4.0.

Typical layout:

```
plugin-myplugin/
├── _define.php              # Plugin metadata (required)
├── _routes.php              # Routes not declared with attributes (optional)
├── _config.inc.php          # Constants, loaded with _define.php
├── lib/
│   └── GaletteMyPlugin/     # Plugin namespace
│       ├── PluginGaletteMyPlugin.php  # Main plugin class (required)
│       ├── PluginEventProvider.php    # Events listeners (optional)
│       ├── Fixtures.php               # Sample data (optional)
│       ├── Controllers/
│       ├── Entity/
│       ├── Repository/
│       └── Filters/
├── templates/
│   └── default/             # Twig templates
│       ├── headers.html.twig  # Auto-included in <head>
│       └── scripts.html.twig  # Auto-included before </body>
├── scripts/                 # Database scripts
│   ├── mysql.sql
│   ├── pgsql.sql
│   ├── upgrade-to-1.1-mysql.sql
│   └── upgrade-to-1.1-pgsql.sql
├── lang/                    # Translations
├── webroot/                 # Public assets (CSS, JS, images)
└── tests/
```

## Registration: _define.php {#plugins_define}

Since Galette 1.4.0, `_define.php` returns a
`Galette\Core\Plugins\PluginDefinition`:

```php
<?php

declare(strict_types=1);

use Galette\Core\Plugins\PluginDefinition;

return new PluginDefinition(
    name: 'Galette My Plugin',     // Display name; also gives the namespace
    desc: 'My plugin description',
    author: 'Your Name',
    version: '1.0.0',              // Plugin version
    compver: '1.4.0',              // Galette compatibility version
    route: 'myplugin',             // Routes prefix and translation domain
    date: '2026-01-15',            // Release date
    acls: [                        // Routes access, see below
        'myplugin_list'   => 'member',
        'myplugin_edit'   => 'myplugin:edit',
        'myplugin_remove' => 'admin',
    ],
    dbver: 1.0                     // Database version, if the plugin has a scripts/ directory
);
```

The main plugin class is not loaded to read the definition, so an outdated
plugin is disabled before it breaks anything. A definition file that throws
an error, or that neither returns a definition nor calls the deprecated
`$this->register()`, disables the plugin as invalid.

- `compver` is mandatory: a plugin without it is disabled. It is not a
  minimum version but the Galette compatibility generation the plugin was
  written for. A plugin whose `compver` is older than the current
  `GALETTE_COMPAT_VERSION` is disabled. A newer one is accepted.
- `dbver` is mandatory as soon as the plugin has a `scripts/` directory;
  the plugin is disabled otherwise. When it differs from the installed
  version, the plugin is marked for update.
- `priority` (default `1000`) sorts plugins, lowest first.
- `repository` (`owner/name`) is where new releases are looked for; it
  defaults to `galette-plugins/<plugin ID>`.
- `csrf_exclusions` lists routes excluded from CSRF checks, see
  [CSRF exclusions](@ref plugins_csrf).

### Deprecated: $this->register()

Before Galette 1.4.0, `_define.php` called `$this->register()` with the
same named arguments, except `csrf_exclusions`. It still works, but is
deprecated since 1.4.0: a notice is logged in debug mode. A plugin that
must still run on Galette 1.3 keeps it, `PluginDefinition` does not exist
there.

```php
<?php

declare(strict_types=1);

/** @var \Galette\Core\Plugins $this */
$this->register(
    name: 'Galette My Plugin',
    desc: 'My plugin description',
    author: 'Your Name',
    version: '1.0.0',
    compver: '1.3.0',
    route: 'myplugin',
    date: '2026-01-15',
    acls: [
        'myplugin_list' => 'member',
    ],
    dbver: 1.0
);
```

## Main plugin class

The main class extends `Galette\Core\GalettePlugin`. Its name is
`PluginGalette` followed by the capitalized `route`. Its namespace is the
plugin name without spaces: `Galette My Plugin` gives `GaletteMyPlugin`.
Classes of every namespace directory under `lib/` are autoloaded (PSR-4).

The class is built from the DI container: use `#[Inject]` to get services.

```php
<?php

declare(strict_types=1);

namespace GaletteMyPlugin;

use DI\Attribute\Inject;
use Galette\Core\Db;
use Galette\Core\GalettePlugin;
use Galette\Core\Plugins\InstallableInterface;

class PluginGaletteMyPlugin extends GalettePlugin implements InstallableInterface
{
    #[Inject]
    protected Db $zdb;

    /**
     * Is the plugin fully installed (database, configuration, ...)?
     */
    public function isInstalled(): bool
    {
        return $this->zdb->tableExists('myplugin_items')  // Without prefix;
    }
}
```

Menus, dashboards, members actions and news come from the interfaces
below. Since Galette 1.4.0, `GalettePlugin` no longer defines their methods,
and the `*Contents()` static methods deprecated since 1.2.2 are no longer
called. `Plugins::getProviders($interface)` returns the installed plugins
implementing an interface.

## Plugin interfaces

All of them live in `Galette\Core\Plugins` and are implemented by the main
class, unless stated otherwise.

### PermissionsProviderInterface

Since Galette 1.4.0, declares the plugin permissions; see
[Permissions](@ref plugins_permissions).

### InstallableInterface

`isInstalled(): bool` tells if the plugin is fully installed.

`GalettePlugin::getLegacyDbVersion(): ?float` can also be overridden: for an
installation that predates plugins versions tracking (Galette 1.2), return
the version existing tables are at, so pending upgrade scripts are run.

### MenuProviderInterface

```php
public function getMenus(): array
{
    $menus = [];
    if ($this->login->isLogged()) {
        $menus['plugin_myplugin'] = [
            'title' => _T('My Plugin', 'myplugin'),
            'icon'  => 'star',  // Fomantic UI icon name
            'items' => [
                [
                    'label' => _T('List', 'myplugin'),
                    'route' => [
                        'name'    => 'myplugin_list',
                        'aliases' => ['myplugin_add', 'myplugin_edit'],
                    ],
                ],
            ],
        ];
    }
    return $menus;
}

public function getPublicMenus(): array
{
    return [
        [
            'label' => _T('My Plugin', 'myplugin'),
            'route' => ['name' => 'myplugin_public'],
            'icon'  => 'star',
        ],
    ];
}
```

`$this->login` is a `Galette\Core\Login` injected with `#[Inject]`.

### DashboardProviderInterface

```php
public function getDashboards(): array
{
    return [
        [
            'label' => _T('My Feature', 'myplugin'),
            'title' => _T('Access my feature', 'myplugin'),
            'route' => ['name' => 'myplugin_list'],
            'icon'  => 'star',
        ],
    ];
}

public function getMyDashboards(): array
{
    return [];  // Entries of the "My information" section
}
```

### MemberActionProviderInterface

Actions on members lists, member details, and batch actions:

```php
public function getListActions(Adherent $member): array
{
    return [
        [
            'label' => _T('Do something', 'myplugin'),
            'route' => [
                'name' => 'myplugin_member_action',
                'args' => ['id_adh' => $member->id],
            ],
            'icon'  => 'star grey',
        ],
    ];
}

public function getDetailedActions(Adherent $member): array
{
    return $this->getListActions($member);
}

public function getBatchActions(): array
{
    return [];
}
```

### NewsProviderInterface

```php
use Galette\IO\News\Entry;
use Galette\IO\News\Post;

public function getNews(): ?Entry
{
    return new Entry(
        _T('My Plugin News', 'myplugin'),
        [new Post(title: 'Something happened', url: 'https://example.org', date: '2026-01-15')]
    );
}
```

### PreferencesProviderInterface

Stores the plugin settings in Galette preferences rather than in a table of
its own: they get a type, validation, the usual `$preferences->name` access,
and a row on the advanced configuration page.

`getPreferences()` returns entries keyed `pref_<route>_<name>`; values are
`PreferencesSchema` entries: a known `type`, a scalar `default`, and
optionally `min`, `max`, `error`, `sensitive`, `acl`. Anything else is
dropped and reported.

### PublicPagesProviderInterface

Gives each public page of the plugin its own visibility, set on the
settings page next to the core public pages.

- `getPublicPages()` returns pages keyed by an identifier (lowercase
  letters, digits, underscores; never change it once released), each with
  the `routes` making the page and an optional `default`, a
  `Galette\Enums\PublicPageVisibility` value.
- `getPublicPageLabel(string $id)` returns the label shown in settings;
  `getPublicPages()` is read before translations are loaded.

Declaring a page does not protect it: its routes still need the
`Galette\Middleware\PublicPages` middleware.

### FixturesProviderInterface

Lets `galette:seed-fixtures` create sample data tied to core fixture members
and groups. It is implemented by a `Fixtures` class in the plugin namespace,
not by the main class.

- `seedFixtures(FixturesContext $context): string` creates data and returns
  a one line summary; use dates relative to the current day.
  `$context->getMemberId($position)` and `getGroupId($position)` give core
  fixtures identifiers.
- `cleanFixtures(FixturesContext $context): void` removes only what
  `seedFixtures()` creates, and works when nothing has been seeded.

## Routes {#plugins_routes}

Since Galette 1.4.0, routes are declared with
`Galette\Controllers\Attributes\Route` attributes on controllers methods.
Controllers of `lib/{Namespace}/Controllers/` are scanned; patterns are
relative to the plugin group, `/plugins/<route>`.

```php
use Galette\Controllers\Attributes\Route;

class MyController extends AbstractPluginController
{
    #[Route(name: 'myplugin_list', pattern: '/list[/{option:page|order}/{value:\d+}]')]
    public function list(Request $request, Response $response): Response
    {
        // ...
    }

    #[Route(name: 'myplugin_edit', pattern: '/edit/{id:\d+}')]
    public function edit(Request $request, Response $response, int $id): Response
    {
        // ...
    }

    #[Route(name: 'myplugin_do_edit', pattern: '/edit/{id:\d+}', methods: 'POST')]
    public function doEdit(Request $request, Response $response, int $id): Response
    {
        // ...
    }
}
```

- `methods` is `GET` by default; a string or a list.
- `requiresAuth` (`true` by default) adds the authentication middleware:
  the route must then have an [ACL](@ref plugins_acls). Set it to `false`
  for public routes.
- `middleware` lists extra middlewares classes, run after authentication.
- `handler` names the method, for a `Route` set on the class to call an
  inherited method.
- `fallback: true` registers the route after the others, for a pattern
  that would shadow some of them.
- The attribute is repeatable: one method can serve several routes.

### _routes.php

Routes that cannot be declared with attributes still go in `_routes.php`,
with Slim 4. It is optional since Galette 1.4.0, and was required before.

```php
<?php

declare(strict_types=1);

use Galette\Middleware\Authenticate;
use GaletteMyPlugin\Controllers\MyController;

$app->get(
    '/list[/{option:page|order}/{value:\d+}]',
    [MyController::class, 'list']
)->setName('myplugin_list')->add(Authenticate::class);
```

Routes without the `Authenticate` middleware are public.

Variables available in `_routes.php`:

- `$app`: the route collector of the plugin group;
- `$module`: plugin information (`root`, `name`, `route`, …);
- `$module_id`: plugin ID, kept for compatibility;
- `$container`: the DI container, kept for compatibility.

### Access control (ACLs) {#plugins_acls}

Each authenticated route name must be listed in the `acls` of the plugin
definition, with what it requires:

- `member`: any logged-in member;
- `groupmanager`: group managers and above;
- `staff`: staff members and above;
- `admin`: administrators;
- `superadmin`: super administrator only;
- since Galette 1.4.0, a permission name, `domain:action`; several
  permissions separated with a pipe mean any of them. See
  [Permissions](@ref plugins_permissions).

A key can also be a regular expression matching route names. A route that
is not listed raises an error when it is accessed. Core ACLs take
precedence over plugins ones.

```php
acls: [
    'myplugin_list'   => 'member',
    'myplugin_edit'   => 'myplugin:edit',
    'myplugin_remove' => 'myplugin:remove',
    'myplugin_export' => 'member:export|member:print',  // core permissions work too
]
```

### Permissions {#plugins_permissions}

Since Galette 1.4.0, roles made of permissions replace access levels and
group managers preferences. A plugin can declare its own permissions,
named `domain:action`, from its main class implementing
`Galette\Core\Plugins\PermissionsProviderInterface`:

```php
use Galette\Access\Permission;
use Galette\Core\Authentication;
use Galette\Core\Plugins\PermissionsProviderInterface;

class PluginGaletteMyPlugin extends GalettePlugin implements PermissionsProviderInterface
{
    public function getPermissions(): array
    {
        return [
            new Permission(
                name: 'myplugin:edit',
                label: _T('Edit things', 'myplugin'),
                level: Authentication::ACCESS_STAFF,
                managers: true,
                owner: true
            ),
            new Permission('myplugin:remove', _T('Remove things', 'myplugin'), Authentication::ACCESS_ADMIN),
        ];
    }
}
```

- The domain is the plugin route, `myplugin` here. Permissions of another
  domain are ignored, and a plugin cannot use a core domain.
- Names are stored in roles: never change them once released.
- `level` is the lowest access level whose system role gets the
  permission: `ACCESS_STAFF` gives it to the staff member role, which
  administrators inherit; `ACCESS_ADMIN` (the default) to the administrator
  role only.
- `managers: true` grants the group manager role, on managed groups;
  `members: true` grants the member role.
- `owner: true` grants members on what they own, or what their children own.
- `group: false` marks a permission that does not apply to members data
  (settings, logs, …): it cannot be granted for a group.
- These rules are what system roles get when the permission is first
  known; administrators then change roles as they see fit.
- `getPermissions()` is called once translations are loaded: labels are
  translated in the plugin domain.

Permissions are checked with `Galette\Access\AccessControl`, available in
controllers as `$this->accessControl`, and with the `can()` Twig function.
The second parameter is the object the permission applies to:

```php
if (!$this->accessControl->can('myplugin:edit', $thing)) {
    // redirect with an error
}
```

```twig
{% if can('myplugin:edit', thing) %}
    <a href="{{ url_for('myplugin_edit', {'id': thing.getId()}) }}">{{ _T('Edit', 'myplugin') }}</a>
{% endif %}
```

Owners and group managers rules need to know who owns an object and which
groups it belongs to: plugin objects implement
`Galette\Interfaces\AccessSubjectInterface`:

```php
use Galette\Interfaces\AccessSubjectInterface;

class Thing implements AccessSubjectInterface
{
    public function getOwnerId(): ?int
    {
        return $this->member_id;  // Member the object belongs to, if any
    }

    public function getGroupsIds(): ?array
    {
        return null;  // Groups the object belongs to; null for those of its owner
    }
}
```

To restrict a list, `AccessControl::getGroupScope()` gives the groups a
permission is granted on: `null` for all of them, an empty array when it
is not granted at all. The `permission_scope()` Twig function does the
same. Objects can use the `Galette\Features\AccessControlled` trait, which
provides `isGranted()` and `getGroupScope()`.

Core permissions are listed on the roles administration page; among them
`member:read`, `member:edit`, `member:export`, `member:print`,
`contribution:read`, `contribution:create`, `group:read`, `mailing:send`.

### CSRF exclusions {#plugins_csrf}

A route receiving external callbacks (payment notifications, for example)
can be excluded from CSRF protection with the `csrf_exclusions` of the
plugin definition, a list of route names patterns:

```php
return new PluginDefinition(
    // ...
    csrf_exclusions: ['/myplugin_(success|notify|cancelled)/']
);
```

Before Galette 1.4.0, `_define.php` called
`$this->setCsrfExclusions([...])` next to `$this->register()`.

## Controllers

Controllers extend `Galette\Controllers\Crud\AbstractPluginController`
(CRUD) or `Galette\Controllers\AbstractPluginController`. Plugin
information is injected under the `Plugin <plugin name>` key:

```php
<?php

declare(strict_types=1);

namespace GaletteMyPlugin\Controllers;

use DI\Attribute\Inject;
use Galette\Controllers\Crud\AbstractPluginController;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class MyController extends AbstractPluginController
{
    /** @var array<string, mixed> */
    #[Inject("Plugin Galette My Plugin")]
    protected array $module_info;

    public function list(Request $request, Response $response): Response
    {
        $this->view->render(
            $response,
            $this->getTemplate('list'),  // @PluginGaletteMyPlugin/list.html.twig
            [
                'page_title' => _T('My Plugin List', 'myplugin'),
                'module_id'  => $this->getModuleId(),
            ]
        );
        return $response;
    }
}
```

`$this->module_info['module']['route']` gives the plugin route.
Other services are injected the same way, with `#[Inject]` on typed
properties.

## Templates

Templates are in `templates/default/`, in the `@PluginGalette{Route}`
Twig namespace, and usually extend the core layout:

```twig
{% extends "page.html.twig" %}

{% block content %}
    <div class="ui segment">
        {# Your content here #}
    </div>
{% endblock %}
```

When they exist, `headers.html.twig` is included in `<head>` and
`scripts.html.twig` before `</body>` of every page.

Static files of `webroot/` are served by the `plugin_res` route:

```twig
<link rel="stylesheet" href="{{ url_for('plugin_res', {'plugin': module_id, 'path': 'myplugin.css'}) }}">
<script src="{{ url_for('plugin_res', {'plugin': module_id, 'path': 'myplugin.js'}) }}"></script>
```

## Database

### Scripts

A plugin needing tables ships `scripts/mysql.sql` and `scripts/pgsql.sql`,
run from the plugins management interface. Tables use the `galette_`
prefix (replaced by the instance prefix).

Upgrades are `scripts/upgrade-to-<version>-mysql.sql` and
`scripts/upgrade-to-<version>-pgsql.sql` (or `upgrade-to-<version>.php`);
scripts with a version greater than the installed one are run, in order.
Bump `dbver` in `_define.php` accordingly.

### Laminas-Db

`Galette\Core\Db` builds and runs queries; table names are given without
prefix:

```php
$select = $this->zdb->select('myplugin_items');
$select->where(['id_adh' => $this->login->id]);
$results = $this->zdb->execute($select);

$insert = $this->zdb->insert('myplugin_items');
$insert->values(['name' => $name, 'id_adh' => $id_adh]);
$this->zdb->execute($insert);
$id = $this->zdb->getLastGeneratedValue($this);  // From the entity
```

### AbstractEntity

`Galette\Entity\AbstractEntity` (since 1.3.0) provides CRUD operations from
PHP attributes:

```php
<?php

declare(strict_types=1);

namespace GaletteMyPlugin\Entity;

use Galette\Entity\AbstractEntity;
use Galette\Entity\Attributes\Column;

class Item extends AbstractEntity
{
    public const TABLE = 'myplugin_items';  // Without prefix
    public const PK = 'id_item';

    #[Column(name: 'id_item')]
    protected int $id;

    #[Column]
    protected string $name;

    #[Column(name: 'creation_date', insertable: true, updatable: false)]
    protected string $creationDate;

    protected function preInsert(): bool
    {
        $this->creationDate = date('Y-m-d');
        return true;  // false cancels the operation
    }
}
```

`#[Column]` accepts `name` (defaults to the property name), `type` (one of
`Column::TYPE_STRING`, `TYPE_INT`, `TYPE_BOOL`, `TYPE_DATE`,
`TYPE_DATETIME`, `TYPE_TIMESTAMP`, `TYPE_FLOAT`, `TYPE_JSON`; inferred from
the PHP type otherwise), `insertable` and `updatable` (both `true` by
default).

Entities are handled with `save()` (insert or update), `load(int $id)`,
`loadFromRS()` and `delete()`; `preInsert()`, `postInsert()`,
`preUpdate()`, `postUpdate()`, `preDelete()` and `postDelete()` hooks can
be overridden.

## Events

Galette dispatches events with
[league/event](https://event.thephpleague.com/). Each is a
`Galette\Events\GaletteEvent`, whose `getObject()` returns the object
concerned:

| Object | Events |
|--------|--------|
| Member | `member.add`, `member.edit`, `member.before_remove`, `member.remove` |
| Contribution | `contribution.add`, `contribution.edit`, `contribution.remove` |
| Transaction | `transaction.add`, `transaction.edit`, `transaction.remove` |
| Group | `group.add`, `group.edit`, `group.before_remove`, `group.remove` |

To listen to them, add a `PluginEventProvider` class to the plugin
namespace, implementing `League\Event\ListenerSubscriber`. It is built
from the container, so `#[Inject]` works:

```php
<?php

declare(strict_types=1);

namespace GaletteMyPlugin;

use DI\Attribute\Inject;
use Galette\Core\Db;
use Galette\Entity\Adherent;
use Galette\Events\GaletteEvent;
use League\Event\ListenerRegistry;
use League\Event\ListenerSubscriber;

class PluginEventProvider implements ListenerSubscriber
{
    #[Inject]
    protected Db $zdb;

    public function subscribeListeners(ListenerRegistry $acceptor): void
    {
        $acceptor->subscribeTo(
            'member.add',
            function (GaletteEvent $event): void {
                /** @var Adherent $member */
                $member = $event->getObject();
                // ...
            }
        );
    }
}
```

`Galette\Events\MemberListener` and `ContribListener` are core examples.

## Translations

Plugins use gettext; the domain is the plugin `route`:

```php
_T('My string', 'myplugin');
```

```twig
{{ _T('My string', 'myplugin') }}
```

Sources are `lang/<domain>.pot` and `lang/<domain>_<locale>.utf8.po`; MO files
are compiled by `bin/console galette:compile-locales` and are not
versioned.

## Disabling a plugin

Plugins are enabled and disabled from the plugins management interface,
which writes a `plugin_<id>_disabled` file in the plugins data directory.
An `_disabled` file in the plugin directory still works, but is deprecated
and migrated automatically.

## Examples

Existing plugins, on [galette-plugins](https://github.com/galette-plugins),
are the best examples:

- `plugin-events`: CRUD, public pages, events, fixtures;
- `plugin-paypal`: payment callbacks and CSRF exclusions;
- `plugin-maps`: JavaScript integration.
