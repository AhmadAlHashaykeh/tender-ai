# Frontend Structure

This project is still a static frontend. The refactor prepares it for a later Laravel Blade migration without adding backend logic or changing the UI design.

## Folder Structure

```text
assets/
  css/
    app.css
    base/
    layout/
    components/
    pages/
  js/
    app.js
    core/
    modules/
    pages/
  images/
  icons/
css/        # legacy source kept for compatibility/reference
js/         # legacy source kept for compatibility/reference
pages/      # static HTML screens
index.html  # landing page
docs/
```

## CSS Folders

- `assets/css/app.css` is the main CSS entry for authenticated pages.
- `assets/css/base/` contains variables, reset rules, and global utilities.
- `assets/css/layout/` contains app shell, sidebar, topbar, and user/profile shell styles.
- `assets/css/components/` contains reusable UI patterns such as cards, buttons, forms, tables, modals, badges, tabs, and charts.
- `assets/css/pages/` contains page/domain styles for dashboard, upload, management, companies, tenders, drugs, reports, settings, login, and landing.

The old `css/` directory is preserved as a reference while pages now load files from `assets/css/`.

## JavaScript Folders

- `assets/js/app.js` is the shared static entry. It initializes Lucide icons, sidebar behavior, profile dropdowns, logout alerts, date pickers, and shared chart helpers.
- `assets/js/core/` is reserved for future extraction of shared app behavior.
- `assets/js/modules/` is reserved for reusable helpers such as charts, filters, modals, and forms.
- `assets/js/pages/` contains page-specific scripts and should be loaded only by the matching page.

The old `js/` directory is preserved as a reference while pages now load files from `assets/js/`.

## Adding a New Page

1. Create the HTML file under `pages/`.
2. Load `../assets/css/app.css` after external libraries in the `<head>`.
3. Use the authenticated shell structure: `app-layout`, sidebar, `main-content`, topbar, and page content.
4. Load `../assets/js/app.js` before `</body>`.
5. Add a page-specific script only if the page needs behavior.

## Adding Page-Specific CSS

1. Add or extend a file under `assets/css/pages/`.
2. Import it from `assets/css/app.css` after shared component styles.
3. Keep selectors scoped to a page wrapper when possible.

## Adding Page-Specific JS

1. Add a file under `assets/js/pages/`.
2. Guard DOM lookups so the script safely does nothing if expected elements are missing.
3. Include it only on the matching HTML page after `assets/js/app.js`.

## Laravel Migration Notes

Future Blade candidates:

- `layouts/app.blade.php`: authenticated app shell.
- `layouts/guest.blade.php`: landing/login shell.
- `partials/sidebar.blade.php`: sidebar navigation.
- `partials/topbar.blade.php`: topbar and global search.
- `components/page-header.blade.php`: page title block.
- `components/stat-card.blade.php`: KPI cards.
- `components/filter-panel.blade.php`: search/filter panels.
- `components/table.blade.php`: repeated data tables.
- `components/modal.blade.php`: modal shell.
- `components/badge.blade.php`: status/category/risk badges.
- `components/chart-card.blade.php`: chart cards and report widgets.

Internal `.html` links should later become named routes, for example `route('dashboard')`, `route('tenders.index')`, `route('drugs.show')`, and `route('reports.market')`.

## Important Constraints

- The project remains static.
- No Blade files or Laravel controllers were introduced.
- Backend APIs were not invented.
- Legacy files were kept to reduce migration risk.
