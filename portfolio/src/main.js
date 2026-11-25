import './style.css';
import { profile } from './profile.js';
import './gallery.js';

const themeButton = document.querySelector('#theme-toggle');
function updateThemeButton() {
  const isDark = document.documentElement.dataset.theme === 'dark';
  themeButton.textContent = isDark ? '☀' : '☾';
  themeButton.setAttribute('aria-label', `Switch to ${isDark ? 'light' : 'dark'} mode`);
  themeButton.title = `Switch to ${isDark ? 'light' : 'dark'} mode`;
  document.querySelector('meta[name="theme-color"]').content = isDark ? '#101114' : '#f6f8fb';
}
updateThemeButton();
themeButton.addEventListener('click', () => {
  const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
  document.documentElement.dataset.theme = theme;
  try { localStorage.setItem('portfolio-theme', theme); } catch { /* Current session still works. */ }
  updateThemeButton();
});
matchMedia('(prefers-color-scheme: light)').addEventListener('change', event => {
  try { if (localStorage.getItem('portfolio-theme')) return; } catch { /* Use system preference. */ }
  document.documentElement.dataset.theme = event.matches ? 'light' : 'dark';
  updateThemeButton();
});

document.querySelector('#contact-form').addEventListener('submit', event => {
  event.preventDefault();
  const form = event.currentTarget;
  if (!form.reportValidity()) return;
  const values = new FormData(form);
  const name = values.get('name').trim();
  const subject = values.get('subject').trim();
  const message = values.get('message').trim();
  if (!name || !subject || !message) {
    document.querySelector('#form-status').textContent = 'Please add your name, a subject, and a message.';
    return;
  }
  const body = `Hi Sheen,\n\n${message}\n\nFrom: ${name}\nEmail: ${values.get('email').trim()}`;
  const url = `mailto:${profile.email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
  document.querySelector('#form-status').textContent = 'Your email app will open with a draft. Review it and press Send there. If no app opens, use the email address above; your message stays here.';
  window.location.href = url;
});

document.querySelectorAll('[data-profile]').forEach(element => {
  element.textContent = profile[element.dataset.profile];
});
document.title = profile.name === 'Your name' ? 'Selected work — Personal portfolio' : `${profile.name} — ${profile.title}`;
document.querySelector('#year').textContent = new Date().getFullYear();
const contacts = document.querySelector('#contact-links');
for (const [label, url] of [['Email me', profile.email ? `mailto:${profile.email}` : ''], ['Call me', profile.phone ? `tel:${profile.phone}` : ''], ['Facebook', profile.facebook], ['GitHub', profile.github], ['LinkedIn', profile.linkedin]]) {
  if (!url) continue;
  if (!/^(https:\/\/|mailto:|tel:)/.test(url)) continue;
  const link = document.createElement('a');
  link.className = `button button-primary ${label !== 'Email me' ? 'social-link' : ''}`;
  link.textContent = label;
  link.href = url;
  if (url.startsWith('https:')) { link.target = '_blank'; link.rel = 'noopener noreferrer'; }
  contacts.append(link);
}
if (contacts.children.length) document.querySelector('#contact-hint').textContent = 'Let’s see what we can build together.';

const systemMotion = matchMedia('(prefers-reduced-motion: reduce)');
let userMotion = null;
try { userMotion = localStorage.getItem('portfolio-motion'); } catch { /* Storage may be disabled. */ }
let reducedMotion = userMotion === null ? systemMotion.matches : userMotion === 'off';
const motionButton = document.querySelector('#motion-toggle');
function applyMotion() {
  document.documentElement.classList.toggle('reduce-motion', reducedMotion);
  motionButton.textContent = `Motion: ${reducedMotion ? 'off' : 'on'}`;
  motionButton.setAttribute('aria-pressed', String(reducedMotion));
  window.dispatchEvent(new CustomEvent('portfolio:motion', { detail: reducedMotion }));
}
applyMotion();
motionButton.addEventListener('click', () => {
  reducedMotion = !reducedMotion;
  userMotion = reducedMotion ? 'off' : 'on';
  try { localStorage.setItem('portfolio-motion', userMotion); } catch { /* In-memory choice still works. */ }
  applyMotion();
});
systemMotion.addEventListener('change', event => { if (userMotion === null) { reducedMotion = event.matches; applyMotion(); } });

const revealObserver = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (!entry.isIntersecting) return;
    entry.target.classList.add('is-visible');
    revealObserver.unobserve(entry.target);
  });
}, { threshold: 0.08 });
document.querySelectorAll('.reveal').forEach(element => {
  element.classList.add('will-reveal');
  revealObserver.observe(element);
});

let scrollQueued = false;
function updateScroll() {
  const max = document.documentElement.scrollHeight - innerHeight;
  document.querySelector('.scroll-progress').style.transform = `scaleX(${max > 0 ? scrollY / max : 0})`;
  document.querySelector('.site-header').classList.toggle('scrolled', scrollY > 24);
  scrollQueued = false;
}
addEventListener('scroll', () => { if (!scrollQueued) { requestAnimationFrame(updateScroll); scrollQueued = true; } }, { passive: true });
addEventListener('resize', updateScroll);
updateScroll();

const navLinks = [...document.querySelectorAll('nav a')];
const sections = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (!entry.isIntersecting) return;
    navLinks.forEach(link => {
      if (link.hash === `#${entry.target.id}`) link.setAttribute('aria-current', 'location');
      else link.removeAttribute('aria-current');
    });
  });
}, { rootMargin: '-20% 0px -50% 0px' });
document.querySelectorAll('main section[id]').forEach(section => sections.observe(section));

// Keep WebGL out of the initial text/layout bundle and preserve a CSS fallback.
import('./scene.js').then(({ createAvatar }) => createAvatar(reducedMotion)).catch(() => {
  const button = document.querySelector('#wave-button');
  button.textContent = 'Hello there!';
  button.disabled = true;
});
