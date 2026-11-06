# CLAUDE TASK 01 - BUILD FINAL STATIC PAGES AS THE DESIGN SOURCE OF TRUTH

Project: Sistem Informasi Manajemen Perpustakaan (SIM Perpustakaan)
Workspace: Existing, already initialized Laravel + Vue 3 + Inertia.js + Bootstrap 5.3 + Vite application.

## How to use this file

Open Claude Code in the existing Laravel project root. Paste the **START PROMPT** below.
After Claude completes one phase, inspect the result, then paste the next phase command (listed below). Do not execute all phases in one unreviewed batch.

## START PROMPT (paste this first)

Read this entire file and inspect my CURRENT Laravel project. This project has ALREADY been initialized; do not re-run project creation, do not replace composer.json/package.json, and do not recreate the Laravel app.

Our immediate goal is to build the FINAL, VISUALLY APPROVED STATIC SIM Perpustakaan website using Vue 3 inside Laravel via Inertia.js. Design every page yourself based on SRS/UML without requiring mockup files. The completed browser-rendered pages will be the SINGLE SOURCE OF TRUTH for Google Stitch, which will only reproduce them as mockups as closely as possible. DO NOT treat the website as a disposable wireframe or plan to redesign it in Stitch. Make both the visual layout and functional content final before capture.

Read documentation if present:
- docs/requirements/ (SRS v1.1 is the primary functional reference)
- docs/uml/ (Use Case, Activity, Sequence, State Machine, Class/Object)

If SRS or UML documents are in different directories, locate them without deleting or moving unrelated files. Respect the existing project structure. Do not depend on, search for, or require any mockup files. Design the initial static pages yourself using SRS/UML as the functional source of truth and simple professional Bootstrap UI. If a requirement is unclear, document the gap rather than inventing new fields, statuses, or business policies.

GLOBAL CONSTRAINTS:
- ONE Laravel repository; Vue files live in resources/js/.
- NO database access, no migrations, no Eloquent/DB queries, no API requests, no HTTP calls for data, no real authentication or backend transaction logic, no additional frontend project.
- All screen data comes from centralized static JS modules. Vue reactive state may simulate forms and transactions in memory only; nothing must persist after refresh.
- If Laravel currently uses database-backed session/cache/queue, change these safely to file/sync/local equivalents without running a migration. Do not disclose secrets.
- Do not expose internal project notes such as SRS, UC, FR, TBD, or 'usulan' in user-facing pages. Keep them only in docs/.
- All user-facing interface text must be professional Bahasa Indonesia.
- VISUAL FIDELITY IS CRITICAL: each page must have finished typography, spacing, padding, layout, sidebar width, header height, colors, borders, shadows, icons, cards, tables, forms, badges, responsive behavior, and error/empty states. Build a polished final UI, not a rough Bootstrap wireframe.
- Code in Vue/Laravel is the canonical design and content reference. Do not ask Google Stitch to improve, restyle, or reinterpret it.
- Prepare reproducible screenshots at a fixed 1440x900 desktop viewport (and optionally 390x844 mobile), browser zoom 100%, with assets/fonts fully loaded. Use a stable dataset and screenshot state so captures are deterministic.
- Do not reference or require docs/mockups/admin/ or docs/mockups/anggota/. Design all UI directly in source code from approved SRS/UML requirements.
- Keep existing /hello/echo route working as a technical smoke test, unless I explicitly request removing it.
- Keep demo routes clearly separated from authenticated production routes. Demo role switching is not real security.
- Do not add Tailwind/React/Nuxt or replace working Vue/Inertia/Bootstrap integration.
- Do not overwrite any existing work without inspecting it first.
- After each phase: run npm build or available checks, report changed files and routes, and STOP for my review.

IMPLEMENT **PHASE 0 ONLY** now: inspect project and provide an inventory of dependencies, routes, layouts, docs, and existing UI. Verify /hello/echo works or report why it does not. Recommend any minimal changes needed before Phase 1. Do not build pages yet.

## PHASE 1 COMMAND - SHARED LAYOUT AND NAVIGATION

