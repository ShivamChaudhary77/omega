/**
 * Contact form async submission. Validates client-side for UX, POSTs JSON to
 * php/contact-submit.php (server re-validates — never trust the client),
 * and shows a success/error state without a page reload. Fires the GA4
 * form_submit key event only on a confirmed successful save, not on click.
 */
(function () {
    "use strict";

    const ENDPOINT = "/php/contact-submit.php";

    const FIELDS = [
        { id: "full_name", label: "Full name", validate: (v) => v.trim().length > 0, message: "Please enter your full name." },
        { id: "phone_number", label: "Phone number", validate: (v) => /^[0-9+\-\s()]{7,20}$/.test(v.trim()), message: "Please enter a valid phone number." },
        { id: "city", label: "City", validate: (v) => v.trim().length > 0, message: "Please enter your city." },
        { id: "project_details", label: "Project details", validate: (v) => v.trim().length > 0, message: "Please tell us a little about your project." },
    ];

    function setFieldError(input, message) {
        input.classList.toggle("is-invalid", !!message);
        const feedback = input.closest(".form-floating, div")?.querySelector(".invalid-feedback");
        if (feedback) feedback.textContent = message || "";
    }

    function validate(form) {
        let firstInvalid = null;
        let ok = true;
        FIELDS.forEach(function (f) {
            const input = form.querySelector("#" + f.id);
            const valid = f.validate(input.value);
            setFieldError(input, valid ? "" : f.message);
            if (!valid) {
                ok = false;
                if (!firstInvalid) firstInvalid = input;
            }
        });
        if (firstInvalid) firstInvalid.focus();
        return ok;
    }

    function showStatus(el, type, message) {
        el.className = "mb-4 alert alert-" + (type === "success" ? "success" : "danger");
        el.textContent = message;
        el.classList.remove("d-none");
    }

    document.addEventListener("DOMContentLoaded", function () {
        const form = document.getElementById("contact-form");
        if (!form) return;
        const statusEl = document.getElementById("contact-form-status");
        const submitBtn = document.getElementById("contact-form-submit");

        FIELDS.forEach(function (f) {
            const input = form.querySelector("#" + f.id);
            input.addEventListener("input", function () { setFieldError(input, ""); });
        });

        form.addEventListener("submit", function (e) {
            e.preventDefault();
            statusEl.classList.add("d-none");

            if (!validate(form)) return;

            const payload = {
                full_name: form.full_name.value.trim(),
                phone_number: form.phone_number.value.trim(),
                city: form.city.value.trim(),
                project_details: form.project_details.value.trim(),
                website: form.website.value, // honeypot
                page_url: window.location.href,
                referrer_url: document.referrer || "",
            };

            const originalBtnText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = "Sending…";

            fetch(ENDPOINT, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload),
            })
                .then(function (r) { return r.json().then(function (body) { return { ok: r.ok, body: body }; }); })
                .then(function (result) {
                    if (result.ok && result.body.success) {
                        form.reset();
                        showStatus(statusEl, "success", "Thank you — we've received your enquiry and will get back to you within 24 hours.");
                        if (typeof window.gtag === "function") {
                            window.gtag("event", "form_submit", { form_id: form.id, page_path: location.pathname });
                        }
                    } else if (result.body && result.body.errors) {
                        Object.keys(result.body.errors).forEach(function (fieldId) {
                            const input = form.querySelector("#" + fieldId);
                            if (input) setFieldError(input, result.body.errors[fieldId]);
                        });
                        showStatus(statusEl, "error", "Please check the highlighted fields and try again.");
                    } else {
                        showStatus(statusEl, "error", (result.body && result.body.error) || "Something went wrong. Please call or WhatsApp us instead.");
                    }
                })
                .catch(function () {
                    showStatus(statusEl, "error", "We couldn't reach the server. Please call or WhatsApp us instead.");
                })
                .finally(function () {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalBtnText;
                });
        });
    });
})();
