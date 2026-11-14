<?php

/**
 * Login Domain - GLPI plugin
 *
 * @license GPL-3.0-or-later
 */

use GlpiPlugin\Logindomain\LoginDomain;

function plugin_logindomain_install(): bool
{
    // Keep an existing mapping on reinstall / upgrade
    $current = Config::getConfigurationValue(LoginDomain::CONFIG_CONTEXT, LoginDomain::CONFIG_KEY);
    if ($current === null) {
        Config::setConfigurationValues(LoginDomain::CONFIG_CONTEXT, [LoginDomain::CONFIG_KEY => '{}']);
    }
    return true;
}

function plugin_logindomain_uninstall(): bool
{
    Config::deleteConfigurationValues(LoginDomain::CONFIG_CONTEXT, [LoginDomain::CONFIG_KEY]);
    return true;
}