Proceed with Phase 1 only. Reuse the existing Laravel+Vue+Inertia setup. Create the FINAL shared visual foundation with polished Bootstrap 5.3 + custom CSS. This is not a temporary prototype styling pass; the resulting visuals are intended to be copied as faithfully as possible into Stitch:
- Shared Login page (Identitas Pengguna, Kata Sandi, Masuk; static preview only)
- Admin/Petugas layout (sidebar, header, content shell)
- Anggota layout (sidebar, header, content shell)
- Reusable UI components: PageHeader, BaseInput, BaseSelect, BaseButton, BaseTable, StatusBadge, FormFeedback, EmptyState, and a modal only when useful for documented actions
- Define route name constants or a central navigation configuration to avoid broken links

Admin/Petugas navigation should reflect existing screens: Dashboard; Data Anggota; Data Buku & Eksemplar; Peminjaman Buku; Pengembalian Buku; Verifikasi Booking; Laporan; Keluar. Realisasi Booking is reached from the verification workflow and can have its own route. Do not create unapproved admin modules.
Anggota navigation: Dashboard; Cari Buku; Booking Pinjam Buku (Ajukan / Status); History Peminjaman; Keluar.

Use example routes only if the project has no conventions already established:
- /login
- /demo/admin/dashboard
- /demo/admin/anggota
- /demo/admin/anggota/tambah
- /demo/admin/buku
- /demo/admin/buku/tambah
- /demo/admin/peminjaman
- /demo/admin/pengembalian
- /demo/admin/laporan
- /demo/admin/booking/verifikasi
- /demo/admin/booking/realisasi
- /demo/anggota/dashboard
- /demo/anggota/cari-buku
- /demo/anggota/booking/ajukan
- /demo/anggota/booking/status
- /demo/anggota/history

Render through Inertia::render and Vue pages. During static phase, make routes publicly reachable on localhost for direct Google Stitch capture. Do not claim this is secure authentication. Do not implement real login. Do not run migrations.

After implementing, test navigation and npm run build. Stop for review.

## PHASE 2 COMMAND - CENTRALIZED STATIC DATA

Proceed with Phase 2 only. Add ONE consistent static domain dataset in resources/js/data/mock/ (adapt paths to current structure). Include:
- members: member_code, name, contact, status (Aktif or Nonaktif)
- books: book_code, title, author, publisher, year, category
- book_copies: book_id/book_code relationship, copy_code, status (Tersedia, Dipesan, Dipinjam)
- loans: ID, member, copy, staff, loaned_at, due_at, status (Aktif/Selesai)
- returns: loan ID, staff, returned_at
- bookings: ID, member, copy, submitted_at, staff/decided_at as applicable, status (Diajukan, Disetujui, Ditolak, Dibatalkan, Direalisasikan)
- dashboard values derived from the same datasets, not contradictory hardcoded counts

Maintain consistency:
- An active loan uses a Dipinjam copy; no two active loans for the same copy.
- An active loan has NO returned_at. A completed loan has a corresponding return record.
- An approved, non-realized booking uses a Dipesan copy.
- A submitted booking starts in Diajukan; do not rename it to Menunggu.
- A realized booking links to an active loan and a Dipinjam copy.
- Every foreign reference points to a real member/book/copy in the mock dataset.
- Member with status Nonaktif must not be used in a newly simulated loan.
- Booking policy, loan duration, fines, and quotas are unresolved in SRS. Do not invent fixed policy values. Example dates in static records are examples, not universal rules.

Use fictional demo identities. If mutations are simulated, use a lightweight in-memory reactive store backed by this data, not localStorage and not a backend. Keep it simple; no need for Pinia unless already installed.

Create a basic development-only data consistency check (script or test) for these invariants. Run it and npm run build. Stop for review.

## PHASE 3 COMMAND - MASTER DATA AND ADMIN DASHBOARD

Proceed with Phase 3 only. Build these screens from the SRS and UML requirements. Create the finished, visually polished UI yourself. Keep ALL documented input labels, table columns, required indicators, actions, and navigation consistent with the SRS/UML. Do not wait for mockups:
1. Admin/Petugas Dashboard: summary of member count, book count, active loans, submitted bookings; derive all values from mock data. Design the FINAL panels yourself, with consistent styling shared across all pages.
2. Data Anggota: search, status filter, list/table, view/add/edit actions; fields ID/Kode Anggota, Nama, Kontak, Status, Aksi as defined in SRS/UML.
3. Form Anggota: ID Anggota/Kode Anggota, Nama, Kontak, Status Anggota; required-field and duplicate-member-code error display.
4. Data Buku & Eksemplar: search by book code/title/category, book table with Kode Buku, Judul, Penulis, Penerbit, Tahun, Kategori; an associated exemplar list showing Kode Eksemplar and Status.
5. Form Buku & Eksemplar: Kode Buku, Judul, Penulis, Penerbit, Tahun Terbit, Kategori, plus repeating exemplar rows Kode Eksemplar/Status; duplicate code validation. Match existing buttons.

