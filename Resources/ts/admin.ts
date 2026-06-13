import "../css/admin.css";

type MailerInput = HTMLInputElement;

const syncSwitch = (input: MailerInput): void => {
    const switchLabel = input.closest<HTMLLabelElement>(".spm-switch");
    const state = switchLabel?.querySelector<HTMLElement>("strong");

    if (state) {
        state.textContent = input.checked ? "ON" : "OFF";
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

const boot = (): void => {
    document.querySelectorAll<MailerInput>(".sympress-mailer .spm-switch input").forEach(syncSwitch);
    document.querySelectorAll<MailerInput>(".sympress-mailer .spm-provider-card input:checked").forEach(syncProvider);

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
    });
};

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot, { once: true });
} else {
    boot();
}
