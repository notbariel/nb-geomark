const defaultTheme = require("tailwindcss/defaultTheme");

module.exports = {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.ts",
        "./resources/**/*.vue",
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ["Work Sans", ...defaultTheme.fontFamily.sans],
            },
        },
    },
    plugins: [require("daisyui")],
};