Notes: use SRS/UML terminology; do not automatically add emails, study programs, ISBN, shelf codes, or other undocumented fields. Do not physically delete records with transaction history if the SRS recommends deactivation. No database and no API. Demo save/change actions may update in-memory state only and clearly show temporary feedback.

Verify direct URLs, documented fields, table columns, and actions against SRS/UML; run npm run build and stop for review.

## PHASE 4 COMMAND - ADMIN TRANSACTIONS, REPORTS, BOOKINGS

Proceed with Phase 4 only. Build the remaining Admin/Petugas screens:
6. Peminjaman Buku: select active member and available physical copy; show member status, book/copy selection, loan date, due date, responsible staff, validation, and existing save action. In-memory simulation must update loan + copy state together. Do not invent a universal loan duration.
7. Pengembalian Buku: list active loans, select an active loan, show ID Peminjaman, Tanggal Kembali, Petugas Proses and existing return action. Reject duplicate return. On success, loan -> Selesai, copy -> Tersedia, create a mock return record.
8. Kelola Laporan: Jenis Laporan (Daftar Buku, Daftar Anggota, Peminjaman, Pengembalian), Periode Awal/Akhir, Tampilkan Laporan, Cetak Laporan; preview tables populated from shared mock dataset. Keep Cetak Laporan as a print-oriented UI action; a static browser print preview is acceptable. Do not add unsupported PDF/Excel export.
9. Verifikasi Booking: list statuses exactly (Diajukan, Disetujui, Ditolak, Dibatalkan, Direalisasikan); detail panel, Setujui/Tolak. Approval reserves the chosen copy (Dipesan); reject releases availability appropriately. No invented reviewer levels.
10. Realisasi Booking: select a Disetujui booking, display member, book, copy and current statuses; loan date, due date, staff; existing realization action. Only valid approved booking may be realized, converting booking -> Direalisasikan, copy -> Dipinjam and recording mock active loan together.

No penalties, payments, barcode/RFID, integrations, unrelated analytics, or database calls.

Verify all 10 Admin/Petugas screens, consistency test, build and route list. Stop for review.

## PHASE 5 COMMAND - MEMBER SCREENS

Proceed with Phase 5 only. Build these 5 Anggota pages using SRS/UML requirements and shared mock data. Design the FINAL polished layout yourself; no mockup dependency:
1. Dashboard Anggota: own active booking, own active loan and completed history counts only.
2. Cari Buku: search by title, book code or category; Kode Buku, Judul, Penulis, Kategori, Ketersediaan; book details and Kode Eksemplar/Status. Display Tersedia/Dipesan/Dipinjam accurately.
3. Ajukan Booking: selected book/copy, member identity, member code, submission date, and actions supported by SRS/UML. For an active member and eligible available copy only; created booking status must be Diajukan, not Menunggu.
4. Status Booking: show only current demo member's bookings, real status names, and existing available actions such as cancellation where allowed by the documented workflow. Do not invent expiration/queue policy.
5. History Peminjaman: show only current demo member's loan records; ID Peminjaman, Judul Buku, Kode Eksemplar, Tanggal Pinjam, Tanggal Kembali, Status, plus existing detail panel. Aktif rows have no Tanggal Kembali; Selesai rows have completed return date.

Use no real authentication; select the current fictional demo member in a developer-only config or preview route without adding new user-facing login fields. Preserve privacy boundaries in demo data display. Include inline validation and empty states, not a dedicated UI-states page.

Verify screens, consistent mock records, build and routes. Stop for review.

## PHASE 6 COMMAND - FINAL VISUAL QA AND PIXEL-FIDELITY STITCH HANDOFF

