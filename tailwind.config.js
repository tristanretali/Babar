/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: ["./assets/**/*.js", "./templates/**/*.html.twig"],
  theme: {
    extend: {
      colors: {
        'lbrown-button': '#AA9C95',
        'dbrown-button': '#977E70',
        'dark-button': '#515151',
        'white-babar': '#F2F2F2',
        'grey-babar': '#D9D9D9',
        'black-babar': '#1A1A1A',
        'grey-text': '#989898'
      }
    },
  },
  plugins: [],
};
