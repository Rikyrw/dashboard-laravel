// Inisialisasi AOS
AOS.init({ duration: 1000, once: true });

const htmlEl = document.documentElement; // ⬅️ target <html>
const toggleButton = document.getElementById('darkToggle');
const navbar = document.querySelector('nav');

// Fungsi toggle dark mode
function toggleDarkMode() {
  const isDark = htmlEl.classList.toggle('dark');
  localStorage.setItem('darkMode', isDark);
  toggleButton.textContent = isDark ? '☀️' : '🌙';
}

// Saat halaman pertama kali dimuat
document.addEventListener('DOMContentLoaded', () => {
  const mode = localStorage.getItem('darkMode') === 'true';
  if (mode) htmlEl.classList.add('dark');
  else htmlEl.classList.remove('dark');
  toggleButton.textContent = mode ? '☀️' : '🌙';
});

// Event klik toggle
toggleButton.addEventListener('click', toggleDarkMode);

// Efek shadow navbar saat scroll
window.addEventListener('scroll', () => {
  navbar.classList.toggle('scrolled', window.scrollY > 20);
});