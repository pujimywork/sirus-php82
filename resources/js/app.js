import "./bootstrap";
import collapse from "@alpinejs/collapse";
import mask from "@alpinejs/mask";
import toastr from "toastr";
import "toastr/build/toastr.min.css";

// Quill Rich Text Editor — Word-style toolbar, output HTML
import Quill from "quill";
import "quill/dist/quill.snow.css";
window.Quill = Quill;

// Default toolbar preset Word-style. Bisa di-override per instance via prop `toolbar`.
const QUILL_TOOLBAR_DEFAULT = [
    [{ header: [1, 2, 3, false] }],
    ["bold", "italic", "underline", "strike"],
    [{ color: [] }, { background: [] }],
    [{ list: "ordered" }, { list: "bullet" }],
    [{ indent: "-1" }, { indent: "+1" }],
    [{ align: [] }],
    ["blockquote", "link"],
    ["clean"],
];

window.QuillToolbarPresets = {
    default: QUILL_TOOLBAR_DEFAULT,
    minimal: [
        ["bold", "italic", "underline"],
        [{ list: "ordered" }, { list: "bullet" }],
    ],
};

// Alpine.data factory — dipakai oleh komponen <x-quill-editor>
document.addEventListener("alpine:init", () => {
    window.Alpine.data(
        "quillEditor",
        ({
            propName,
            placeholder = "Tulis di sini…",
            toolbar = "default",
            modalEvent = null,
            flushEvent = null,
        }) => ({
            quill: null,
            init() {
                if (modalEvent) {
                    window.addEventListener("open-modal", (e) => {
                        if (e.detail?.name === modalEvent) {
                            setTimeout(() => this.bootEditor(), 120);
                        }
                    });
                    window.addEventListener("close-modal", (e) => {
                        if (e.detail?.name === modalEvent) {
                            this.cleanupEditor();
                        }
                    });
                } else {
                    this.$nextTick(() => this.bootEditor());
                }

                // Custom event untuk paksa flush isi ke $wire (sebelum action submit)
                if (flushEvent) {
                    window.addEventListener(flushEvent, () => this.flush());
                }
            },
            cleanupEditor() {
                // Quill 2.x tidak punya destroy(). Manual cleanup:
                // - Hapus toolbar (.ql-toolbar) yang Quill insert sebagai sibling host
                // - Reset host element ke state semula (class + innerHTML)
                const host = this.$refs.host;
                if (!host) return;

                const parent = host.parentNode;
                if (parent) {
                    parent.querySelectorAll(".ql-toolbar").forEach((el) => el.remove());
                    parent.querySelectorAll(".ql-tooltip").forEach((el) => el.remove());
                }
                host.className = "";
                host.innerHTML = "";
                host.removeAttribute("style");
                this.quill = null;
            },
            bootEditor() {
                // Pastikan tidak ada residual instance/toolbar dari open-modal sebelumnya
                this.cleanupEditor();

                const host = this.$refs.host;
                if (!host) return;

                const toolbarDef =
                    typeof toolbar === "string"
                        ? window.QuillToolbarPresets[toolbar] || window.QuillToolbarPresets.default
                        : toolbar;

                this.quill = new window.Quill(host, {
                    theme: "snow",
                    placeholder: placeholder,
                    modules: { toolbar: toolbarDef },
                });

                // Pre-fill dari Livewire property
                const initial = this.$wire.get(propName) || "";
                if (initial) this.quill.root.innerHTML = initial;

                this.quill.on("text-change", () => this.flush());
            },
            flush() {
                if (!this.quill) return;
                const html = this.quill.root.innerHTML;
                const clean = html === "<p><br></p>" ? "" : html;
                this.$wire.set(propName, clean, false);
            },
        })
    );
});

window.toastr = toastr;

// TinyMCE — full-featured rich text editor dengan table support (community GPL)
// Pakai self-hosted bundle (npm package), no API key needed.
import tinymce from "tinymce";
import "tinymce/icons/default";
import "tinymce/themes/silver";
import "tinymce/models/dom";
// Plugins yang dipakai (subset)
import "tinymce/plugins/lists";
import "tinymce/plugins/link";
import "tinymce/plugins/table";
import "tinymce/plugins/autolink";
import "tinymce/plugins/code";
import "tinymce/plugins/charmap";
// Skin & content CSS — import via Vite (di-bundle ke build)
import "tinymce/skins/ui/oxide/skin.min.css";
import contentCss from "tinymce/skins/content/default/content.min.css?inline";
import contentUiCss from "tinymce/skins/ui/oxide/content.min.css?inline";

