/* Omega dashboard behaviour: plain JavaScript, no framework. */
(function () {
    "use strict";

    var uid = 0;
    function nextId() { return "n" + Date.now().toString(36) + (uid++); }

    /* ---------- Rich text editor (TinyMCE) ---------- */
    function initEditors(root) {
        if (!window.tinymce) return;
        (root || document).querySelectorAll("textarea.richtext").forEach(function (ta) {
            if (ta.closest("template") || ta.dataset.editorReady) return;
            ta.id = "rt_" + nextId();
            ta.dataset.editorReady = "1";
            var small = ta.classList.contains("richtext-small");
            var tall = ta.classList.contains("richtext-tall");
            tinymce.init({
                target: ta,
                menubar: false,
                branding: false,
                promotion: false,
                plugins: "lists link table image code autoresize",
                toolbar: small ? "bold italic link | removeformat" :
                    "blocks | bold italic | bullist numlist | link image table | blockquote | removeformat | code",
                block_formats: "Paragraph=p; Heading 2=h2; Heading 3=h3; Heading 4=h4",
                min_height: small ? 120 : (tall ? 480 : 220),
                max_height: tall ? 900 : 600,
                convert_urls: false,
                relative_urls: false,
                link_default_target: "",
                image_caption: false,
                content_style: "body{font-family:Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;max-width:820px;margin:12px auto} img{max-width:100%;height:auto} table{border-collapse:collapse} td,th{border:1px solid #ccc;padding:4px 8px}",
                images_upload_handler: function (blobInfo) {
                    var data = new FormData();
                    data.append("file", blobInfo.blob(), blobInfo.filename());
                    data.append("action", "upload");
                    data.append("csrf", window.OMEGA_CSRF);
                    return fetch("/admin/media.php?format=json", { method: "POST", body: data, credentials: "same-origin" })
                        .then(function (r) { return r.json(); })
                        .then(function (res) { if (!res.ok) throw new Error(res.error || "Upload failed"); return res.url; });
                },
                setup: function (editor) {
                    editor.on("change input", function () { markDirty(); });
                }
            });
        });
    }

    function removeEditors(root) {
        if (!window.tinymce) return;
        root.querySelectorAll("textarea.richtext").forEach(function (ta) {
            var ed = tinymce.get(ta.id);
            if (ed) { ed.save(); ed.remove(); }
            delete ta.dataset.editorReady;
        });
    }

    /* ---------- Repeaters and page sections ---------- */
    function repeaterOf(el) { return el.closest("[data-repeater]"); }
    function itemsOf(rep) { return rep.querySelector(":scope > .repeater-items"); }

    document.addEventListener("click", function (e) {
        var add = e.target.closest("[data-repeater-add]");
        if (add) {
            var rep = repeaterOf(add);
            var tpl = rep.querySelector(":scope > template");
            insertTemplate(itemsOf(rep), tpl.innerHTML.split("__INDEX__").join(nextId()));
            return;
        }
        var addBlock = e.target.closest("[data-add-block]");
        if (addBlock) {
            var ed = addBlock.closest(".blocks-editor");
            var btpl = ed.querySelector(':scope > template[data-block-type="' + addBlock.dataset.addBlock + '"]');
            insertTemplate(itemsOf(ed), btpl.innerHTML.split("__BLOCK__").join(nextId()));
            return;
        }
        var remove = e.target.closest("[data-remove]");
        if (remove) {
            var item = remove.closest(".repeater-item");
            var isBlock = item.classList.contains("block-item");
            if (!confirm(isBlock ? "Remove this section from the page?" : "Remove this item?")) return;
            removeEditors(item);
            item.remove();
            markDirty();
            return;
        }
        var move = e.target.closest("[data-move]");
        if (move) {
            var it = move.closest(".repeater-item");
            var sibling = move.dataset.move === "up" ? it.previousElementSibling : it.nextElementSibling;
            if (!sibling) return;
            removeEditors(it);
            if (move.dataset.move === "up") it.parentNode.insertBefore(it, sibling);
            else it.parentNode.insertBefore(sibling, it);
            initEditors(it);
            it.scrollIntoView({ block: "nearest" });
            markDirty();
            return;
        }
        var modeBtn = e.target.closest("[data-mode-btn]");
        if (modeBtn) {
            setHtmlMode(modeBtn.closest("[data-html-block]"), modeBtn.dataset.modeBtn);
        }
    });

    function insertTemplate(container, html) {
        var wrap = document.createElement("div");
        wrap.innerHTML = html.trim();
        var node = wrap.firstElementChild;
        container.appendChild(node);
        node.querySelectorAll("[data-html-block]").forEach(function (b) { setHtmlMode(b, b.dataset.mode); });
        initEditors(node);
        initCounters(node);
        node.scrollIntoView({ behavior: "smooth", block: "center" });
        markDirty();
    }

    /* "Edit text" / "Edit HTML" for designed sections. In text mode the
       word fields are sent and applied to the (unchanged) HTML; in HTML mode
       the word fields are switched off and the edited HTML is sent instead. */
    function setHtmlMode(block, mode) {
        if (!block) return;
        if (mode === "text" && !block.querySelector(".html-text-fields input, .html-text-fields textarea")) mode = "html";
        block.dataset.mode = mode;
        block.querySelectorAll(".html-text-fields input, .html-text-fields textarea").forEach(function (f) { f.disabled = mode !== "text"; });
        var raw = block.querySelector(".html-raw-field textarea");
        if (raw) raw.readOnly = mode === "text";
        block.querySelectorAll("[data-mode-btn]").forEach(function (b) { b.classList.toggle("active", b.dataset.modeBtn === mode); });
    }
    document.querySelectorAll("[data-html-block]").forEach(function (b) { setHtmlMode(b, b.dataset.mode); });

    /* ---------- Character counters ---------- */
    function initCounters(root) {
        (root || document).querySelectorAll("[data-max]").forEach(function (input) {
            if (input.dataset.counterReady || input.closest("template")) return;
            input.dataset.counterReady = "1";
            var counter = document.createElement("div");
            counter.className = "char-count";
            input.insertAdjacentElement("afterend", counter);
            var max = parseInt(input.dataset.max, 10);
            function update() {
                var n = input.value.length;
                counter.textContent = n + " / " + max + " characters";
                counter.classList.toggle("over", n > max);
            }
            input.addEventListener("input", update);
            update();
        });
    }

    /* ---------- Image picker ---------- */
    var picker = document.getElementById("imagePicker");
    // Bootstrap 5.0 (the site's version) has no getOrCreateInstance().
    function pickerModal() { return bootstrap.Modal.getInstance(picker) || new bootstrap.Modal(picker); }
    var pickerTarget = null, pickerMulti = false, images = null, selected = [];

    function renderPicker(filter) {
        var grid = picker.querySelector("[data-picker-grid]");
        var q = (filter || "").toLowerCase();
        var shown = (images || []).filter(function (img) { return !q || img.url.toLowerCase().indexOf(q) !== -1; }).slice(0, 240);
        grid.innerHTML = "";
        shown.forEach(function (img) {
            var b = document.createElement("button");
            b.type = "button";
            b.className = "picker-item" + (selected.indexOf(img.url) !== -1 ? " selected" : "");
            b.innerHTML = '<img loading="lazy" alt=""><span></span>';
            b.querySelector("img").src = img.url;
            b.querySelector("span").textContent = (img.uploaded ? "⬆ " : "") + img.name;
            b.title = img.url;
            b.addEventListener("click", function () { choose(img.url, b); });
            grid.appendChild(b);
        });
        if (!shown.length) grid.innerHTML = '<p class="text-muted">No images match.</p>';
    }

    function loadImages() {
        if (images) return Promise.resolve();
        picker.querySelector("[data-picker-status]").textContent = "Loading…";
        return fetch("/admin/media.php?format=json", { credentials: "same-origin" })
            .then(function (r) { return r.json(); })
            .then(function (res) { images = res.images || []; picker.querySelector("[data-picker-status]").textContent = ""; });
    }

    function choose(url, button) {
        if (pickerMulti) {
            var i = selected.indexOf(url);
            if (i === -1) selected.push(url); else selected.splice(i, 1);
            button.classList.toggle("selected");
            return;
        }
        setImageValue(pickerTarget, url);
        pickerModal().hide();
    }

    function setImageValue(field, url) {
        var input = field.querySelector("input[type=text]");
        input.value = url;
        input.dispatchEvent(new Event("input", { bubbles: true }));
        var prev = field.querySelector(".image-preview");
        if (prev) { prev.src = url; prev.hidden = false; }
        markDirty();
    }

    if (picker) {
        document.addEventListener("click", function (e) {
            var btn = e.target.closest("[data-pick-image]");
            if (!btn) return;
            pickerMulti = btn.dataset.pickImage === "multi";
            pickerTarget = btn.closest(pickerMulti ? "[data-gallery]" : "[data-image-field]");
            selected = [];
            picker.querySelector("[data-picker-done]").hidden = !pickerMulti;
            loadImages().then(function () { renderPicker(picker.querySelector("[data-picker-search]").value); });
            pickerModal().show();
        });
        picker.querySelector("[data-picker-search]").addEventListener("input", function (e) { renderPicker(e.target.value); });
        picker.querySelector("[data-picker-done]").addEventListener("click", function () {
            var ta = pickerTarget.querySelector("textarea");
            var lines = ta.value.split(/\n/).map(function (s) { return s.trim(); }).filter(Boolean);
            ta.value = lines.concat(selected).join("\n");
            ta.dispatchEvent(new Event("input", { bubbles: true }));
            pickerModal().hide();
        });
        picker.querySelector("[data-picker-upload]").addEventListener("change", function (e) {
            var files = Array.prototype.slice.call(e.target.files);
            var status = picker.querySelector("[data-picker-status]");
            var chain = Promise.resolve();
            files.forEach(function (file, i) {
                chain = chain.then(function () {
                    status.textContent = "Uploading " + (i + 1) + " of " + files.length + "…";
                    var data = new FormData();
                    data.append("file", file);
                    data.append("action", "upload");
                    data.append("csrf", window.OMEGA_CSRF);
                    return fetch("/admin/media.php?format=json", { method: "POST", body: data, credentials: "same-origin" })
                        .then(function (r) { return r.json(); })
                        .then(function (res) {
                            if (!res.ok) throw new Error(res.error || "Upload failed");
                            images = images || [];
                            images.unshift({ url: res.url, name: res.url.split("/").pop(), uploaded: true });
                            if (pickerMulti) selected.push(res.url);
                            else if (files.length === 1) { choose(res.url, null); }
                        });
                });
            });
            chain.then(function () { status.textContent = "Uploaded."; renderPicker(""); })
                .catch(function (err) { status.textContent = err.message; });
            e.target.value = "";
        });
    }

    /* Gallery thumbnails follow the list of paths. */
    document.addEventListener("input", function (e) {
        var gallery = e.target.closest("[data-gallery]");
        if (gallery && e.target.tagName === "TEXTAREA") {
            var thumbs = gallery.querySelector(".gallery-thumbs");
            thumbs.innerHTML = "";
            e.target.value.split(/\n/).map(function (s) { return s.trim(); }).filter(Boolean).forEach(function (src) {
                var img = document.createElement("img");
                img.src = src; img.alt = ""; img.loading = "lazy";
                thumbs.appendChild(img);
            });
        }
        var imageField = e.target.closest("[data-image-field]");
        if (imageField && e.target.type === "text") {
            var prev = imageField.querySelector(".image-preview");
            prev.src = e.target.value; prev.hidden = !e.target.value;
        }
    });

    /* ---------- Confirmations, unsaved changes, slug hint ---------- */
    document.addEventListener("submit", function (e) {
        var form = e.target;
        if (form.dataset.confirm && !confirm(form.dataset.confirm)) {
            e.preventDefault();
            return;
        }
        if (form.id === "edit-form") dirty = false;
    });

    var dirty = false;
    function markDirty() {
        dirty = true;
        var flag = document.querySelector("[data-unsaved]");
        if (flag) flag.hidden = false;
    }
    var editForm = document.getElementById("edit-form");
    if (editForm) {
        editForm.addEventListener("input", markDirty);
        editForm.addEventListener("change", markDirty);
        window.addEventListener("beforeunload", function (e) {
            if (dirty) { e.preventDefault(); e.returnValue = ""; }
        });
        var slug = document.querySelector("[data-slug-source]");
        var titleInput = editForm.querySelector('input[name="f[title]"], input[name="f[name]"], input[name="f[h1]"]');
        if (slug && titleInput) {
            titleInput.addEventListener("input", function () {
                slug.placeholder = titleInput.value.toLowerCase().replace(/&/g, " and ").replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "") || "made from the title";
            });
        }
    }

    /* Live Google-style preview in the SEO panel. */
    var serp = document.querySelector("[data-serp]");
    if (serp && editForm) {
        var siteHost = serp.querySelector("[data-serp-url]").textContent.split("/")[0];
        var tIn = editForm.querySelector('[name="f[seo][title]"]');
        var dIn = editForm.querySelector('[name="f[seo][description]"]');
        var uIn = editForm.querySelector('[name="meta[urlPath]"]');
        var update = function () {
            if (tIn) serp.querySelector("[data-serp-title]").textContent = tIn.value.length > 62 ? tIn.value.slice(0, 60) + "…" : tIn.value;
            if (dIn) serp.querySelector("[data-serp-desc]").textContent = dIn.value.length > 162 ? dIn.value.slice(0, 158) + "…" : dIn.value;
            if (uIn && uIn.value) serp.querySelector("[data-serp-url]").textContent = siteHost + uIn.value.replace(/\/$/, "").replace(/\//g, " › ").replace(/^ › /, " › ");
        };
        [tIn, dIn, uIn].forEach(function (el) { if (el) el.addEventListener("input", update); });
    }

    /* Delete form: show the custom-address box only when chosen. */
    document.querySelectorAll("[data-redirect-choice]").forEach(function (sel) {
        var custom = sel.form.querySelector("[data-redirect-custom]");
        sel.addEventListener("change", function () { custom.hidden = sel.value !== "custom"; if (!custom.hidden) custom.focus(); });
    });

    /* ================= Layout and navigation (display only) ================= */

    /* Sidebar slides in on phones. */
    var sidebar = document.getElementById("admin-sidebar");
    function setSidebar(open) {
        document.body.classList.toggle("sidebar-open", open);
        var btn = document.querySelector("[data-sidebar-toggle]");
        if (btn) btn.setAttribute("aria-expanded", open ? "true" : "false");
    }
    document.addEventListener("click", function (e) {
        if (e.target.closest("[data-sidebar-toggle]")) setSidebar(!document.body.classList.contains("sidebar-open"));
        else if (e.target.closest("[data-sidebar-close]")) setSidebar(false);
    });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") setSidebar(false); });

    /* Success messages fade away after a few seconds. */
    document.querySelectorAll(".admin-flash.alert-success").forEach(function (el) {
        setTimeout(function () { el.classList.add("fade-out"); setTimeout(function () { el.remove(); }, 600); }, 7000);
    });

    /* Instant search on the pages list (the Search button still works too). */
    document.querySelectorAll("[data-live-search]").forEach(function (input) {
        var table = document.querySelector(input.dataset.liveSearch);
        if (!table) return;
        var rows = Array.prototype.slice.call(table.querySelectorAll("tbody tr[data-search]"));
        var empty = table.querySelector(".admin-empty-row");
        var counter = document.querySelector("[data-live-count]");
        input.addEventListener("input", function () {
            var words = input.value.toLowerCase().split(/\s+/).filter(Boolean);
            var shown = 0;
            rows.forEach(function (row) {
                var hay = row.dataset.search;
                var match = words.every(function (w) { return hay.indexOf(w) !== -1; });
                row.hidden = !match;
                if (match) shown++;
            });
            if (counter) counter.textContent = shown;
            if (empty) empty.hidden = shown > 0;
        });
    });

    /* Refresh rich-text editors that just became visible. */
    function refreshEditors(root) {
        if (!window.tinymce) return;
        root.querySelectorAll("textarea.richtext").forEach(function (ta) {
            var ed = tinymce.get(ta.id);
            if (ed) { try { ed.execCommand("mceAutoResize"); } catch (err) { /* ignore */ } }
        });
    }

    /* Editor: Content / SEO / Consultation box become tabs. */
    document.querySelectorAll(".admin-edit-main, [data-tabs]").forEach(function (editMain) {
        var cards = Array.prototype.slice.call(editMain.querySelectorAll(":scope > .admin-card[data-tab-title]"));
        if (cards.length > 1) {
            var bar = document.createElement("div");
            bar.className = "admin-tabs";
            bar.setAttribute("role", "tablist");
            var storeKey = "omega-tab:" + location.pathname + location.search;
            var showTab = function (i, remember) {
                cards.forEach(function (card, j) { card.classList.toggle("tab-hidden", i !== j); });
                Array.prototype.forEach.call(bar.children, function (b, j) {
                    b.classList.toggle("active", i === j);
                    b.setAttribute("aria-selected", i === j ? "true" : "false");
                });
                refreshEditors(cards[i]);
                if (remember) { try { sessionStorage.setItem(storeKey, String(i)); } catch (err) { /* ignore */ } }
            };
            cards.forEach(function (card, i) {
                var b = document.createElement("button");
                b.type = "button";
                b.className = "admin-tab";
                b.setAttribute("role", "tab");
                var errors = card.querySelectorAll(".is-invalid").length;
                b.innerHTML = '<i class="bi ' + (card.dataset.tabIcon || "bi-card-text") + '"></i> <span></span>' + (errors ? ' <span class="admin-tab-badge">' + errors + "</span>" : "");
                b.querySelector("span").textContent = card.dataset.tabTitle;
                b.addEventListener("click", function () { showTab(i, true); window.scrollTo({ top: editMain.getBoundingClientRect().top + window.scrollY - 80, behavior: "smooth" }); });
                bar.appendChild(b);
            });
            editMain.insertBefore(bar, cards[0]);
            var start = 0;
            var firstError = cards.findIndex(function (c) { return c.querySelector(".is-invalid"); });
            if (firstError >= 0) start = firstError;
            else { try { var saved = parseInt(sessionStorage.getItem(storeKey), 10); if (saved >= 0 && saved < cards.length) start = saved; } catch (err) { /* ignore */ } }
            showTab(start, false);
        }
    });

    /* Page sections: click a section's title bar to fold or unfold it. */
    document.querySelectorAll(".blocks-editor").forEach(function (ed) {
        var items = function () { return Array.prototype.slice.call(ed.querySelectorAll(":scope > .repeater-items > .block-item")); };
        if (items().length > 2) items().forEach(function (it) { it.classList.add("collapsed"); });
        var tools = document.createElement("div");
        tools.className = "admin-blocks-tools";
        tools.innerHTML = '<span><i class="bi bi-info-circle"></i> Click a section to open it. Use the arrows to reorder.</span>' +
            '<span><button type="button" class="btn btn-link btn-sm" data-blocks="open">Open all</button>' +
            '<button type="button" class="btn btn-link btn-sm" data-blocks="close">Close all</button></span>';
        ed.insertBefore(tools, ed.firstChild);
        tools.addEventListener("click", function (e) {
            var b = e.target.closest("[data-blocks]");
            if (!b) return;
            items().forEach(function (it) { it.classList.toggle("collapsed", b.dataset.blocks === "close"); if (b.dataset.blocks === "open") refreshEditors(it); });
        });
    });
    document.addEventListener("click", function (e) {
        var label = e.target.closest(".block-item > .item-tools .item-label");
        if (!label) return;
        var item = label.closest(".block-item");
        item.classList.toggle("collapsed");
        if (!item.classList.contains("collapsed")) refreshEditors(item);
    });

    /* Images page: search, folder and usage filters, copy address. */
    var mediaGrid = document.querySelector("[data-media-grid]");
    if (mediaGrid) {
        var mSearch = document.querySelector("[data-media-search]");
        var mGroup = document.querySelector("[data-media-group]");
        var mUsage = document.querySelector("[data-media-usage]");
        var mCount = document.querySelector("[data-media-count]");
        var mEmpty = document.querySelector("[data-media-empty]");
        var cards = Array.prototype.slice.call(mediaGrid.querySelectorAll(".media-item"));
        var filterMedia = function () {
            var words = (mSearch ? mSearch.value : "").toLowerCase().split(/\s+/).filter(Boolean);
            var group = mGroup ? mGroup.value : "";
            var usage = mUsage ? mUsage.value : "";
            var shown = 0;
            cards.forEach(function (card) {
                var ok = words.every(function (w) { return card.dataset.name.indexOf(w) !== -1; })
                    && (!group || card.dataset.group === group)
                    && (!usage || card.dataset.used === usage);
                card.hidden = !ok;
                if (ok) shown++;
            });
            if (mCount) mCount.textContent = shown;
            if (mEmpty) mEmpty.hidden = shown > 0 || !cards.length;
        };
        [mSearch, mGroup, mUsage].forEach(function (el) { if (el) el.addEventListener("input", filterMedia); });
    }
    document.addEventListener("click", function (e) {
        var btn = e.target.closest("[data-copy]");
        if (!btn) return;
        var text = btn.dataset.copy;
        var done = function () {
            var old = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check2"></i> Copied';
            btn.classList.add("copied");
            setTimeout(function () { btn.innerHTML = old; btn.classList.remove("copied"); }, 1500);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done, function () { window.prompt("Copy this address:", text); });
        } else {
            window.prompt("Copy this address:", text);
        }
    });

    initEditors(document);
    initCounters(document);
})();
