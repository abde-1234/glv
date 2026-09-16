import './bootstrap';

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.getAttribute('aria-controls'));

        if (!input) {
            return;
        }

        const isVisible = input.type === 'text';
        input.type = isVisible ? 'password' : 'text';
        button.classList.toggle('is-visible', !isVisible);
        button.setAttribute('aria-label', isVisible ? 'Afficher le mot de passe' : 'Masquer le mot de passe');
    });
});

const adminSidebar = document.querySelector('[data-admin-sidebar]');
const sidebarBackdrop = document.querySelector('[data-admin-sidebar-close]');
const sidebarToggle = document.querySelector('[data-admin-sidebar-toggle]');

const setSidebarOpen = (open) => {
    if (!adminSidebar || !sidebarBackdrop || !sidebarToggle) {
        return;
    }

    adminSidebar.classList.toggle('is-open', open);
    sidebarBackdrop.classList.toggle('is-visible', open);
    sidebarToggle.setAttribute('aria-expanded', String(open));
    document.body.classList.toggle('sidebar-open', open);
};

sidebarToggle?.addEventListener('click', () => {
    setSidebarOpen(!adminSidebar.classList.contains('is-open'));
});

sidebarBackdrop?.addEventListener('click', () => setSidebarOpen(false));

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        setSidebarOpen(false);
    }
});

document.querySelectorAll('[data-flash-close]').forEach((button) => {
    button.addEventListener('click', () => button.closest('[data-flash-message]')?.remove());
});

document.querySelectorAll('[data-confirm-delete]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirmDelete)) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('[data-photo-input]').forEach((input) => {
    input.addEventListener('change', () => {
        const file = input.files?.[0];
        const dropzone = input.closest('.vehicle-photo-dropzone');
        const preview = dropzone?.querySelector('[data-photo-preview]');
        const placeholder = dropzone?.querySelector('[data-photo-placeholder]');

        if (!file || !preview) {
            return;
        }

        const previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;
        preview.hidden = false;

        if (placeholder) {
            placeholder.hidden = true;
        }

        preview.addEventListener('load', () => URL.revokeObjectURL(previewUrl), { once: true });
    });
});

document.querySelectorAll('[data-reservation-calculator]').forEach((calculator) => {
    const client = calculator.querySelector('[data-reservation-client]');
    const vehicle = calculator.querySelector('[data-reservation-vehicle]');
    const start = calculator.querySelector('[data-reservation-start]');
    const end = calculator.querySelector('[data-reservation-end]');
    const price = calculator.querySelector('[data-reservation-price]');
    const duration = calculator.querySelector('[data-reservation-duration]');
    const amount = calculator.querySelector('[data-reservation-amount]');

    const formatDate = (value) => {
        if (!value) return null;
        const [year, month, day] = value.split('-');
        return `${day}/${month}/${year}`;
    };

    const updateReservationSummary = () => {
        const selectedClient = client?.selectedOptions?.[0];
        const selectedVehicle = vehicle?.selectedOptions?.[0];
        const dailyPrice = Number(selectedVehicle?.dataset.price || 0);
        let numberOfDays = 0;

        if (start?.value && end?.value) {
            const startDate = new Date(`${start.value}T00:00:00Z`);
            const endDate = new Date(`${end.value}T00:00:00Z`);
            const difference = Math.round((endDate - startDate) / 86400000);
            numberOfDays = difference >= 0 ? Math.max(1, difference) : 0;
        }

        const total = dailyPrice * numberOfDays;
        if (price) price.value = dailyPrice.toFixed(2);
        if (duration) duration.value = numberOfDays;
        if (amount) amount.value = total.toFixed(2);

        const clientSummary = calculator.querySelector('[data-summary-client]');
        const vehicleSummary = calculator.querySelector('[data-summary-vehicle]');
        const periodSummary = calculator.querySelector('[data-summary-period]');
        const durationSummary = calculator.querySelector('[data-summary-duration]');
        const amountSummary = calculator.querySelector('[data-summary-amount]');

        if (clientSummary) clientSummary.textContent = selectedClient?.dataset.label || '—';
        if (vehicleSummary) vehicleSummary.textContent = selectedVehicle?.dataset.label || '—';
        if (periodSummary) periodSummary.textContent = start?.value && end?.value ? `${formatDate(start.value)} – ${formatDate(end.value)}` : '—';
        if (durationSummary) durationSummary.textContent = String(numberOfDays);
        if (amountSummary) amountSummary.textContent = total.toFixed(2);
    };

    [client, vehicle, start, end].forEach((field) => field?.addEventListener('change', updateReservationSummary));
    updateReservationSummary();
});

document.querySelectorAll('[data-contract-form]').forEach((form) => {
    const reservation = form.querySelector('[data-contract-reservation]');

    const updateContractSummary = () => {
        const selected = reservation?.selectedOptions?.[0];
        const values = {
            reference: selected?.dataset.reference || '—',
            client: selected?.dataset.client || '—',
            vehicle: selected?.dataset.vehicle || '—',
            period: selected?.dataset.period || '—',
            amount: selected?.dataset.amount || '0.00',
        };

        Object.entries(values).forEach(([key, value]) => {
            const target = form.querySelector(`[data-contract-${key}]`);
            if (target) target.textContent = value;
        });
    };

    reservation?.addEventListener('change', updateContractSummary);
    updateContractSummary();
});

document.querySelectorAll('[data-agency-logo-input]').forEach((input) => {
    input.addEventListener('change', () => {
        const file = input.files?.[0];
        const preview = document.querySelector('[data-agency-logo-preview]');
        const placeholder = document.querySelector('[data-agency-logo-placeholder]');

        if (!file || !preview) return;

        const url = URL.createObjectURL(file);
        preview.src = url;
        preview.hidden = false;
        if (placeholder) placeholder.hidden = true;
        preview.addEventListener('load', () => URL.revokeObjectURL(url), { once: true });
    });
});

document.querySelectorAll('[data-document-input]').forEach((input) => {
    input.addEventListener('change', () => {
        const label = input.closest('.agency-file-button')?.querySelector('[data-document-file-name]');
        if (label) label.textContent = input.files?.[0]?.name || 'Choisir un fichier';
    });
});

const closeAgencyModal = (modal) => {
    if (typeof modal?.close === 'function') modal.close();
    else modal?.removeAttribute('open');
};

document.querySelectorAll('[data-modal-open]').forEach((button) => {
    button.addEventListener('click', () => {
        const modal = document.getElementById(button.dataset.modalOpen);
        if (typeof modal?.showModal === 'function') modal.showModal();
        else modal?.setAttribute('open', '');
    });
});

document.querySelectorAll('.agency-modal').forEach((modal) => {
    modal.querySelectorAll('[data-modal-close]').forEach((button) => button.addEventListener('click', () => closeAgencyModal(modal)));
    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeAgencyModal(modal);
    });

    if (modal.hasAttribute('data-auto-open')) {
        if (typeof modal.showModal === 'function') modal.showModal();
        else modal.setAttribute('open', '');
    }
});