Proceed with Phase 6 only. FINALIZE the UI in the Laravel/Vue source code. The website will be the final visual reference, and Stitch must reproduce its appearance, not redesign it. Audit and correct all screens:
- Every page should open by a stable, directly addressable localhost route so I can capture it in Google Stitch.
- All required inputs, field names, menu items, columns, statuses, and buttons match SRS/UML. UI layout and styling are Claude's FINISHED design implemented in Vue, not a rough starting point.
- Test actions with in-memory state only. No persistence/database/API calls.
- Validate member/book/copy/loan/return/booking cross-screen consistency.
- Make sure login is clearly a static role-preview, not a secure authentication system.
- Use professional Bahasa Indonesia; do not expose SRS, UC, FR, TBD, 'usulan', or academic project notes in the UI.
- Check no PHP/Laravel DB access on page load. Routes should work even with no MySQL service running.
- Test responsive behavior; retain all content. Validate desktop 1440x900 at 100% zoom and mobile 390x844 where practical. Preserve deterministic viewport and font rendering for Stitch screenshots.
- Run npm run build and php artisan route:list. If browser capture is available, generate PNG screenshots for EVERY final route at 1440x900 (full-page additional captures if content extends below the viewport), after waiting for fonts and Vue hydration. If unavailable, document manual screenshot instructions and never claim captures were completed.
- For every captured screen, record page title, exact route, viewport, screenshot filename and any required static state; store screenshots under docs/stitch-reference/screenshots/ if possible. Avoid browser UI chrome and ensure no scrolling overlays, tooltips, loading spinners or console errors.
- Freeze visual CSS design tokens and UI copy for the capture; do not alter the source afterward without regenerating all affected screenshots.
- Preserve /hello/echo as smoke-test route until the user requests removal.

Create docs/STATIC_PAGE_URLS.md listing every page URL, page title, role, exact viewport, screenshot filename, purpose, and review status. Create docs/STATIC_IMPLEMENTATION_STATUS.md listing finished/incomplete/failed items. Create docs/stitch-reference/STITCH_HANDOFF.md explaining that the mockups must match the already-final website visually without changing any details. Do not claim any real backend implementation.

Report results and STOP. Do not connect to MySQL or implement production login until instructed.

## Suggested operator workflow

1. In local terminal, enter the existing Laravel project directory.
2. Open Claude Code in that directory and paste the START PROMPT.
3. Review Phase 0 inspection.
4. Paste each phase command in order; inspect the output at every checkpoint.
5. During visual inspection, run PHP and Vite from two terminals if required:

```bash
php artisan serve
npm run dev
```

6. Check the expected base URL (usually http://127.0.0.1:8000) and direct demo routes.
7. Only after all pages have correct content, behavior AND final visual styling, capture every page at the same desktop viewport and bring the URLs/screenshots to Google Stitch. No previous mockups are required.
8. In Stitch, use the screenshot and/or accessible website URL as a STRICT visual reference. Generate an as-close-as-possible MATCHING mockup, not a redesign. Do NOT automatically apply changes from Stitch back to Vue. The Laravel/Vue website is the source of truth.
9. Compare Stitch mockups side-by-side with the original browser screenshots. Check every layout, text, table, card, button, icon, color and spacing. Revise only the Stitch mockup when mismatches arise; if truly pixel-exact output is mandatory, retain the original screenshots because AI recreation may not guarantee exact pixels.


## FINAL WORKFLOW - LOCKED BY USER

**Claude Code creates FINAL static web pages in Laravel/Vue -> User reviews browser-rendered pages -> Capture screenshots + exact URLs -> Google Stitch recreates matching mockups -> compare side-by-side with website screenshots.**

Google Stitch is NOT the design author and is NOT being asked to make the site more modern, reorganize elements, change text, or improve the layout. All redesign language from previous workflow is superseded by this rule.

Source-of-truth hierarchy for the design deliverables:
1. Browser-rendered website screenshots from finalized Laravel/Vue code (visual geometry, typography, colors, position, content).
2. Source code and URL captures (interaction and detailed state reference).
3. SRS/UML (business content, fields, statuses, workflow).
4. Stitch result (derivative mockup, not authoritative).

Quality gate before Stitch: source pages must be final, stable, visually polished, accessible at reproducible URLs, and cross-screen consistent.
