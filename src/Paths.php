<?php

namespace Yiendos\MySitesIde\Editor\Theia;

use RuntimeException;

/**
 * Where this plugin's files live. Everything Theia should keep between
 * containers goes in the IDE's storage/plugins/theia/, mounted at /storage
 * ("storage": true in composer.json):
 *
 *   config/    THEIA_CONFIG_DIR - settings, keymaps, installed extensions
 *   claude/    CLAUDE_CONFIG_DIR - Claude Code's login and settings
 *   composer/  COMPOSER_HOME - Composer's auth and cache
 *
 * The IDE root comes from IDE_ROOT, which the my-sites-ide bootstrap sets
 * before any plugin command runs (and which docker-compose.yml interpolates).
 */
final class Paths
{
    public const STORAGE = 'storage/plugins/theia';

    /**
     * The folders under STORAGE the container expects (see docker-compose.yml)
     */
    public const STORAGE_FOLDERS = ['config', 'claude', 'composer'];

    /**
     * The my-sites-ide project root
     *
     * @return string
     */
    public static function root(): string
    {
        $root = getenv('IDE_ROOT');

        if ($root === false || $root === '') {
            throw new RuntimeException('IDE_ROOT is not set - run this command through the my-sites-ide CLI.');
        }

        return rtrim($root, '/');
    }

    /**
     * A path in the plugin's storage on the host, e.g. config/plugins,
     * created on first use - Docker would otherwise create the bind mount
     * itself, owned by root on Linux hosts
     *
     * @param string $path
     * @return string
     */
    public static function storage(string $path = ''): string
    {
        $storage = self::root() . '/' . self::STORAGE;

        if (!is_dir($storage)) {
            mkdir($storage, 0755, true);
        }

        return $path === '' ? $storage : "{$storage}/{$path}";
    }

    /**
     * The folder editor:theia-extensions unpacks into - Theia loads unpacked
     * extensions from <THEIA_CONFIG_DIR>/plugins when it starts
     *
     * @return string
     */
    public static function extensions(): string
    {
        return self::storage('config/plugins');
    }

    /**
     * Theia's workspace, the IDE's Repos/ (/home/project in the container)
     *
     * @param string $file
     * @return string
     */
    public static function workspace(string $file = ''): string
    {
        return self::root() . '/Repos' . ($file === '' ? '' : "/{$file}");
    }

    /**
     * A file shipped with this package, e.g. stubs/launch.json
     *
     * @param string $file
     * @return string
     */
    public static function package(string $file = ''): string
    {
        return dirname(__DIR__) . ($file === '' ? '' : "/{$file}");
    }

    /**
     * An absolute path shown relative to the IDE root, for user-facing messages
     *
     * @param string $path
     * @return string
     */
    public static function relative(string $path): string
    {
        $root = self::root() . '/';

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }
}
