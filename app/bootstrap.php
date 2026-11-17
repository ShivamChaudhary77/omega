<?php
/**
 * Loads configuration and every app/ module. Include this once from any
 * entry point (front controller, dev router, dashboard, tests).
 */

declare(strict_types=1);

if (defined('OMEGA_ROOT')) {
    return;
}

define('OMEGA_ROOT', dirname(__DIR__));
define('OMEGA_APP', __DIR__);
define('OMEGA_TEMPLATES', OMEGA_ROOT . '/templates');

/** Shared storage for the merged config (null until first loaded). */
function &omega_config_store(): ?array
{
    static $config = null;
    return $config;
}

/**
 * Merged configuration: app/config.php defaults, overridden by the optional
 * gitignored app/config.local.php. With a key, returns that value (or null).
 */
function config(?string $key = null)
{
    $config = &omega_config_store();
    if ($config === null) {
        $config = require OMEGA_APP . '/config.php';
        $local = OMEGA_APP . '/config.local.php';
        if (is_file($local)) {
            $overrides = require $local;
            if (is_array($overrides)) {
                $config = array_replace($config, $overrides);
            }
        }
    }
    return $key === null ? $config : ($config[$key] ?? null);
}

/** Override config values for the rest of the request (used by tests). */
function config_set(array $values): void
{
    config();
    $config = &omega_config_store();
    $config = array_replace($config, $values);
}

date_default_timezone_set((string) config('timezone'));
mb_internal_encoding('UTF-8');

require_once OMEGA_APP . '/helpers.php';
require_once OMEGA_APP . '/content.php';
require_once OMEGA_APP . '/cache.php';
require_once OMEGA_APP . '/render.php';
require_once OMEGA_APP . '/front.php';
