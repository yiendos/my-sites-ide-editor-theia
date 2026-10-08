# Theia IDE

[Theia IDE](https://theia-ide.org) for [my-sites-ide](https://github.com/yiendos/my-sites-ide):
a VS Code-style editor in the browser at http://localhost:3001, opened on the IDE's `Repos/`. It
comes with PHP 8.4, Composer, the Laravel installer and Claude Code in its terminal, and debugs
your sites through Xdebug.

Written for: developers running sites in my-sites-ide who want an editor inside the IDE,
including ones moving over from the Theia that used to ship inside the IDE.

## Contents

- [Installation](#installation)
- [Upgrading from the built-in Theia](#upgrading-from-the-built-in-theia)
- [Architecture](#architecture)
- [What's kept between containers](#whats-kept-between-containers)
- [Extensions](#extensions)
- [Debugging PHP with Xdebug](#debugging-php-with-xdebug)
- [Site workspaces](#site-workspaces)
- [Command reference](#command-reference)
- [Configuration](#configuration)
- [What it uses from the IDE](#what-it-uses-from-the-ide)
- [Troubleshooting](#troubleshooting)
- [Known gaps](#known-gaps)

## Installation

A [my-sites-ide](https://github.com/yiendos/my-sites-ide) plugin. Add it to the `require` section of the IDE's `composer.local.json`:

```json
"yiendos/my-sites-ide-editor-theia": "@dev"
```

Then, from the IDE root:

```
composer update
php my-sites-ide editor:theia-start
```

Composer's `post-autoload-dump` hook registers the `editor:theia-*` commands and the `theia`
compose service. Theia is opt-in: it doesn't autostart, so either keep `theia` in `APP` (the IDE's
`env-example` lists it) for `ide:spark` to start it, or run `editor:theia-start` when you want it.

The first `editor:theia-start` builds the `${NAMESPACE}_theia` image (a few minutes) and installs
the default extensions. Each site gets its own Theia workspace when it's created - see
[Site workspaces](#site-workspaces).

## Upgrading from the built-in Theia

Theia used to live in the IDE at `_dev/environment/editor/theia/`. The parts that moved:

| Before | Now |
|---|---|
| `_dev/environment/editor/theia/` | this package |
| port 3000 | port 3001 (`THEIA_PORT`) - 3000 is often taken by Grafana or Prometheus |
| a `conf` volume over all of `/home/theia` | only your own state, in `storage/plugins/theia/` - see below |
| the whole root `.env` passed into the container (`env_file`) | nothing passed in - Theia read none of it, and it put secrets such as API keys and passwords in the container's environment |
| `container_name: theia` | compose's own name, so two IDE checkouts don't clash. Other containers still reach it as `theia` |
| Theia 1.69.0 (Debian 11) | Theia 1.76.0 (Debian 12) - 1.69.0's Debian security packages are gone, so it no longer builds from scratch |
| PHP 8.4 with php-fpm | PHP 8.4 CLI only - sites run on the php plugin's fpm |
| Xdebug's port 9003 published on the host | not published - see [Debugging PHP with Xdebug](#debugging-php-with-xdebug) |

The old `conf` volume (`<project>_conf`) isn't used any more. Extensions you installed there need
installing again; `docker volume rm <project>_conf` removes it once you don't need it.

## Architecture

```
my-sites-ide CLI (host)
  |- editor:theia-start / -stop   --> docker compose up -d --build / stop theia
  |- editor:theia-extensions      --> .vsix files from open-vsx.org, unpacked into storage/plugins/theia/config/plugins/

browser --http://localhost:3001--> theia container
                                     |- /home/project            <-- Repos/
                                     |- /home/project/Packages   <-- Packages/
                                     |- /storage                 <-- storage/plugins/theia/
fpm / cli (php plugin) --Xdebug theia:9003--> theia container
```

The image is `ghcr.io/eclipse-theia/theia-ide/theia-ide:1.76.0` plus PHP 8.4 CLI (from
packages.sury.org), Composer, the Laravel installer and Claude Code. Theia runs as `theia`
(uid 100). Theia itself is on the IDE network, so its terminal reaches `mysql`, `redis` and the
rest by name.

## What's kept between containers

Everything worth keeping lives in `storage/plugins/theia/` on the host, mounted at `/storage`. The
rest of the container comes from the image, so a rebuild or a Theia upgrade reaches it.

| Folder | Container variable | What's in it |
|---|---|---|
| `config/` | `THEIA_CONFIG_DIR` | settings, keybindings, extensions - the ones `editor:theia-extensions` installs (`config/plugins/`) and the ones you install from the Extensions panel |
| `claude/` | `CLAUDE_CONFIG_DIR` | Claude Code's login and settings |
| `composer/` | `COMPOSER_HOME` | Composer's auth and cache |

So installing an extension, changing a setting or logging in to Claude Code survives stopping,
removing or rebuilding the container. Open tabs and panel layout are kept by your browser.

## Extensions

Extensions aren't baked into the image. `editor:theia-extensions` downloads pinned `.vsix` files
from [Open VSX](https://open-vsx.org) and unpacks them on the host into
`storage/plugins/theia/config/plugins/<namespace>.<name>/`, which Theia loads when it starts. The
default list, in `extensions.txt`:

| Extension | What it does |
|---|---|
| `xdebug.php-debug` | Xdebug client - breakpoints, stepping, variables |
| `bmewburn.vscode-intelephense-client` | PHP completion, go-to-definition, diagnostics |
| `laravel.vscode-laravel` | the official Laravel extension - completion and go-to for routes, views, config, env and Eloquent |
| `EditorConfig.EditorConfig` | follows a site's `.editorconfig` |

To change the list, copy `extensions.txt` to `storage/plugins/theia/extensions.txt` and edit it -
one `<namespace>.<name>@<version>` per line. `config/plugins/` belongs to the command: a changed
version replaces the extension, and one taken off the list is removed. It restarts Theia when it's
running, so reload the browser tab afterwards. Unpacking needs PHP's `zip` extension on the host.

Anything else, install from Theia's Extensions panel - it's kept in `config/` too, and the command
leaves it alone. Some that suit Laravel work:

| Extension | What it does |
|---|---|
| `shufo.vscode-blade-formatter` | formats Blade templates |
| `onecentlin.laravel-blade` | Blade syntax and snippets |
| `open-southeners.laravel-pint` | formats PHP with Pint on save |
| `recca0120.vscode-phpunit` | runs PHPUnit and Pest tests from a test panel |
| `SanderRonde.phpstan-vscode` | PHPStan / Larastan errors inline |
| `bradlc.vscode-tailwindcss` | Tailwind class completion |
| `mikestead.dotenv` | `.env` syntax highlighting |

Pick one PHP language server: DEVSENSE's PHP tools (`devsense.phptools-vscode`) clash with Intelephense.

## Debugging PHP with Xdebug

PHP runs in the php plugin's `fpm` and `cli` containers, with Xdebug connecting out to whatever
`XDEBUG_CLIENT_HOST` names. Theia listens for it on port 9003 inside the IDE network, so nothing is
published on the host - an editor on your machine keeps 9003 to itself.

1. Point Xdebug at Theia in the IDE's root `.env`, then restart the IDE (`ide:restart`):

   ```
   XDEBUG_CLIENT_HOST=theia
   ```

2. In Theia, open the site's workspace (File > Open Workspace,
   `/home/project/<site>/<site>.theia-workspace`) - it carries the launch configuration.
3. Run > Start Debugging, choosing **Listen for Xdebug**.
4. Set a breakpoint and load the page.

The configuration's `pathMappings` tell the debugger where fpm's paths are in Theia:

| fpm / cli | Theia |
|---|---|
| `/opt/repos/<site>` | the `<site>` folder (`/home/project/<site>`) |
| `/opt/Packages` | the `Packages` folder (`/home/project/Packages`) |

To go back to debugging in an editor on the host, set `XDEBUG_CLIENT_HOST=host.docker.internal`
(the php plugin's default).

## Site workspaces

Each new site gets a Theia workspace, `Repos/<site>/<site>.theia-workspace`: the plugin hooks
`editor:theia-workspace` to the IDE's `site-created` event, so `ide:create-site` and
`ide:repo-clone` write it. Open it with File > Open Workspace
(`/home/project/<site>/<site>.theia-workspace`) for a window on just that site. It has:

- **Two folders** - the site, and `Packages/` next to it
- **Git scanning three folders deep** (`git.repositoryScanMaxDepth`) - so the Source Control view
  finds the plugin repositories under `Packages/<vendor>/`
- **Intelephense settings** - PHP 8.4, the site's `vendor/` and IDE helper files
  (`_ide_helper.php`, `_ide_helper_models.php`, `.phpstorm.meta.php`) under `IDE_APP_DIR`
- **Listen for Xdebug** - mapping `/opt/repos/<site>` and `/opt/Packages` onto those two folders

It's written from `stubs/workspace.theia-workspace`, with `__PROJECT__` replaced by the site name
and `__APP_PATH__` by `<site>/<IDE_APP_DIR>`. A site that already has one is left alone. For a
site that predates the plugin, run `editor:theia-workspace <site>`, adding `--force` to replace one.

The file sits at the root of the site's repository, so a cloned repository shows it as untracked -
commit it, or add it to the repository's `.gitignore`.

## Command reference

| Command | What it does |
|---|---|
| `editor:theia-start` | `docker compose up -d --build theia`, then prints the address. Rebuilds the image when the Dockerfile has changed (from cache otherwise, in seconds) and installs the default extensions the first time. Also recreates a running container whose compose config has changed (e.g. a new `THEIA_PORT`) |
| `editor:theia-stop` | `docker compose stop theia`, leaving the rest of the IDE running |
| `editor:theia-workspace <site> [--force]` | Writes `Repos/<site>/<site>.theia-workspace` from the stub, unless the site has one. Runs on the `site-created` hook |
| `editor:theia-extensions [--force]` | Installs the extensions in the list that aren't there at that version (all of them with `--force`), removes ones taken off the list, and restarts Theia when it's running |

## Configuration

| Variable | Default | What it does |
|---|---|---|
| `THEIA_PORT` | `3001` (this plugin's `.env`) | The host port for the web UI |
| `XDEBUG_CLIENT_HOST` | `host.docker.internal` (the php plugin's `.env`) | Set to `theia` to debug in Theia |

Set either in the IDE's root `.env`, which wins over the plugins' defaults, then run
`editor:theia-start` (or `ide:restart` for `XDEBUG_CLIENT_HOST`).
`php my-sites-ide ide:plugin-env yiendos/my-sites-ide-editor-theia` copies them in, commented out.

## What it uses from the IDE

| From the IDE | Used for |
|---|---|
| `NAMESPACE` (root `.env`) | the image name, `${NAMESPACE}_theia` |
| `IDE_ROOT` (set by the CLI and `_dev/cache/ide.env`) | mounting `Repos/`, `Packages/` and `storage/plugins/theia/` |
| `storage/plugins/theia/` (`"storage": true`) | settings, extensions, Claude Code and Composer state |
| the `my-sites-ide` network | reaching `mysql`, `redis` and the rest, and Xdebug reaching `theia` |
| the php plugin's `XDEBUG_CLIENT_HOST` | pointing Xdebug at Theia |
| the `site-created` hook and `IDE_APP_DIR` | writing each new site's Theia workspace |

## Troubleshooting

**Breakpoints never hit.** Check `XDEBUG_CLIENT_HOST=theia` is in the root `.env` and the IDE was
restarted since (`docker compose exec fpm php -i | grep client_host` shows what fpm uses), that
**Listen for Xdebug** is running from the site's workspace, and that the file is in that site or
`Packages/` - the path mappings only cover those. No **Listen for Xdebug** to pick means Theia
isn't in a site workspace, or the site has none yet: run `editor:theia-workspace <site>`.

**An extension in the list isn't showing.** Theia loads `config/plugins/` when it starts:
run `editor:theia-extensions` (which restarts it) and reload the browser tab.
`editor:theia-extensions --force` unpacks everything again.

**`download failed`.** The name or version isn't on Open VSX - check
`https://open-vsx.org/extension/<namespace>/<name>`.

**Port 3001 is already allocated.** Another container or host process has it.
`docker ps --filter publish=3001` shows which one, or move Theia with `THEIA_PORT`.

**`laravel` or `claude` not found in a terminal.** They're on the image's `PATH`; a login shell
(`bash -l`) resets it - open a plain terminal instead.

## Known gaps

- On Linux hosts, `storage/plugins/theia/` is created by your user while Theia runs as uid 100, so
  it may not be able to write there. Docker Desktop on macOS maps ownership, so it isn't affected.
- The web UI has no authentication. It's published on all host interfaces, and its terminal runs
  commands in the IDE, so anyone on your network who can reach port 3001 can too.
- Claude Code updates itself inside the container; a recreate goes back to the version in the image
  until the next rebuild.
