	</div>
	<footer class="px-4 py-3 small text-secondary border-top no-print">
		Xomiton – Administration · version <?= e(setting('platform_version', '1.0')) ?> · <?= date('d/m/Y H:i') ?>
	</footer>
</div>
<?php $noSosFab = true; include __DIR__ . '/footer-scripts.php'; ?>
<script src="<?= BASE ?>assets/js/xomiton-admin.js"></script>
<script>
document.getElementById('xmSidebarToggle')?.addEventListener('click', () => document.getElementById('xmSidebar').classList.toggle('open'));
/* Palette de graphiques Xomiton */
window.XM_COLORS = ['#0433C3', '#E30E0E', '#6F2C91', '#0B7A4B', '#F0A800', '#C2255C', '#1864AB', '#B84B00'];
if (window.Chart) { Chart.defaults.font.family = 'Montserrat, Inter, sans-serif'; Chart.defaults.plugins.legend.position = 'bottom'; }
</script>
<?= $adminScripts ?? '' ?>
</body>
</html>
