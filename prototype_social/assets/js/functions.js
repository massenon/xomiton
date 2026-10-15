/**
* Social - Network, Community and Event Theme
*
* @author StackBros 
* @version 1.1.2
**/


/* ===================
Table Of Content
======================
01 PRELOADER
02 NAVBAR DROPDOWN HOVER
03 TINY SLIDER
04 TOOLTIP
05 POPOVER
06 VIDEO PLAYER
07 GLIGHTBOX
08 SIDEBAR TOGGLE START
09 SIDEBAR TOGGLE END
10 CHOICES
11 AUTO RESIZE TEXTAREA
12 DROP ZONE
13 FLAT PICKER
14 AVATAR IMAGE
15 CUSTOM SCROLLBAR
16 TOASTS
17 PSWMETER
18 FAKE PASSWORD
====================== */


"use strict";


const e = {
	init: function () {
		e.preLoader();
		e.navbarDropdownHover();
		e.tinySlider();
		e.toolTipFunc();
		e.popOverFunc();
		e.videoPlyr();
		e.lightBox();
		e.sidebarToggleStart();
		e.sidebarToggleEnd();
		e.choicesSelect();
		e.autoResize();
		e.DropZone();
		e.flatPicker();
		e.avatarImg();
		e.customScrollbar();
		e.toasts();
		e.pswMeter();
		e.fakePwd();
	},

	isVariableDefined: function (el) {
		return typeof el !== 'undefined' && el !== null;
	},

	getParents: function (el, selector, filter) {
		const result = [];
		const matchesSelector = el.matches || el.webkitMatchesSelector || el.mozMatchesSelector || el.msMatchesSelector;

		el = el.parentElement;
		while (el && !matchesSelector.call(el, selector)) {
			if (!filter) {
				if (selector && matchesSelector.call(el, selector)) {
					result.push(el);
					return result;
				} else {
					result.push(el);
				}
			} else {
				if (matchesSelector.call(el, filter)) {
					result.push(el);
				}
			}
			el = el.parentElement;
			if (e.isVariableDefined(el) && matchesSelector.call(el, selector)) {
				return el;
			}
		}
		return result;
	},

	getNextSiblings: function (el, selector, filter) {
		const sibs = [];
		let nextElem = el.parentNode.firstChild;
		const matchesSelector = el.matches || el.webkitMatchesSelector || el.mozMatchesSelector || el.msMatchesSelector;

		do {
			if (nextElem.nodeType === 3 || nextElem === el) continue;
			if (nextElem === el.nextElementSibling) {
				if (!filter || filter(el)) {
					if (selector) {
						if (matchesSelector.call(nextElem, selector)) return nextElem;
					} else {
						sibs.push(nextElem);
					}
					el = nextElem;
				}
			}
		} while ((nextElem = nextElem.nextSibling));
		return sibs;
	},

	on: function (selectors, type, listener) {
		document.addEventListener("DOMContentLoaded", () => {
			if (!(selectors instanceof HTMLElement) && selectors !== null) {
				selectors = document.querySelector(selectors);
			}
			selectors.addEventListener(type, listener);
		});
	},

	onAll: function (selectors, type, listener) {
		document.addEventListener("DOMContentLoaded", () => {
			document.querySelectorAll(selectors).forEach((element) => {
				if (type.indexOf(',') > -1) {
					const types = type.split(',');
					types.forEach((t) => {
						element.addEventListener(t, listener);
					});
				} else {
					element.addEventListener(type, listener);
				}
			});
		});
	},

	removeClass: function (selectors, className) {
		if (!(selectors instanceof HTMLElement) && selectors !== null) {
			selectors = document.querySelector(selectors);
		}
		if (e.isVariableDefined(selectors) && className) {
			selectors.classList.remove(className);
		}
	},

	removeAllClass: function (selectors, className) {
		if (e.isVariableDefined(selectors)) {
			document.querySelectorAll(selectors).forEach((element) => {
				element.classList.remove(className); // fixed invalid method
			});
		}
	},

	toggleClass: function (selectors, className) {
		if (!(selectors instanceof HTMLElement) && selectors !== null) {
			selectors = document.querySelector(selectors);
		}
		if (e.isVariableDefined(selectors) && className) {
			selectors.classList.toggle(className);
		}
	},

	toggleAllClass: function (selectors, className) {
		if (e.isVariableDefined(selectors)) {
			document.querySelectorAll(selectors).forEach((element) => {
				element.classList.toggle(className); // fixed invalid method
			});
		}
	},

	addClass: function (selectors, className) {
		if (!(selectors instanceof HTMLElement) && selectors !== null) {
			selectors = document.querySelector(selectors);
		}
		if (e.isVariableDefined(selectors) && className) {
			selectors.classList.add(className);
		}
	},

	select: function (selectors) {
		return document.querySelector(selectors);
	},

	selectAll: function (selectors) {
		return document.querySelectorAll(selectors);
	},


	// START: 01 Preloader
	preLoader: function () {
		window.onload = () => {
			const preloader = e.select('.preloader');
			if (e.isVariableDefined(preloader)) {
				preloader.className += ' animate__animated animate__fadeOut';
				setTimeout(() => {
					preloader.style.display = 'none';
				}, 200);
			}
		};
	},
	// END: Preloader

	// START: 02 Navbar dropdown hover
	navbarDropdownHover: function () {
		e.onAll('.dropdown-menu a.dropdown-item.dropdown-toggle', 'click', function (event) {
			const element = this;
			event.preventDefault();
			event.stopImmediatePropagation();

			if (
				e.isVariableDefined(element.nextElementSibling) &&
				!element.nextElementSibling.classList.contains("show")
			) {
				const parents = e.getParents(element, '.dropdown-menu');
				const openMenu = parents.querySelector('.show');
				if (e.isVariableDefined(openMenu)) {
					openMenu.classList.remove("show");
				}
				const openDropdown = parents.querySelector('.dropdown-opened');
				if (e.isVariableDefined(openDropdown)) {
					openDropdown.classList.remove("dropdown-opened");
				}
			}

			const subMenu = e.getNextSiblings(element, ".dropdown-menu");
			if (subMenu && subMenu.classList) {
				subMenu.classList.toggle("show");
			}

			if (element && element.classList) {
				element.classList.toggle("dropdown-opened");
			}

			const parents = e.getParents(element, 'li.nav-item.dropdown.show');
			if (e.isVariableDefined(parents) && parents.length > 0) {
				e.on(parents, 'hidden.bs.dropdown', function () {
					e.removeAllClass('.dropdown-submenu .show', 'show');
				});
			}
		});
	},

	// END: Navbar dropdown hover

	// START: 03 Tiny Slider
	tinySlider: function () {
		const $carousel = e.select('.tiny-slider-inner');
		if (e.isVariableDefined($carousel)) {
			const tnsCarousel = e.selectAll('.tiny-slider-inner');
			tnsCarousel.forEach(slider => {
				const slider1 = slider;
				const sliderMode = slider1.getAttribute('data-mode') || 'carousel';
				const sliderAxis = slider1.getAttribute('data-axis') || 'horizontal';
				const sliderSpace = slider1.getAttribute('data-gutter') || 30;
				const sliderEdge = slider1.getAttribute('data-edge') || 0;

				const sliderItems = slider1.getAttribute('data-items') || 4;
				const sliderItemsXl = slider1.getAttribute('data-items-xl') || Number(sliderItems);
				const sliderItemsLg = slider1.getAttribute('data-items-lg') || Number(sliderItemsXl);
				const sliderItemsMd = slider1.getAttribute('data-items-md') || Number(sliderItemsLg);
				const sliderItemsSm = slider1.getAttribute('data-items-sm') || Number(sliderItemsMd);
				const sliderItemsXs = slider1.getAttribute('data-items-xs') || Number(sliderItemsSm);

				const sliderSpeed = slider1.getAttribute('data-speed') || 500;
				const sliderautoWidth = slider1.getAttribute('data-autowidth') === 'true';
				const sliderArrow = slider1.getAttribute('data-arrow') !== 'false';
				const sliderDots = slider1.getAttribute('data-dots') !== 'false';
				const sliderAutoPlay = slider1.getAttribute('data-autoplay') !== 'false';
				const sliderAutoPlayTime = slider1.getAttribute('data-autoplaytime') || 4000;
				const sliderHoverPause = slider1.getAttribute('data-hoverpause') === 'true';

				let sliderNavContainer = null;
				if (e.isVariableDefined(e.select('.custom-thumb'))) {
					sliderNavContainer = e.select('.custom-thumb');
				}

				const sliderLoop = slider1.getAttribute('data-loop') !== 'false';
				const sliderRewind = slider1.getAttribute('data-rewind') === 'true';
				const sliderAutoHeight = slider1.getAttribute('data-autoheight') === 'true';
				const sliderfixedWidth = slider1.getAttribute('data-fixedwidth') === 'true';
				const sliderTouch = slider1.getAttribute('data-touch') !== 'false';
				const sliderDrag = slider1.getAttribute('data-drag') !== 'false';

				// RTL direction
				const ifRtl = document.documentElement.getAttribute("dir");
				const sliderDirection = ifRtl === 'rtl' ? 'rtl' : 'ltr';

				const tnsSlider = tns({
					container: slider,
					mode: sliderMode,
					axis: sliderAxis,
					gutter: sliderSpace,
					edgePadding: sliderEdge,
					speed: sliderSpeed,
					autoWidth: sliderautoWidth,
					controls: sliderArrow,
					nav: sliderDots,
					autoplay: sliderAutoPlay,
					autoplayTimeout: sliderAutoPlayTime,
					autoplayHoverPause: sliderHoverPause,
					autoplayButton: false,
					autoplayButtonOutput: false,
					controlsPosition: 'top',
					navContainer: sliderNavContainer,
					navPosition: 'top',
					autoplayPosition: 'top',
					controlsText: [
						'<i class="fa-solid fa-chevron-left"></i>',
						'<i class="fa-solid fa-chevron-right"></i>'
					],
					loop: sliderLoop,
					rewind: sliderRewind,
					autoHeight: sliderAutoHeight,
					fixedWidth: sliderfixedWidth,
					touch: sliderTouch,
					mouseDrag: sliderDrag,
					arrowKeys: true,
					items: sliderItems,
					textDirection: sliderDirection,
					lazyload: true,
					lazyloadSelector: '.lazy',
					responsive: {
						0: {
							items: Number(sliderItemsXs)
						},
						576: {
							items: Number(sliderItemsSm)
						},
						768: {
							items: Number(sliderItemsMd)
						},
						992: {
							items: Number(sliderItemsLg)
						},
						1200: {
							items: Number(sliderItemsXl)
						}
					}
				});
			});
		}
	},

	// END: Tiny Slider


	// START: 04 Tooltip
	// Enable tooltips everywhere via data-toggle attribute
	toolTipFunc: function () {
		const tooltipTriggerList = Array.from(e.selectAll('[data-bs-toggle="tooltip"]'));
		const tooltipList = tooltipTriggerList.map((tooltipTriggerEl) => {
			return new bootstrap.Tooltip(tooltipTriggerEl);
		});
	},
	// END: Tooltip

	// START: 05 Popover
	// Enable popover everywhere via data-toggle attribute
	popOverFunc: function () {
		const popoverTriggerList = Array.from(e.selectAll('[data-bs-toggle="popover"]'));
		const popoverList = popoverTriggerList.map((popoverTriggerEl) => {
			return new bootstrap.Popover(popoverTriggerEl);
		});
	},
	// END: Popover


	// START: 06 Video player
	videoPlyr: function () {
		const vdp = e.select('.player-wrapper');
		if (e.isVariableDefined(vdp)) {
			// YouTube
			const playerYoutube = Plyr.setup('.player-youtube', {});
			window.player = playerYoutube;

			// Vimeo
			const playerVimeo = Plyr.setup('.player-vimeo', {});
			window.player = playerVimeo;

			// HTML video
			const playerHtmlvideo = Plyr.setup('.player-html', {
				captions: { active: true }
			});
			window.player = playerHtmlvideo;

			// HTML audio
			const playerHtmlaudio = Plyr.setup('.player-audio', {});
			window.player = playerHtmlaudio;
		}
	},
	// END: Video player

	// START: 07 GLightbox
	lightBox: function () {
		const light = e.select('[data-glightbox]');
		if (e.isVariableDefined(light)) {
			const lb = GLightbox({
				selector: '*[data-glightbox]',
				openEffect: 'fade',
				touchFollowAxis: 'true',
				closeEffect: 'fade'
			});
		}
	},
	// END: GLightbox

	// START: 08 Sidebar Toggle start
	sidebarToggleStart: function () {
		const sidebar = e.select('.sidebar-start-toggle');
		if (e.isVariableDefined(sidebar)) {
			const sb = sidebar;
			const mode = document.body;
			sb.addEventListener("click", () => {
				mode.classList.toggle("sidebar-start-enabled");
			});
		}
	},
	// END: Sidebar Toggle start

	// START: 09 Sidebar Toggle end
	sidebarToggleEnd: function () {
		const sidebar = e.select('.sidebar-end-toggle');
		if (e.isVariableDefined(sidebar)) {
			const sb = sidebar;
			const mode = document.body;
			sb.addEventListener("click", () => {
				mode.classList.toggle("sidebar-end-enabled");
			});
		}
	},
	// END: Sidebar Toggle end


	// START: 10 Choices
	choicesSelect: function () {
		const choice = e.select('.js-choice');
		if (e.isVariableDefined(choice)) {
			const elements = e.selectAll('.js-choice');
			elements.forEach(function (item) {
				const removeItemBtn = item.getAttribute('data-remove-item-button') === 'true';
				const placeHolder = item.getAttribute('data-placeholder') !== 'false';
				const placeHolderVal = item.getAttribute('data-placeholder-val') || '';
				const maxItemCount = item.getAttribute('data-max-item-count') || 3;
				const searchEnabled = item.getAttribute('data-search-enabled') === 'true';
				const position = item.getAttribute('data-position') || 'auto';
	
				new Choices(item, {
					removeItemButton: removeItemBtn,
					placeholder: placeHolder,
					placeholderValue: placeHolderVal,
					maxItemCount: maxItemCount,
					searchEnabled: searchEnabled,
					position: position
				});
			});
		}
	},
	
	// END: Choices

	// START: 11 Auto resize textarea
	autoResize: function () {
		e.selectAll('[data-autoresize]').forEach((element) => {
			const offset = element.offsetHeight - element.clientHeight;
			element.addEventListener('input', function (event) {
				event.target.style.height = 'auto';
				event.target.style.height = event.target.scrollHeight + offset + 'px';
			});
		});
	},
	// END: Auto resize textarea

	// START: 12 Drop Zone
	DropZone: function () {
		if (e.isVariableDefined(e.select("[data-dropzone]"))) {
			window.Dropzone.autoDiscover = false;

			// Default Dropzone
			if (e.isVariableDefined(e.select(".dropzone-default"))) {
				e.selectAll(".dropzone-default").forEach((el) => {
					const configData = el.dataset.dropzone ? JSON.parse(el.dataset.dropzone) : {};
					const baseConfig = {
						url: '/upload',
						init: function () {
							this.on('error', function (file, errorMessage) {
								if (file.accepted) {
									let mypreview = document.getElementsByClassName('dz-error');
									mypreview = mypreview[mypreview.length - 1];
									mypreview.classList.toggle('dz-error');
									mypreview.classList.toggle('dz-success');
								}
							});
						}
					};
					const finalConfig = { ...baseConfig, ...configData };
					new Dropzone(el, finalConfig);
				});
			}

			// Custom Dropzone
			if (e.isVariableDefined(e.select(".dropzone-custom"))) {
				e.selectAll(".dropzone-custom").forEach((el) => {
					const configData = el.dataset.dropzone ? JSON.parse(el.dataset.dropzone) : {};
					const baseConfig = {
						addRemoveLinks: true,
						previewsContainer: el.querySelector(".dz-preview"),
						previewTemplate: el.querySelector(".dz-preview").innerHTML,
						url: '/upload',
						init: function () {
							this.on('error', function (file, errorMessage) {
								if (file.accepted) {
									let mypreview = document.getElementsByClassName('dz-error');
									mypreview = mypreview[mypreview.length - 1];
									mypreview.classList.toggle('dz-error');
									mypreview.classList.toggle('dz-success');
								}
							});
						}
					};
					const finalConfig = { ...baseConfig, ...configData };
					el.querySelector(".dz-preview").innerHTML = '';
					new Dropzone(el, finalConfig);
				});
			}
		}
	},
	// END: Drop Zone

	// START: 13 Flat picker
	flatPicker: function () {
		const picker = e.select('.flatpickr');
		if (e.isVariableDefined(picker)) {
			const elements = e.selectAll('.flatpickr');
			elements.forEach((item) => {
				const modeAttr = item.getAttribute('data-mode');
				const mode = modeAttr === 'multiple' ? 'multiple' : modeAttr === 'range' ? 'range' : 'single';
				const enableTime = item.getAttribute('data-enableTime') === 'true';
				const noCalendar = item.getAttribute('data-noCalendar') === 'true';
				const inline = item.getAttribute('data-inline') === 'true';
				const dateFormat = item.getAttribute('data-date-format') ||
					(enableTime ? "h:i K" : "d M");

				flatpickr(item, {
					mode,
					enableTime,
					noCalendar,
					inline,
					animate: "false",
					position: "top",
					dateFormat: "D-m-Y",
					disableMobile: "true"
				});
			});
		}
	},
	// END: Flat picker

	// START: 14 Avatar Image
	avatarImg: function () {
		if (e.isVariableDefined(e.select('#avatarUpload'))) {
			const avtInput = e.select('#avatarUpload');
			const avtReset = e.select("#avatar-reset-img");
			const avtPreview = e.select('#avatar-preview');

			function readURL() {
				const file = avtInput.files[0];
				const reader = new FileReader();
				reader.onloadend = function () {
					avtPreview.src = reader.result;
				};

				if (file) {
					reader.readAsDataURL(file);
				}
				avtInput.value = '';
			}

			// Events
			avtInput.addEventListener('change', readURL, true);

			avtReset.addEventListener("click", function () {
				avtPreview.src = "/assets/images/avatar/placeholder.jpg";
			});
		}
	},
	// END: Avatar Image

	// START: 15 Custom Scrollbar
	customScrollbar: function () {
		if (e.isVariableDefined(e.select(".custom-scrollbar"))) {
			document.addEventListener("DOMContentLoaded", function () {
				OverlayScrollbars(e.selectAll('.custom-scrollbar'), {
					resize: "none",
					scrollbars: {
						autoHide: 'leave',
						autoHideDelay: 200
					},
					overflowBehavior: {
						x: "visible-hidden",
						y: "scroll"
					}
				});
			});
		}

		if (e.isVariableDefined(e.select(".custom-scrollbar-y"))) {
			document.addEventListener("DOMContentLoaded", function () {
				OverlayScrollbars(e.selectAll('.custom-scrollbar-y'), {
					resize: "none",
					scrollbars: {
						autoHide: 'leave',
						autoHideDelay: 200
					},
					overflowBehavior: {
						x: "scroll",
						y: "scroll"
					}
				});
			});
		}
	},
	// END: Custom Scrollbar


	// START: 16 Toasts
	toasts: function () {
		if (e.isVariableDefined(e.select('.toast-btn'))) {
			window.addEventListener('DOMContentLoaded', () => {
				e.selectAll(".toast-btn").forEach((t) => {
					t.addEventListener("click", () => {
						const toastTarget = document.getElementById(t.dataset.target);
						const toast = new bootstrap.Toast(toastTarget);
						toast.show();
					});
				});
			});
		}
	},
	// END: Toasts

	// START: 17 pswMeter
	pswMeter: function () {
		if (e.isVariableDefined(e.select("#pswmeter"))) {
			const myPassMeter = passwordStrengthMeter({
				containerElement: "#pswmeter",
				passwordInput: "#psw-input",
				showMessage: true,
				messageContainer: "#pswmeter-message",
				messagesList: [
					"Write your password...",
					"Easy peasy!",
					"That is a simple one",
					"That is better",
					"Yeah! that password rocks ;)",
				],
				height: 8,
				borderRadius: 4,
				pswMinLength: 8,
				colorScore1: "#dc3545",
				colorScore2: "#f7c32e",
				colorScore3: "#4f9ef8",
				colorScore4: "#0cbc87",
			});
		}
	},
	// END: pswMeter

	// START: 18 Fake Password
	fakePwd: function () {
		if (e.isVariableDefined(e.select(".fakepassword"))) {
			const password = e.select(".fakepassword");
			const toggler = e.select(".fakepasswordicon");

			const showHidePassword = () => {
				if (password.type === "password") {
					password.setAttribute("type", "text");
					toggler.classList.add("fa-eye");
				} else {
					toggler.classList.remove("fa-eye");
					password.setAttribute("type", "password");
				}
			};

			toggler.addEventListener("click", showHidePassword);
		}
	},
	// END: Fake Password


};
e.init();

document.addEventListener("DOMContentLoaded", function () {
	const sidebarLinks = document.querySelectorAll("#nav-active");

	// Get the current URL
	const currentURL = window.location.href;

	// Loop through each sidebar link
	sidebarLinks.forEach((link) => {
		// Remove active class from all links
		link.classList.remove("active");

		// Check if the current link's href matches the current URL
		if (currentURL.includes(link.getAttribute("href"))) {
			link.classList.add("active");
		}
	});
});
