<!-- Bootstrap JS -->
<script src="<?= BASE ?>assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- Theme Functions -->
<script src="<?= BASE ?>assets/js/functions.js"></script>

<!-- Xomiton : hors ligne, SOS, validation, assistant -->
<script>window.XM = { base: '<?= BASE ?>', csrf: '<?= csrf_token() ?>', user: <?= json_encode(x_user() ? ['id' => x_user()['id'], 'name' => x_user()['name'], 'role' => x_user()['role']] : null) ?> };</script>
<script src="<?= BASE ?>assets/js/xomiton.js"></script>
<?php if (x_user() && empty($noSosFab)): ?>
<a class="xm-sos-fab no-print" href="<?= BASE ?>sos.php" title="Envoyer une alerte SOS avec ma position"><span class="pulse"></span> SOS</a>
<a class="btn btn-dark btn-sm xm-quick-exit no-print" href="https://www.google.com" title="Quitter rapidement ce site"><i class="bi bi-box-arrow-up-right me-1"></i>Sortie rapide</a>
<?php endif; ?>
