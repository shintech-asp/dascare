# Sheen Russel Castillo — portfolio

An independent, static portfolio. No backend, database, login, management panel, or environment variables. No changes to DASCARE's web, API, or mobile app are needed.

## Local development

```sh
cd portfolio
npm install
npm run dev
```

Open `http://127.0.0.1:5190`.

## Static build

```sh
npm run build
```

Publish the **contents of `dist/`** to any static host when ready. Relative asset URLs support either a domain root or a subfolder. Node is used only to build; the published website needs only a static file server. Do not publish the full DASCARE repository.

On this XAMPP workspace the built page is available at `http://localhost/Dascare/portfolio/dist/` while Apache is running. Nothing is deployed by the build command.

## Content

- `src/profile.js`: name, introduction, bio, and contact links.
- `src/projects.js`: each project's description, expanded system overview, tech stack, public page gallery, and exact live destinations. Cards show concise descriptions; preview galleries explain each system and its decision support features in more detail.
- `public/images/`: locally stored public-page screenshots captured on 2026-11-25; no remote screenshot service is required.
- `src/scene.js`: original procedural Three.js portrait inspired by the supplied photo, with glasses, side-parted hair, a blazer, and an articulated waving arm. It is a stylized likeness, not a scanned or photorealistic model. The original photo is not published.
- `src/style.css`: responsive styles, dark/light themes, and animation settings.

The contact form validates inputs and creates a `mailto:` draft. The visitor must have an email handler, review the draft, and send it themselves. Nothing is stored or sent by this website; a direct email link remains available.

Project cards open a keyboard-accessible gallery with 8 public DASCARE pages and 9 public Pestify pages. `Visit this page` opens the selected live page in a new tab. Authenticated dashboards are not captured. Refresh screenshots manually when those sites change.

Theme choice and motion preference are stored in the visitor's browser. System preferences are used initially. The 3D animation pauses when offscreen or when the tab is hidden; WebGL failure leaves a visual fallback. Google Fonts is optional, with local system font fallbacks.
