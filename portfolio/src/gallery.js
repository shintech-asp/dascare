import { projects } from './projects.js';

const dialog = document.querySelector('#project-dialog');
const thumbnails = document.querySelector('#preview-thumbnails');
const image = document.querySelector('#preview-image');
const previous = document.querySelector('#preview-prev');
const next = document.querySelector('#preview-next');
let currentProject, selectedIndex = 0, opener;

function createTechStack(project) {
  const stack = document.createElement('div');
  stack.className = 'project-stack';
  const label = document.createElement('span');
  label.className = 'project-stack-label';
  label.textContent = 'Tech stack';
  const technologies = document.createElement('ul');
  technologies.setAttribute('aria-label', `${project.name} tech stack`);
  for (const technology of project.stack) {
    const item = document.createElement('li');
    item.textContent = technology;
    technologies.append(item);
  }
  stack.append(label, technologies);
  return stack;
}

function selectPage(index) {
  selectedIndex = index;
  const page = currentProject.pages[index];
  image.hidden = false;
  document.querySelector('.preview-unavailable').hidden = true;
  image.src = `${import.meta.env.BASE_URL}images/${page.image}.jpg`;
  image.alt = `${currentProject.name} — ${page.name}, saved website preview`;
  document.querySelector('#preview-page-name').textContent = page.name;
  document.querySelector('#preview-page-description').textContent = page.description;
  document.querySelector('#preview-visit').href = `${currentProject.origin}${page.path}`;
  document.querySelector('#preview-counter').textContent = `${index + 1} of ${currentProject.pages.length}`;
  previous.disabled = index === 0;
  next.disabled = index === currentProject.pages.length - 1;
  thumbnails.querySelectorAll('button').forEach((button, i) => button.setAttribute('aria-current', String(i === index)));
}
image.addEventListener('error', () => {
  image.hidden = true;
  document.querySelector('.preview-unavailable').hidden = false;
});
function openProject(key, source) {
  currentProject = projects[key];
  opener = source;
  document.querySelector('#preview-title').textContent = currentProject.name;
  dialog.querySelector('.preview-overview')?.remove();
  dialog.querySelector('.project-stack')?.remove();
  const overview = document.createElement('p');
  overview.className = 'preview-overview';
  overview.textContent = currentProject.overview;
  dialog.querySelector('.preview-header').after(overview, createTechStack(currentProject));
  thumbnails.replaceChildren();
  currentProject.pages.forEach((page, index) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'preview-thumb';
    button.setAttribute('aria-label', `Preview ${page.name}`);
    const thumbnail = document.createElement('img');
    thumbnail.src = `${import.meta.env.BASE_URL}images/${page.image}.jpg`;
    thumbnail.alt = '';
    thumbnail.loading = 'lazy';
    const name = document.createElement('span');
    name.textContent = page.name;
    button.append(thumbnail, name);
    button.addEventListener('click', () => selectPage(index));
    thumbnails.append(button);
  });
  selectPage(0);
  dialog.showModal();
  document.body.classList.add('modal-open');
  dialog.scrollTop = 0;
}
for (const [key, project] of Object.entries(projects)) {
  document.querySelector(`.project-${key} .project-info > div > p`).textContent = project.description;
  document.querySelector(`.project-${key} .project-info > div`).append(createTechStack(project));
  document.querySelectorAll(`.project-${key} .project-visual, .project-${key} .project-link`).forEach(link => {
    link.removeAttribute('target');
    link.removeAttribute('rel');
    link.setAttribute('aria-haspopup', 'dialog');
    link.setAttribute('aria-controls', 'project-dialog');
    link.setAttribute('aria-label', `Preview ${project.name} pages`);
    if (link.classList.contains('project-link')) link.textContent = `Preview ${project.pages.length} pages`;
    else link.querySelector('.project-view').textContent = 'Explore page previews';
    link.addEventListener('click', event => { event.preventDefault(); openProject(key, link); });
  });
}
previous.addEventListener('click', () => { if (selectedIndex > 0) selectPage(selectedIndex - 1); });
next.addEventListener('click', () => { if (selectedIndex < currentProject.pages.length - 1) selectPage(selectedIndex + 1); });
dialog.querySelector('.dialog-close').addEventListener('click', () => dialog.close());
dialog.addEventListener('click', event => {
  const bounds = dialog.getBoundingClientRect();
  if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
});
dialog.addEventListener('close', () => { document.body.classList.remove('modal-open'); opener?.focus(); });
dialog.addEventListener('keydown', event => {
  if (event.key === 'ArrowRight' && selectedIndex < currentProject.pages.length - 1) { event.preventDefault(); selectPage(selectedIndex + 1); }
  if (event.key === 'ArrowLeft' && selectedIndex > 0) { event.preventDefault(); selectPage(selectedIndex - 1); }
});