window.tinymce = tinymce;

// Alpine.data factory untuk <x-tinymce-editor>
document.addEventListener("alpine:init", () => {
    window.Alpine.data(
        "tinymceEditor",
        ({
            propName,
            placeholder = "Tulis di sini…",
            modalEvent = null,
            flushEvent = null,
            reloadEvent = null,
            height = 480,
        }) => ({
            editor: null,
            init() {
                if (modalEvent) {
                    window.addEventListener("open-modal", (e) => {
                        if (e.detail?.name === modalEvent) {
                            setTimeout(() => this.bootEditor(), 120);
                        }
                    });
                    window.addEventListener("close-modal", (e) => {
                        if (e.detail?.name === modalEvent) {
                            this.cleanupEditor();
                        }
                    });
                } else {
                    this.$nextTick(() => this.bootEditor());
                }
                if (flushEvent) {
                    window.addEventListener(flushEvent, () => this.flush());
                }
                if (reloadEvent) {
                    window.addEventListener(reloadEvent, () => this.reload());
                }
            },
            reload() {
                if (!this.editor) return;
                const fresh = this.$wire.get(propName) || "";
                this.editor.setContent(fresh);
            },
            cleanupEditor() {
                // tinymce.remove() = canonical cleanup di TinyMCE 8 (destroy + unbind global).
                if (this.editor) {
                    try { tinymce.remove(this.editor); } catch (e) {
                        console.warn("[tinymceEditor] remove failed:", e);
                    }
                    this.editor = null;
                }
                // Defensive: hapus orphan instance yang masih nempel ke host
                const host = this.$refs?.host;
                if (host && host.id) {
                    try {
                        const orphan = tinymce.get(host.id);
                        if (orphan) tinymce.remove(orphan);
                    } catch (e) {}
                }
            },
            bootEditor() {
                this.cleanupEditor();
                const host = this.$refs.host;
                if (!host) {
                    console.warn("[tinymceEditor] x-ref host not found");
                    return;
                }

                // Workaround bug TinyMCE 8: `purgeDestroyedEditor` crash kalau ada
                // null entry di `tinymce.editors` global registry (dari destroy/remove
                // sebelumnya yang tidak bersih). Force-filter null sebelum init.
                try {
                    if (Array.isArray(tinymce.editors)) {
                        tinymce.editors = tinymce.editors.filter((e) => e != null);
                    }
                } catch (e) {
                    console.warn("[tinymceEditor] could not purge null editors:", e);
                }

                // Reset textarea state — sapu bersih artifact dari destroy sebelumnya
                host.style.cssText = "";
                host.className = "";
                host.value = "";

                // Assign unique ID + pakai selector (lebih reliable daripada target)
                host.id = "tinymce-host-" + Math.random().toString(36).slice(2);

                const initial = this.$wire.get(propName) || "";
                console.log("[tinymceEditor] init start", {
                    propName,
                    hostId: host.id,
                    initialLen: initial.length,
                });
                tinymce
                    .init({
                        selector: "#" + host.id,
                        // TinyMCE 6.8+ / 7+ / 8+ wajib explicit license key.
                        // 'gpl' = pakai versi open-source (GPLv2), tidak butuh API key Tiny Cloud.
                        license_key: "gpl",
                        height: height,
                        menubar: false,
                        branding: false,
                        promotion: false,
                        plugins: "lists link table autolink code charmap",
                        toolbar:
                            "undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | " +
                            "alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | " +
                            "table | link charmap | removeformat code",
                        placeholder: placeholder,
                        skin: false,
                        content_css: false,
                        content_style: contentCss + "\n" + contentUiCss + "\nbody { font-size: 14px; }",
                        setup: (ed) => {
                            this.editor = ed;
                            ed.on("init", () => {
                                console.log("[tinymceEditor] editor init complete");
                                if (initial) ed.setContent(initial);
                            });
                            ed.on(
                                "input change keyup blur SetContent",
                                () => this.flush()
                            );
                        },
                    })
                    .then((editors) => {
                        console.log("[tinymceEditor] init resolved", editors?.length);
                    })
                    .catch((err) => {
                        console.error("[tinymceEditor] init failed:", err);
                    });
            },
            flush() {
                if (!this.editor) return;
                const html = this.editor.getContent();
                this.$wire.set(propName, html, false);
            },
        })
    );
});

// ✅ Plugin didaftarkan via event, Alpine-nya dari Livewire
document.addEventListener("alpine:init", () => {
    window.Alpine.plugin(collapse);
    window.Alpine.plugin(mask);
});
