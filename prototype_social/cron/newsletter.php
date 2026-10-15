<?php
/**
 * Tâche cron de la newsletter (hébergement mutualisé cPanel) — à lancer chaque minute :
 *   * * * * * php /chemin/vers/prototype_social/cron/newsletter.php >/dev/null 2>&1
 * Envoie les campagnes programmées arrivées à échéance et poursuit les envois en cours, par lots.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI uniquement.'); }
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTP_HOST'] = getenv('XOMITON_HOST') ?: 'xomiton.org';
$_SERVER['HTTPS'] = 'on';
ob_start();
require __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/x_newsletter.php';
ob_end_clean();
$n = nl_process_due();
fwrite(STDOUT, date('c') . ' newsletter : ' . $n . " campagne(s) traitée(s)\n");
