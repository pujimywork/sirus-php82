const defaultTheme = require("tailwindcss/defaultTheme");
const forms = require("@tailwindcss/forms");

/** @type {import('tailwindcss').Config} */
module.exports = {
    darkMode: "class",
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
    ],
    theme: {
        extend: {
            colors: {
                // Brand inti
                brand: {
                    DEFAULT: "#157547",
                    green: "#157547",
                    lime: "#A1CD3A",
                    "green-active": "#0f5634", // hover/press hijau
                    "green-soft": "#4f9e6a", // indikator/aksen hijau-soft
                    "green-pale": "#cfe0d3", // disabled hijau
                },
                // Teks di atas permukaan gelap
                on: {
                    dark: "#f6f8f5",
                    "dark-soft": "#9aa89e",
                },
                // Semantic
                success: "#3fae6a",
                warning: "#d4a017",
                error: "#c64545",
                // ===== Design system tokens (acuan /standarisasi-ui) =====
                // Pakai sebagai utility: bg-canvas, text-ink, border-hairline, dst.
                canvas: "#f6f8f5", // kanvas terang halaman
                surface: {
                    soft: "#eef2ec", // pembatas band
                    card: "#e6ece4", // kartu fitur
                    strong: "#dbe6d6", // tab terpilih / band ditekankan
                    dark: "#14201a", // panel data / footer (forest)
                    "dark-elevated": "#1e2b23", // kartu di atas dark
                    "dark-soft": "#18241d", // blok kode di dalam dark
                },
                ink: "#13201a", // judul & teks utama
                body: {
                    DEFAULT: "#3c463f", // teks berjalan
                    strong: "#233029", // paragraf lead
                },
                muted: {
                    DEFAULT: "#69736b", // sub-judul
                    soft: "#8b948c", // caption / fine-print
                },
                hairline: {
                    DEFAULT: "#dde4d8", // garis 1px di permukaan terang
                    soft: "#e7ece4", // divider sangat halus
                },
            },
            fontFamily: {
                sans: ["Inter", ...defaultTheme.fontFamily.sans],
                // Headline editorial (substitusi Copernicus/Tiempos)
                serif: [
                    '"Cormorant Garamond"',
                    '"Tiempos Headline"',
                    "Garamond",
                    '"Times New Roman"',
                    "serif",
                ],
            },
            // Skala display serif (ukuran + line-height + tracking sudah dibundel)
            fontSize: {
                // HANYA ukuran yang TIDAK ada di Tailwind default.
                // (30→text-3xl, 24→text-2xl, 20→text-xl, 18→text-lg, 16→text-base,
                //  14→text-sm, 12→text-xs sudah native — tidak perlu dibuat.)
                "display-xl": ["52px", { lineHeight: "1.1", letterSpacing: "0.2px" }],  // antara 5xl/6xl
                "display-lg": ["38px", { lineHeight: "1.15", letterSpacing: "0.2px" }], // ~4xl tapi beda
                "title-sm": ["15px", { lineHeight: "1.45" }],                            // antara sm/base
                "caption": ["13px", { lineHeight: "1.4" }],                              // antara xs/sm
                "caption-up": ["11.5px", { lineHeight: "1.4", letterSpacing: "1px" }],   // < xs
            },
            spacing: {
                section: "96px", // jarak antar band (py-section)
            },
            backgroundImage: {
                // Garis dash pola panjang-pendek (----------- --) — lime, utk pemisah header sidebar
                "dash-lime":
                    "repeating-linear-gradient(to right, #A1CD3A 0 44px, transparent 44px 52px, #A1CD3A 52px 62px, transparent 62px 70px)",
            },
        },
    },
    plugins: [forms],
};
