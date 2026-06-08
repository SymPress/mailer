(() => {
    const syncSwitch = (input) => {
        const switchLabel = input.closest(".spm-switch");
        const state = switchLabel?.querySelector("strong");

        if (state) {
            state.textContent = input.checked ? "ON" : "OFF";
        }
    };

    const syncProvider = (input) => {
        const grid = input.closest(".spm-provider-grid");

        if (!grid) {
            return;
        }

        grid.querySelectorAll(".spm-provider-card").forEach((card) => {
            card.classList.remove("is-selected");
        });

        input.closest(".spm-provider-card")?.classList.add("is-selected");
    };

    const boot = () => {
        document.querySelectorAll(".sympress-mailer .spm-switch input").forEach(syncSwitch);
        document.querySelectorAll(".sympress-mailer .spm-provider-card input:checked").forEach(syncProvider);

        document.addEventListener("change", (event) => {
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
})();
