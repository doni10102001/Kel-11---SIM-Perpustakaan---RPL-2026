# TASK 00 — INITIAL PROJECT SETUP: LARAVEL + VUE (HELLO / ECHO)

You are Claude Code running locally with permission to inspect and edit files in the current workspace. Act as a careful Laravel + Vue engineer.

## Goal

Initialize **one integrated Laravel project** named `sim-perpustakaan`. Vue 3 must run **inside Laravel**, through Inertia.js and Vite. This task is **ONLY the initial project setup and a minimal test page**.

Create **exactly one application page/URL**:

- **GET `/hello/echo`** — displays a Vue page with the heading **"Hello Echo"**, a text input **"Pesan"**, and a live preview **"Echo: <typed message>"** updated using Vue reactive state. This proves Laravel → Inertia → Vue → Vite works. Do not create an API endpoint for echo.

Do not build any other page, dashboard, feature, user role, authentication, or navigation yet. No separate frontend or backend project.

## Tech stack

- PHP **8.4**, if available and compatible
- Laravel **13.x**, if available and compatible
- Vue.js **3**
- Inertia.js (Laravel adapter + Vue 3 adapter, mutually compatible)
- Bootstrap **5.3**
- Vite + npm
- JavaScript (not TypeScript unless existing project already requires it)

First check current official installation/compatibility guidance, particularly:
- https://laravel.com/for/agents
- https://laravel.com/docs/13.x/installation
- https://inertiajs.com/
- https://getbootstrap.com/docs/5.3/

Do not use outdated Inertia setup commands or assume package versions without checking compatibility. Prefer a minimal manual integration over installing a starter kit that brings authentication, Tailwind, or extra pages.

## Hard scope boundaries

**Do not create or use a database at this stage.** Specifically:

- NO MySQL, SQLite, PostgreSQL or database file creation for this task.
- NO `php artisan migrate`, seeders, factories, database tables, models, or database queries.
- NO login, register, roles, policies, authentication, API routes, REST endpoints, or business modules.
- NO Tailwind, React, Next.js, Nuxt, Docker or separate Vue CLI/Vite app.
- NO Admin/Petugas/Anggota screens or implementation of mockups yet.
- NO fake server persistence. The echo interaction is client-side only.

Configure local `.env` so the app boots without database-backed session/cache/queue, e.g. compatible `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`. Use appropriate framework defaults and avoid any database operation on page load.

## Workspace safety

1. Inspect OS, current directory, existing files, PHP, Composer, Node, npm and Git versions before changing anything.
2. If an existing Laravel app is present, **reuse it**; do not replace it.
3. If the directory is empty, initialize Laravel **in the current project directory**. Do not create a redundant nested `sim-perpustakaan/sim-perpustakaan` folder.
4. If the directory contains docs/mockups but no Laravel installation, preserve them, assess safe initialization, and proceed without overwriting files. Ask only if there is a real destructive conflict.
5. Never delete, overwrite or reset existing work or databases. Do not use `--force` to overwrite files without inspecting them.
6. If a missing dependency requires admin privileges or system-wide installation, report the need before making changes.

## Files/configuration expected

Use Laravel's normal file structure with a minimal Inertia integration, including the equivalent of:

```
sim-perpustakaan/
├── app/
├── bootstrap/
├── config/
├── public/
├── resources/
│   ├── css/
│   │   └── app.css
│   ├── js/
│   │   ├── app.js
│   │   └── Pages/
│   │       └── HelloEcho.vue
│   └── views/
│       └── app.blade.php
├── routes/
│   └── web.php
├── vite.config.js
├── package.json
├── composer.json
├── .env.example
└── README.md
```

- Integrate Bootstrap 5.3 CSS in the Laravel/Vite entry, plus optional tiny custom CSS.
- Make sure the Blade root template contains the appropriate Vite and Inertia directives for the installed version.
- Make sure the Laravel route returns an **Inertia page**, not a Blade-only Hello World screen.
- Do not add unnecessary controllers just for a single static page unless required by project conventions.
- Use the **exact route path `/hello/echo`**, not two separate URLs `/hello` and `/echo`.
- Do not create additional application routes. If the Laravel default `/` welcome page exists, replace/remove it to avoid a second application page; let `/` be 404 unless the framework requires otherwise.

## Minimal UI behavior

The `/hello/echo` screen should be simple, polished, and self-contained:

- Title: `Hello Echo`
- Subtitle: `Laravel + Inertia + Vue 3 + Bootstrap 5.3`
- One labeled input: `Pesan`
- One reactive display: `Echo: ...`
- A small indication `Static UI — tanpa database` is acceptable **only on this technical smoke-test page**.

Do not fetch data. Do not store anything in localStorage or a database. No extra screens or forms.

## Execution and verification

Run the actual installation/configuration commands locally, not just generate a plan.

Check and report the outcomes of:

1. `php artisan --version`
2. `composer validate` (when applicable)
3. `npm install` and `npm run build`
4. `php artisan route:list --path=hello` — verify **GET `/hello/echo`**
5. Basic application boot check without a database
6. If practical, start local Laravel + Vite servers, load `/hello/echo`, and verify that the Vue text input updates the Echo display. If GUI/browser testing is unavailable, say so explicitly.

Use suitable Laravel commands to generate `APP_KEY`; do not commit `.env` or real secrets. Do not run migrations.

Document in `README.md`:
- versions installed
- how to install dependencies
- how to start Laravel (`php artisan serve`)
- how to start Vite (`npm run dev`) in a separate terminal, or the supported combined command
- test URL: `http://localhost:8000/hello/echo` (if port 8000 is actually used)
- note: **no database, authentication, API, or business modules yet**

At the end, provide a concise factual report: commands run, files created/changed, versions, verified route, Vite build result, local URL, and any blockers. **Do not claim checks passed unless you executed them.**

## Start now

Inspect the existing workspace, then initialize the minimal single-folder Laravel + Vue application and implement **only `/hello/echo`**.
