import "./bootstrap";
import collapse from "@alpinejs/collapse";
import mask from "@alpinejs/mask";
import toastr from "toastr";
import "toastr/build/toastr.min.css";

window.toastr = toastr;

// ✅ Plugin didaftarkan via event, Alpine-nya dari Livewire
document.addEventListener("alpine:init", () => {
    window.Alpine.plugin(collapse);
    window.Alpine.plugin(mask);
});
