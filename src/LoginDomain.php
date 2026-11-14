<?php

/**
 * Login Domain - GLPI plugin
 *
 * Appends a domain suffix (e.g. "@ad.example.com") to the login typed on the GLPI login page,
 * for the LDAP directories it is configured for. Typical use: GLPI authenticates through the
 * FreeIPA compat tree, where Active Directory (trust) users are named "user@ad.domain".
 *
 * @license GPL-3.0-or-later
 */

namespace GlpiPlugin\Logindomain;

/**
 * Pure logic, no GLPI dependency (unit tested in tests/run_tests.php).
 */
final class LoginDomain
{
    /** GLPI configuration context and key holding the mapping */
    public const CONFIG_CONTEXT = 'plugin:logindomain';
    public const CONFIG_KEY     = 'domains';

    /**
     * Decide whether the login of this request must get a domain suffix.
     *
     * @param string               $method  HTTP method of the request
     * @param string               $path    request path (without query string)
     * @param array<string, mixed> $post    POST parameters
     * @param array<int, string>   $domains map "LDAP directory id" => "domain"
     *
     * @return string|null the new login, or null to leave the request untouched
     */
    public static function rewrittenLogin(string $method, string $path, array $post, array $domains): ?string
    {
        if (strtoupper($method) !== 'POST') {
            return null;
        }
        // GLPI may be served from a sub path (e.g. /glpi/front/login.php)
        if (preg_match('#/front/login\.php$#', $path) !== 1) {
            return null;
        }

        $login = $post['login_name'] ?? null;
        $auth  = $post['auth'] ?? null;
        if (!is_string($login) || !is_string($auth)) {
            return null;
        }

        $login = trim($login);
        // Empty, already qualified ("user@domain") or NetBIOS style ("DOMAIN\user"): keep as typed
        if ($login === '' || str_contains($login, '@') || str_contains($login, '\\')) {
            return null;
        }

        // Login source chosen on the login page: "ldap-<id>" for an LDAP directory
        if (preg_match('/^ldap-(\d+)$/', $auth, $m) !== 1) {
            return null;
        }
        $domain = self::normalizeDomain((string) ($domains[(int) $m[1]] ?? ''));
        if ($domain === '') {
            return null;
        }

        return $login . '@' . $domain;
    }

    /**
     * Lower-case, strip a leading "@" and validate a DNS domain name.
     *
     * @return string the domain, or "" when it is not a valid domain name
     */
    public static function normalizeDomain(string $domain): string
    {
        $domain = ltrim(strtolower(trim($domain)), '@');
        $label  = '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?';
        if (strlen($domain) > 253 || preg_match('/^' . $label . '(?:\.' . $label . ')+$/', $domain) !== 1) {
            return '';
        }
        return $domain;
    }

    /**
     * Read the mapping stored in the GLPI configuration (JSON object).
     *
     * @return array<int, string>
     */
    public static function decodeDomains(mixed $json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }
        $domains = [];
        foreach ($data as $id => $domain) {
            $id     = (int) $id;
            $domain = is_string($domain) ? self::normalizeDomain($domain) : '';
            if ($id > 0 && $domain !== '') {
                $domains[$id] = $domain;
            }
        }
        ksort($domains);
        return $domains;
    }

    /**
     * Serialize the mapping for the GLPI configuration.
     *
     * @param array<int, string> $domains
     */
    public static function encodeDomains(array $domains): string
    {
        $clean = self::decodeDomains(json_encode((object) $domains) ?: '');
        return json_encode((object) $clean, JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
