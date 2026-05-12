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

// ✅ Plugin didaftarkan via event, Alpine-nya dari Livewire
document.addEventListener("alpine:init", () => {
    window.Alpine.plugin(collapse);
    window.Alpine.plugin(mask);
});
