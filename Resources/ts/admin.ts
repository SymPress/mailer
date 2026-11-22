import "../css/admin.css";

type MailerInput = HTMLInputElement;

const syncSwitch = (input: MailerInput): void => {
    const switchLabel = input.closest<HTMLLabelElement>(".spm-switch");
    const state = switchLabel?.querySelector<HTMLElement>("strong");

    if (state) {
        state.textContent = (input.checked ? switchLabel?.dataset.on : switchLabel?.dataset.off) ?? state.textContent;
    }
};

const syncProvider = (input: MailerInput): void => {
    const grid = input.closest<HTMLElement>(".spm-provider-grid");

    if (!grid) {
        return;
    }

    grid.querySelectorAll<HTMLElement>(".spm-provider-card").forEach((card) => {
        card.classList.remove("is-selected");
    });

    input.closest<HTMLElement>(".spm-provider-card")?.classList.add("is-selected");
};

// Hidden rows stay in the form, so their values are still submitted unchanged.
const syncFields = (): void => {
    const container = document.querySelector<HTMLElement>(".sympress-mailer .spm-provider-fields");
    const showAll = container?.querySelector<MailerInput>(".spm-show-all input");
    const fields = document.querySelector<MailerInput>(".sympress-mailer .spm-provider-card input:checked")
        ?.closest<HTMLElement>(".spm-provider-card")?.dataset.fields;
    const relevant = fields === undefined || showAll?.checked ? null : fields.split(" ");

    container?.querySelectorAll<HTMLElement>("[data-field]").forEach((row) => {
        row.hidden = relevant !== null && !relevant.includes(row.dataset.field ?? "");
    });
};

const boot = (): void => {
    document.querySelectorAll<MailerInput>(".sympress-mailer .spm-switch input").forEach(syncSwitch);
    document.querySelectorAll<MailerInput>(".sympress-mailer .spm-provider-card input:checked").forEach(syncProvider);
    document.querySelectorAll<HTMLElement>(".sympress-mailer .spm-show-all").forEach((toggle) => {
        toggle.hidden = false;
    });
    syncFields();

    document.addEventListener("change", (event: Event): void => {
        if (!(event.target instanceof HTMLInputElement)) {
            return;
        }

        if (event.target.closest(".sympress-mailer .spm-switch")) {
            syncSwitch(event.target);
        }

        if (event.target.closest(".sympress-mailer .spm-provider-card")) {
            syncProvider(event.target);
        }

        if (event.target.closest(".sympress-mailer .spm-provider-card, .sympress-mailer .spm-show-all")) {
            syncFields();
        }
    });
};

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot, { once: true });
} else {
    boot();
}
