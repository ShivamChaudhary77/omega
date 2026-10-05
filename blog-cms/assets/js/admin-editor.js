/**
 * Blog editor page behavior: Quill init + sync into the hidden textarea,
 * tab switching, slug/URL preview, status->scheduled field toggle, and the
 * media-picker modal (existing-image browse + new upload + insert-into-
 * content / set-as-featured-image).
 */
(function () {
    "use strict";

    document.addEventListener("DOMContentLoaded", function () {
        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || "";

        // ---- Quill editor ----
        let quill = null;
        const editorEl = document.getElementById("editor");
        if (editorEl && window.Quill) {
            quill = new Quill("#editor", {
                theme: "snow",
                modules: {
                    toolbar: {
                        container: [
                            [{ header: [2, 3, false] }],
                            ["bold", "italic"],
                            [{ list: "ordered" }, { list: "bullet" }],
                            ["blockquote", "code-block"],
                            ["link", "image", "youtube"],
                            ["clean"],
                        ],
                        handlers: {
                            image: function () { openMediaModal("editor-image"); },
                            youtube: function () {
                                const url = prompt("Paste a YouTube video URL:");
                                if (!url) return;
                                const m = url.match(/(?:youtu\.be\/|v=|embed\/)([a-zA-Z0-9_-]{11})/);
                                if (!m) { alert("Could not find a valid YouTube video ID in that URL."); return; }
                                const range = quill.getSelection(true);
                                quill.insertText(range.index, "[[youtube:" + m[1] + "]]\n");
                            },
                        },
                    },
                },
            });

            const hiddenTextarea = document.getElementById("content_html");
            const form = document.getElementById("blog-form");
            form.addEventListener("submit", function () {
                hiddenTextarea.value = quill.root.innerHTML;
            });
        }

        // Custom "youtube" toolbar button needs a label since Quill has no
        // built-in icon for it.
        const ytButton = document.querySelector(".ql-youtube");
        if (ytButton) ytButton.textContent = "▶";

        // ---- Tabs ----
        document.querySelectorAll(".tab-link[data-tab]").forEach(function (link) {
            link.addEventListener("click", function (e) {
                e.preventDefault();
                document.querySelectorAll(".tab-link[data-tab]").forEach((l) => l.classList.remove("active"));
                document.querySelectorAll(".tab-pane").forEach((p) => (p.style.display = "none"));
                link.classList.add("active");
                document.getElementById("tab-" + link.dataset.tab).style.display = "block";
            });
        });

        // ---- Slug / URL preview ----
        const titleInput = document.getElementById("title");
        const slugInput = document.getElementById("slug");
        const categorySelect = document.getElementById("category_id");
        const urlPreview = document.getElementById("url-preview");

        function slugify(text) {
            return text
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, "-")
                .replace(/-+/g, "-")
                .replace(/^-|-$/g, "");
        }
        function updateUrlPreview() {
            if (!urlPreview) return;
            const catSlug = categorySelect?.selectedOptions[0]?.dataset.slug || "category";
            const slug = slugInput.value || slugify(titleInput.value) || "post-slug";
            urlPreview.textContent = "https://theomegagroup.in/" + catSlug + "/" + slug + "/";
        }
        if (titleInput && slugInput) {
            titleInput.addEventListener("input", function () {
                if (!slugInput.dataset.userEdited) slugInput.value = slugify(titleInput.value);
                updateUrlPreview();
            });
            slugInput.addEventListener("input", function () {
                slugInput.dataset.userEdited = "1";
                updateUrlPreview();
            });
            categorySelect?.addEventListener("change", updateUrlPreview);
            updateUrlPreview();
        }

        // ---- Status -> scheduled_at field toggle ----
        const statusSelect = document.getElementById("status");
        const scheduledRow = document.getElementById("scheduled-row");
        function toggleScheduledRow() {
            if (!statusSelect || !scheduledRow) return;
            scheduledRow.style.display = statusSelect.value === "scheduled" ? "block" : "none";
        }
        statusSelect?.addEventListener("change", toggleScheduledRow);
        toggleScheduledRow();

        // ---- Media modal ----
        const modal = document.getElementById("media-modal");
        let modalTarget = null; // 'editor-image' | an input id | {preview: elId}

        function openMediaModal(target) {
            modalTarget = target;
            modal.style.display = "block";
            loadMediaGrid("");
        }
        document.getElementById("media-modal-close")?.addEventListener("click", function () {
            modal.style.display = "none";
        });

        document.querySelectorAll(".js-pick-media").forEach(function (btn) {
            btn.addEventListener("click", function () {
                openMediaModal({ input: btn.dataset.target, preview: btn.dataset.preview });
            });
        });

        function loadMediaGrid(q) {
            fetch("/blog-cms/admin/media/browse.php?q=" + encodeURIComponent(q))
                .then((r) => r.json())
                .then(function (data) {
                    const grid = document.getElementById("media-existing-grid");
                    grid.innerHTML = "";
                    const items = [...data.uploaded, ...data.existing];
                    items.forEach(function (item) {
                        const card = document.createElement("div");
                        card.style.cursor = "pointer";
                        card.innerHTML =
                            '<img src="' + (item.file_path) + '" style="width:100%; height:90px; object-fit:cover; border-radius:6px; border:1px solid #E8DFD0;">' +
                            '<div style="font-size:.7rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' + item.file_name + "</div>";
                        card.addEventListener("click", function () {
                            selectMediaItem(item);
                        });
                        grid.appendChild(card);
                    });
                });
        }

        function selectMediaItem(item) {
            // If it's an existing site image with no media ID yet, register
            // it first (lazily creates the blog_media row).
            if (!item.id) {
                fetch("/blog-cms/admin/media/select-existing.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ file_path: item.file_path, csrf_token: csrfToken }),
                })
                    .then((r) => r.json())
                    .then(function (res) {
                        if (res.ok) applyMediaSelection(res.media);
                        else alert(res.error || "Could not select this image.");
                    });
            } else {
                applyMediaSelection(item);
            }
        }

        function applyMediaSelection(media) {
            if (modalTarget === "editor-image") {
                const range = quill.getSelection(true) || { index: quill.getLength() };
                quill.insertEmbed(range.index, "image", media.file_path);
            } else if (modalTarget && modalTarget.input) {
                const input = document.getElementById(modalTarget.input);
                if (input) input.value = modalTarget.input === "featured_image_id" ? media.id : media.file_path;
                if (modalTarget.preview) {
                    document.getElementById(modalTarget.preview).innerHTML =
                        '<img src="' + media.file_path + '" style="width:100%; border-radius:6px;">';
                }
            }
            modal.style.display = "none";
        }

        document.getElementById("media-search-existing")?.addEventListener("input", function (e) {
            loadMediaGrid(e.target.value);
        });

        document.querySelectorAll(".tab-link[data-media-tab]").forEach(function (link) {
            link.addEventListener("click", function (e) {
                e.preventDefault();
                document.querySelectorAll(".tab-link[data-media-tab]").forEach((l) => l.classList.remove("active"));
                link.classList.add("active");
                const which = link.dataset.mediaTab;
                document.getElementById("media-tab-existing").style.display = which === "existing" ? "block" : "none";
                document.getElementById("media-tab-upload").style.display = which === "upload" ? "block" : "none";
            });
        });

        document.getElementById("media-upload-input")?.addEventListener("change", function (e) {
            const file = e.target.files[0];
            if (!file) return;
            const statusEl = document.getElementById("media-upload-status");
            statusEl.textContent = "Uploading…";
            const fd = new FormData();
            fd.append("file", file);
            fd.append("csrf_token", csrfToken);
            fetch("/blog-cms/admin/media/upload.php", { method: "POST", body: fd })
                .then((r) => r.json())
                .then(function (res) {
                    if (res.ok) {
                        statusEl.textContent = "Uploaded.";
                        applyMediaSelection(res.media);
                    } else {
                        statusEl.textContent = res.error || "Upload failed.";
                    }
                })
                .catch(function () {
                    statusEl.textContent = "Upload failed — could not reach the server.";
                });
        });
    });
})();
