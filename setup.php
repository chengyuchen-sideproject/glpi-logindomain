<?php

/**
 * Login Domain - GLPI plugin
 *
 * @license GPL-3.0-or-later
 */

use Glpi\Plugin\Hooks;
use GlpiPlugin\Logindomain\LoginDomain;

define('PLUGIN_LOGINDOMAIN_VERSION', '1.0.0');
define('PLUGIN_LOGINDOMAIN_MIN_GLPI', '11.0.0');
define('PLUGIN_LOGINDOMAIN_MAX_GLPI', '11.0.99');

function plugin_version_logindomain(): array
{
    return [
        'name'         => 'Login domain',
        'version'      => PLUGIN_LOGINDOMAIN_VERSION,
        'author'       => 'chengyuchen-sideproject',
        'license'      => 'GPLv3+',
        'homepage'     => 'https://github.com/chengyuchen-sideproject/glpi-logindomain',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_LOGINDOMAIN_MIN_GLPI,
                'max' => PLUGIN_LOGINDOMAIN_MAX_GLPI,
            ],
        ],
    ];
}

function plugin_init_logindomain(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['logindomain'] = true;
    $PLUGIN_HOOKS['config_page']['logindomain']          = 'front/config.form.php';

    plugin_logindomain_apply_to_login_request();
}

/**
 * Called on every request while the plugin is active; only acts on the POST of the login form.
 * Plugins are booted before "front/login.php" runs, so the login it reads is already completed.
 */
function plugin_logindomain_apply_to_login_request(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    $path   = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if ($method !== 'POST' || !str_ends_with($path, '/front/login.php')) {
        return; // cheap exit before reading the configuration
    }

    $domains = LoginDomain::decodeDomains(
        Config::getConfigurationValue(LoginDomain::CONFIG_CONTEXT, LoginDomain::CONFIG_KEY)
    );
    $login = LoginDomain::rewrittenLogin($method, $path, $_POST, $domains);
    if ($login !== null) {
        $_POST['login_name'] = $login;
    }
}

function plugin_logindomain_check_prerequisites(): bool
{
    return true;
}

function plugin_logindomain_check_config($verbose = false): bool
{
    return true;
}
