/**
 * free-design-consultation/ landing page: 3-step qualification form +
 * page-specific tracking. Mirrors js/contact-form.js's pattern (client-side
 * validation for UX only, POST JSON, server re-validates, fire the GA4 event
 * only after a confirmed save) and js/analytics.js's gtag-safe wrapper.
 *
 * Event names reuse the site's real GA4 key events where one already exists
 * (call_click / whatsapp_click / book_appointment / form_submit are bound by
 * js/analytics.js or fired the same way contact-form.js fires them) and adds
 * ones specific to a multi-step paid-ads form that don't already exist
 * elsewhere on the site: cta_click, form_start, form_step_complete,
 * lead_qualified, scroll_depth.
 */
(function () {
    "use strict";

    const ENDPOINT = "/php/consultation-leads-submit.php";

    function gtag() {
        (window.gtag || function () { (window.dataLayer = window.dataLayer || []).push(arguments); }).apply(null, arguments);
    }

    // ---- Generic data-cta click tracking (any element not already covered
    // by js/analytics.js's tel:/wa.me/book-appointment delegated listeners) ----
    document.addEventListener("click", function (e) {
        const el = e.target.closest("[data-cta]");
        if (!el) return;
        gtag("event", "cta_click", { cta_id: el.getAttribute("data-cta"), page_path: location.pathname });
    });

    // ---- Scroll depth (25/50/75/100%), fired once per threshold per page view ----
    (function scrollDepthTracking() {
        const thresholds = [25, 50, 75, 100];
        const fired = {};
        function check() {
            const doc = document.documentElement;
            const scrolled = window.scrollY + window.innerHeight;
            const pct = Math.min(100, Math.round((scrolled / doc.scrollHeight) * 100));
            thresholds.forEach(function (t) {
                if (pct >= t && !fired[t]) {
                    fired[t] = true;
                    gtag("event", "scroll_depth", { percent: t, page_path: location.pathname });
                }
            });
        }
        window.addEventListener("scroll", check, { passive: true });
        check();
    })();

    // ---- 3-step form ----
    document.addEventListener("DOMContentLoaded", function () {
        const form = document.getElementById("lp-consult-form");
        if (!form) return;

        document.getElementById("lp-page-url").value = window.location.href;
        document.getElementById("lp-referrer-url").value = document.referrer || "";

        const statusEl = document.getElementById("lp-form-status");
        const submitBtn = document.getElementById("lp-form-submit");
        let started = false;
        let currentStep = 1;

        function markFormStarted() {
            if (started) return;
            started = true;
            gtag("event", "form_start", { form_id: "consultation", page_path: location.pathname });
        }
        form.addEventListener("change", markFormStarted, { once: true });
        form.addEventListener("input", markFormStarted, { once: true });

        function goToStep(step) {
            form.querySelectorAll(".lp-form-step").forEach(function (el) {
                el.classList.toggle("is-active", Number(el.getAttribute("data-step")) === step);
            });
            for (let i = 1; i <= 3; i++) {
                const dot = document.getElementById("lp-progress-" + i);
                if (dot) dot.classList.toggle("is-done", i <= step);
            }
            currentStep = step;
        }

        function stepIsValid(step) {
            const stepEl = form.querySelector('.lp-form-step[data-step="' + step + '"]');
            // Collect every input sharing a name that has at least one
            // [required] member — not just the [required]-marked input
            // itself. A radio group only marks one option `required` (HTML
            // only needs one per group), so checking that single input's
            // `.checked` would wrongly fail whenever the user picks any
            // *other* option in the same group.
            const requiredNames = new Set();
            stepEl.querySelectorAll("input[required]").forEach(function (input) {
                requiredNames.add(input.name);
            });
            let ok = true;
            requiredNames.forEach(function (name) {
                const inputs = Array.from(stepEl.querySelectorAll('input[name="' + name + '"]'));
                const isRadioGroup = inputs[0].type === "radio";
                if (isRadioGroup) {
                    if (!inputs.some(function (i) { return i.checked; })) ok = false;
                } else {
                    inputs.forEach(function (input) {
                        const valid = input.checkValidity();
                        input.classList.toggle("is-invalid", !valid);
                        const feedback = input.parentElement.querySelector(".invalid-feedback");
                        if (feedback) feedback.textContent = valid ? "" : (input.validationMessage || "This field is required.");
                        if (!valid) ok = false;
                    });
                }
            });
            const phoneInput = stepEl.querySelector("#lp-phone-number");
            if (phoneInput && phoneInput.value && !/^[0-9+\-\s()]{7,20}$/.test(phoneInput.value.trim())) {
                phoneInput.classList.add("is-invalid");
                const feedback = phoneInput.parentElement.querySelector(".invalid-feedback");
                if (feedback) feedback.textContent = "Please enter a valid phone number.";
                ok = false;
            }
            return ok;
        }

        form.querySelectorAll("[data-lp-next]").forEach(function (btn) {
            btn.addEventListener("click", function () {
                if (!stepIsValid(currentStep)) return;
                const next = Number(btn.getAttribute("data-lp-next"));
                gtag("event", "form_step_complete", { step: currentStep, form_id: "consultation", page_path: location.pathname });
                goToStep(next);
            });
        });

        form.querySelectorAll("[data-lp-back]").forEach(function (btn) {
            btn.addEventListener("click", function () {
                goToStep(Number(btn.getAttribute("data-lp-back")));
            });
        });

        function showStatus(type, message) {
            statusEl.className = "mb-4 alert alert-" + (type === "success" ? "success" : "danger");
            statusEl.textContent = message;
            statusEl.classList.remove("d-none");
        }

        // Qualification rule grounded in the site's real published starting
        // price (data/organization.json startingPrice: "₹15 Lakhs+ for a full
        // home turnkey") — not an invented threshold. A lead is "qualified"
        // if their stated budget is at/above that starting price AND they
        // aren't just browsing with no real timeline.
        function computeIsQualified(payload) {
            const belowStartingPrice = payload.budget_range === "Under ₹15 Lakhs";
            const justExploring = payload.timeline === "Just Exploring";
            return !belowStartingPrice && !justExploring;
        }

        form.addEventListener("submit", function (e) {
            e.preventDefault();
            statusEl.classList.add("d-none");
            if (!stepIsValid(3)) return;

            const payload = {
                project_type: form.project_type.value,
                configuration: form.configuration.value,
                budget_range: form.budget_range.value,
                timeline: form.timeline.value,
                full_name: form.full_name.value.trim(),
                phone_number: form.phone_number.value.trim(),
                city: form.city.value.trim(),
                website: form.website.value, // honeypot
                page_url: form.page_url.value,
                referrer_url: form.referrer_url.value,
            };
            payload.is_qualified = computeIsQualified(payload);

            const originalText = submitBtn.textContent;
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
                        showStatus("success", "Thank you — a member of our design team will call you back within 24 hours.");
                        gtag("event", "form_submit", { form_id: "consultation", page_path: location.pathname });
                        if (payload.is_qualified) {
                            gtag("event", "lead_qualified", { form_id: "consultation", budget_range: payload.budget_range, timeline: payload.timeline, page_path: location.pathname });
                        }
                    } else if (result.body && result.body.errors) {
                        showStatus("error", "Please check the highlighted fields and try again.");
                    } else {
                        showStatus("error", (result.body && result.body.error) || "Something went wrong. Please call or WhatsApp us instead.");
                    }
                })
                .catch(function () {
                    showStatus("error", "We couldn't reach the server. Please call or WhatsApp us instead.");
                })
                .finally(function () {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                });
        });
    });
})();
