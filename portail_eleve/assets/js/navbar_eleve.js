// navbar_eleve.js - responsive pour le navbar

const openButton = document.getElementById('open-sidebar-button');
const navbar = document.getElementById('navbar');
const overlay = document.getElementById('overlay');
const media = window.matchMedia('(max-width: 700px)');

function updateNavbar() {
  if (!navbar) return;

  if (media.matches) {
    navbar.setAttribute('inert', '');
  } else {
    navbar.removeAttribute('inert');
    navbar.classList.remove('show');
    if (openButton) {
      openButton.setAttribute('aria-expanded', 'false');
    }
  }
}

function openSidebar() {
  if (!navbar) return;
  navbar.classList.add('show');
  navbar.removeAttribute('inert');
  if (openButton) {
    openButton.setAttribute('aria-expanded', 'true');
  }
}

function closeSidebar() {
  if (!navbar) return;
  navbar.classList.remove('show');
  if (openButton) {
    openButton.setAttribute('aria-expanded', 'false');
  }
  if (media.matches) {
    navbar.setAttribute('inert', '');
  }
}

media.addEventListener('change', updateNavbar);
document.addEventListener('DOMContentLoaded', updateNavbar);