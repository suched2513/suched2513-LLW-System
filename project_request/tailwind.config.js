/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./**/*.php",
    "./assets/js/**/*.js",
  ],
  theme: {
    extend: {
      fontFamily: {
        'sans': ['Prompt', 'sans-serif'],
      },
    },
  },
  plugins: [],
}
