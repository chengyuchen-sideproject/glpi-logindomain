<?php

/**
 * Login Domain - configuration page (Setup > Plugins > Login domain)
 *
 * @license GPL-3.0-or-later
 */

use Glpi\Event;
use GlpiPlugin\Logindomain\LoginDomain;

Session::checkRight('config', UPDATE);

/**
 * @var DBmysql $DB
 * @var array   $CFG_GLPI
 */
global $DB, $CFG_GLPI;

$current = LoginDomain::decodeDomains(
    Config::getConfigurationValue(LoginDomain::CONFIG_CONTEXT, LoginDomain::CONFIG_KEY)
);

if (isset($_POST['update'])) {
    $domains = [];
    $posted  = is_array($_POST['domain'] ?? null) ? $_POST['domain'] : [];
    foreach ($posted as $id => $raw) {
        $raw = trim((string) $raw);
        if ($raw === '') {
            continue;
        }
        $domain = LoginDomain::normalizeDomain($raw);
        if ($domain === '' || (int) $id <= 0) {
            Session::addMessageAfterRedirect(
                htmlescape(sprintf(__('Invalid domain "%s" was not saved.', 'logindomain'), $raw)),
                false,
                ERROR
            );
            continue;
        }
        $domains[(int) $id] = $domain;
    }
    $before = LoginDomain::encodeDomains($current);
    $after  = LoginDomain::encodeDomains($domains);
    Config::setConfigurationValues(LoginDomain::CONFIG_CONTEXT, [LoginDomain::CONFIG_KEY => $after]);
    if ($after !== $before) {
        // Setup > Logs (Administration > Logs): who changed what, so the change can be reverted by hand
        Event::log(0, 'system', 4, 'setup', sprintf('logindomain mapping changed from %s to %s', $before, $after));
    }
    Session::addMessageAfterRedirect(htmlescape(__('Login domains saved.', 'logindomain')));
    Html::redirect($CFG_GLPI['root_doc'] . '/plugins/logindomain/front/config.form.php');
}

Html::header(__('Login domain', 'logindomain'), '', 'config', 'plugin');

$directories = $DB->request([
    'SELECT' => ['id', 'name', 'host', 'port', 'basedn', 'is_active'],
    'FROM'   => 'glpi_authldaps',
    'ORDER'  => 'id',
]);

echo '<div class="card m-3"><div class="card-body">';
echo '<h3>' . htmlescape(__('Login domain', 'logindomain')) . '</h3>';
echo '<p>' . htmlescape(__(
    'For each LDAP directory, the domain to append when the user types a login without "@" and chooses that directory as login source. Leave empty to change nothing.',
    'logindomain'
)) . '</p>';
echo '<form method="post">'; // posts back to this page
echo '<table class="table table-hover"><thead><tr>';
foreach (['ID', __('Name'), __('Server'), 'BaseDN', __('Active'), __('Domain to append', 'logindomain')] as $title) {
    echo '<th>' . htmlescape($title) . '</th>';
}
echo '</tr></thead><tbody>';
$count = 0;
foreach ($directories as $row) {
    $count++;
    $id = (int) $row['id'];
    echo '<tr>';
    echo '<td>' . $id . '</td>';
    echo '<td>' . htmlescape($row['name']) . '</td>';
    echo '<td>' . htmlescape($row['host'] . ':' . $row['port']) . '</td>';
    echo '<td>' . htmlescape($row['basedn']) . '</td>';
    echo '<td>' . ($row['is_active'] ? htmlescape(__('Yes')) : htmlescape(__('No'))) . '</td>';
    echo '<td><input type="text" class="form-control" name="domain[' . $id . ']" placeholder="ad.example.com" value="'
        . htmlescape($current[$id] ?? '') . '"></td>';
    echo '</tr>';
}
if ($count === 0) {
    echo '<tr><td colspan="6">' . htmlescape(__('No LDAP directory defined.', 'logindomain')) . '</td></tr>';
}
echo '</tbody></table>';
echo '<button type="submit" name="update" value="1" class="btn btn-primary">' . htmlescape(_sx('button', 'Save')) . '</button>';
Html::closeForm();
echo '</div></div>';

Html::footer();
