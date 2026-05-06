# ZPMPackages Server Access Filament

Filament admin resources and page actions for the Laravel server-access stack. This package plugs into a Filament panel and exposes server, user, and SSH key management on top of `zpm-packages/server-access-laravel`.

## What This Package Adds

- `SshServerResource` for server records and current-system manager verification.
- Nested `SshUserResource` for server-scoped SSH users.
- `SshEntryResource` and user-level key relation management.
- Header actions for acting as a user, updating OS passwords, editing `authorized_keys`, editing `~/.ssh/config`, and confirming root-user deletion.
- Current-system scan actions for pulling OS users into the panel workflow.

## Installation

```bash
composer require zpm-packages/server-access-filament
```

This package expects `zpm-packages/server-access-laravel` to be installed and configured first.

## Register The Plugin

```php
use Filament\Panel;
use ZPMPackages\FilamentSshManagement\FilamentSshManagementPlugin;

public function panel(Panel $panel): Panel
{
	return $panel
		->plugin(FilamentSshManagementPlugin::make());
}
```

## Host App Requirements

The current resources are intentionally thin and reuse the host application's SSH wrappers. Your app must provide these classes:

- `App\Models\SshServer`
- `App\Models\SshUser`
- `App\Models\SshEntry`
- `App\Support\Ssh\SshManagerResolver`
- `App\Support\Ssh\SshDirectDataService`
- `App\Support\Ssh\SshSystemUserService`
- `App\Support\Ssh\SshUserManagerService`
- `App\Support\Ssh\SshActorAuthorizer`
- `App\Support\Ssh\DatabaseSshRepository`

If your app uses different namespaces or model names, adapt the package resources or provide compatible wrappers before enabling the plugin.

## Registered Resources

The plugin registers three resources on the active panel:

```php
SshServerResource::class,
SshUserResource::class,
SshEntryResource::class,
```

## Server Resource Usage

`SshServerResource` is the entrypoint for server records.

### What You Can Do

- List configured server records.
- Create and edit servers when database sync is enabled.
- Open nested user management from the server table.
- Change the verified manager user for the current system.
- Delete server records.

### Current-System Manager Verification

The edit page exposes a `Change Manager User` action for current-system servers. It verifies the selected OS user password before switching the configured manager.

## User Resource Usage

`SshUserResource` is nested under a server.

### Users List Page

The list page supports:

- Scanning system users.
- Creating a new user.
- Auto-importing current-system users into the database-backed repository on mount.
- Direct-mode record actions when database sync is disabled.

### Create User Flow

The create page supports:

- username, name, home directory, groups, comment, and root flag
- `can_read_entries`, `can_write_entries`, and `can_manage_entries`
- managed directories
- optional create-time password
- create-time SSH key passphrase forwarding when a managed key is generated

### Edit User Header Actions

The edit page currently provides:

- `Act As`
- `Authorized Keys`
- `SSH Config`
- `Update Password`
- `Delete`

The delete action requires password confirmation when the target user is root-equivalent.

## Key Management Usage

Keys are managed from the user relation manager and the standalone key resource.

### Supported Flows

- Create a manual key row.
- Generate a managed key pair.
- Set custom private and public key paths under the target `.ssh` directory.
- Edit an existing key.
- Delete a key.

### Related UI Behaviors

- Private/public path inputs stay constrained to the user `.ssh` directory.
- Public path can auto-fill from the chosen private path.
- Managed key generation failures show notifications instead of raw exceptions.

## Example Panel Registration

```php
use App\Providers\Filament\AdminPanelProvider;
use Filament\Panel;
use ZPMPackages\FilamentSshManagement\FilamentSshManagementPlugin;

class AdminPanelProvider extends PanelProvider
{
	public function panel(Panel $panel): Panel
	{
		return $panel
			->default()
			->id('admin')
			->path('admin')
			->plugin(FilamentSshManagementPlugin::make());
	}
}
```

## Example App Service Wrappers

The plugin expects the host app wrappers to adapt package services to app models. A typical wrapper extends the Laravel package service:

```php
namespace App\Support\Ssh;

class SshManagerResolver extends \ZPMPackages\LaravelSshManagement\Services\SshManagerResolver
{
}
```

## Testing

The package includes a lightweight standalone plugin test that verifies:

- the plugin id
- resource registration on a panel
- plugin resolution through the container

Run it from the package directory:

```bash
vendor/bin/phpunit tests/FilamentSshManagementPluginTest.php
```

## Notes

- This package is currently app-coupled by design; it is a reusable plugin package, but not a zero-configuration drop-in for arbitrary Laravel apps.
- The panel resources rely on the host app for authorization messaging and OS/file management wrappers.
- If you want a fully standalone Filament package, the next step is extracting the remaining `App\...` dependencies behind interfaces or plugin configuration.
