<?php

/**
 * Unit tests for src/LoginDomain.php (pure PHP, no GLPI, no PHPUnit needed).
 * Run: php tests/run_tests.php      exit code 0 = all passed
 */

declare(strict_types=1);

require __DIR__ . '/../src/LoginDomain.php';

use GlpiPlugin\Logindomain\LoginDomain;

$failed = 0;
$total  = 0;

function check(string $name, mixed $expected, mixed $actual): void
{
    global $failed, $total;
    $total++;
    if ($expected === $actual) {
        echo "  ok    $name\n";
        return;
    }
    $failed++;
    echo "  FAIL  $name\n        expected: " . var_export($expected, true) . "\n        actual:   " . var_export($actual, true) . "\n";
}

$map  = [2 => 'ad.example.com'];
$post = fn (string $login, string $auth = 'ldap-2'): array => ['login_name' => $login, 'login_password' => 'x', 'auth' => $auth];
$rw   = fn (array $p, string $path = '/front/login.php', string $m = 'POST', array $d = []): ?string
    => LoginDomain::rewrittenLogin($m, $path, $p, $d ?: $GLOBALS['map']);

echo "rewrittenLogin\n";
check('plain login on mapped directory gets the domain', 'alice@ad.example.com', $rw($post('alice')));
check('surrounding spaces are trimmed', 'alice@ad.example.com', $rw($post('  alice ')));
check('login already containing @ is kept', null, $rw($post('alice@ad.example.com')));
check('login with another domain is kept', null, $rw($post('alice@other.example')));
check('NetBIOS DOMAIN\\user is kept', null, $rw($post('AD\\alice')));
check('empty login is kept', null, $rw($post('')));
check('unmapped LDAP directory is kept', null, $rw($post('alice', 'ldap-1')));
check('GLPI internal database (local) is kept', null, $rw($post('glpi', 'local')));
check('mail server source is kept', null, $rw($post('alice', 'mail-1')));
check('missing auth field is kept', null, $rw(['login_name' => 'alice']));
check('array login is ignored', null, $rw(['login_name' => ['a'], 'auth' => 'ldap-2']));
check('GET request is ignored', null, $rw($post('alice'), '/front/login.php', 'GET'));
check('other script is ignored', null, $rw($post('alice'), '/front/central.php'));
check('look-alike path is ignored', null, $rw($post('alice'), '/front/login.php.bak'));
check('GLPI under a sub path works', 'alice@ad.example.com', $rw($post('alice'), '/glpi/front/login.php'));
check('lower-case post method works', 'alice@ad.example.com', $rw($post('alice'), '/front/login.php', 'post'));
check('invalid domain in config does nothing', null, LoginDomain::rewrittenLogin('POST', '/front/login.php', $post('alice'), [2 => 'not a domain']));
check('auth with suffix is not matched', null, $rw($post('alice', 'ldap-2x')));

echo "normalizeDomain\n";
check('upper case and leading @', 'ad.example.com', LoginDomain::normalizeDomain(' @AD.Example.COM '));
check('single label rejected', '', LoginDomain::normalizeDomain('localhost'));
check('spaces rejected', '', LoginDomain::normalizeDomain('ad example.com'));
check('trailing dot rejected', '', LoginDomain::normalizeDomain('ad.example.com.'));
check('label starting with hyphen rejected', '', LoginDomain::normalizeDomain('-ad.example.com'));
check('slash / quote injection rejected', '', LoginDomain::normalizeDomain('ad.example.com"><script>'));
check('hyphen inside label ok', 'corp-ad.example.com', LoginDomain::normalizeDomain('corp-ad.example.com'));

echo "decodeDomains / encodeDomains\n";
check('null config', [], LoginDomain::decodeDomains(null));
check('broken JSON', [], LoginDomain::decodeDomains('{oops'));
check('valid JSON, bad entries dropped', [2 => 'ad.example.com'], LoginDomain::decodeDomains('{"2":"AD.example.com","0":"x.y","3":"bad","x":"a.b"}'));
check('round trip', '{"2":"ad.example.com","5":"corp.example"}', LoginDomain::encodeDomains([5 => 'corp.example', 2 => 'ad.example.com']));
check('empty map encodes to {}', '{}', LoginDomain::encodeDomains([]));

echo "\n$total tests, $failed failed\n";
exit($failed === 0 ? 0 : 1);
