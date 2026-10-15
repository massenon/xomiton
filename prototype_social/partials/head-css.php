<!-- Plugins CSS -->
<link rel="stylesheet" type="text/css" href="<?= BASE ?>assets/vendor/@fortawesome/fontawesome-free/css/all.min.css">
<link rel="stylesheet" type="text/css" href="<?= BASE ?>assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css">

<!-- Theme CSS -->
<link rel="stylesheet" type="text/css" href="<?= BASE ?>assets/css/style.css">
<link rel="stylesheet" type="text/css" href="<?= BASE ?>assets/css/tokens.css">
<link rel="stylesheet" type="text/css" href="<?= BASE ?>assets/css/social-xomiton.css">
<link rel="stylesheet" type="text/css" href="<?= BASE ?>assets/css/components.css">
<link rel="stylesheet" type="text/css" href="<?= BASE ?>assets/css/xomiton.css">

<!-- Dark mode -->
<script>
	const storedTheme = localStorage.getItem('theme')

	const getPreferredTheme = () => {
		if (storedTheme) {
			return storedTheme
		}
		return window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'light'
	}

	const setTheme = function (theme) {
		if (theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
			document.documentElement.setAttribute('data-bs-theme', 'dark')
		} else {
			document.documentElement.setAttribute('data-bs-theme', theme)
		}
	}

	setTheme(getPreferredTheme())

	window.addEventListener('DOMContentLoaded', () => {
		var el = document.querySelector('.theme-icon-active');
		if (el != 'undefined' && el != null) {
			const showActiveTheme = theme => {
				const activeThemeIcon = document.querySelector('.theme-icon-active use')
				const btnToActive = document.querySelector(`[data-bs-theme-value="${theme}"]`)
				const svgOfActiveBtn = btnToActive.querySelector('.mode-switch use').getAttribute('href')

				document.querySelectorAll('[data-bs-theme-value]').forEach(element => {
					element.classList.remove('active')
				})

				btnToActive.classList.add('active')
				activeThemeIcon.setAttribute('href', svgOfActiveBtn)
			}

			window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
				if (storedTheme !== 'light' || storedTheme !== 'dark') {
					setTheme(getPreferredTheme())
				}
			})

			showActiveTheme(getPreferredTheme())

			document.querySelectorAll('[data-bs-theme-value]')
				.forEach(toggle => {
					toggle.addEventListener('click', () => {
						const theme = toggle.getAttribute('data-bs-theme-value')
						localStorage.setItem('theme', theme)
						setTheme(theme)
						showActiveTheme(theme)
					})
				})

		}
	})

</script>