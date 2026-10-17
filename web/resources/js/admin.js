// Paquet de l'administration (Livewire 3). Alpine est fourni par Livewire : ne pas le réimporter.
import 'bootstrap';
import Swal from 'sweetalert2';
import Chart from 'chart.js/auto';
import Quill from 'quill';
import Choices from 'choices.js';
import flatpickr from 'flatpickr';
import { French } from 'flatpickr/dist/l10n/fr.js';
import './shared/theme.js';
import './public/xomiton-ui.js';

window.Swal = Swal;
window.Chart = Chart;
window.Quill = Quill;
window.Choices = Choices;
flatpickr.localize(French);
window.flatpickr = flatpickr;

// Toasts SweetAlert2 : un seul écouteur global (DEVELOPMENT_STANDARDS.md §3, NOTIFICATIONS_INSTRUCTION.md §5).
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 4500,
    timerProgressBar: true,
    didOpen: (t) => {
        t.addEventListener('mouseenter', Swal.stopTimer);
        t.addEventListener('mouseleave', Swal.resumeTimer);
    },
});
window.addEventListener('toast', (e) => Toast.fire({ icon: e.detail.type ?? 'success', title: e.detail.message }));
