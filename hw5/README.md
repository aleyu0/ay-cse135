# The Absolute Essential — CSE 135 Analytics Platform

## Project Overview

My CSE 135 Project consists for the following pages.

| Site | URL | Purpose |
|------|-----|---------|
| **Test Store** | [test.alessioyu.xyz](https://test.alessioyu.xyz) | Public-facing mock e-commerce store (called "The Absolute Essential") |
| **Collector** | [collector.alessioyu.xyz](https://collector.alessioyu.xyz) | Analytics data collection endpoint |
| **Reporting** | [reporting.alessioyu.xyz](https://reporting.alessioyu.xyz) | Analytics reporting dashboard with role-based access |
| **Main** | [alessioyu.xyz](https://alessioyu.xyz) | Main domain |

**Repository:** [github.com/aleyu0/ay-cse135](https://github.com/aleyu0/ay-cse135)

## Technical Stack

- **Server:** DigitalOcean droplet running Ubuntu 24 with Apache2 and Let's Encrypt TLS
- **Backend:** PHP 8.x
- **Database:** PostgreSQL with JSONB for event payload storage
- **Frontend:** Vanilla JavaScript, Chart.js for data visualization
- **Collector:** Custom JavaScript analytics library (`collector.js`)

## Architecture

### Data Pipeline

1. User visits test site loads collector
2. collector.js captures events
3. sendBeacon POST to collector endpoint
4. log.php loads data into PostgreSQL
5. events.php REST API serves data upon request
6. reporting dashboard renders charts and KPI cards


### Data points

- **Static data:** User agent, screen/window dimensions, language, connection type, device memory, color scheme, applied theme
- **Performance:** Navigation timing (DNS, TCP, TLS, TTFB, DOM events), resource timing grouped by type, total load time
- **Web Vitals:** LCP, CLS, INP via PerformanceObserver with standard scoring (good/needs-improvement/poor)
- **Activity:** Mouse movement (200ms throttled), clicks with selector info, scroll depth, keyboard events, idle detection (2s threshold), page enter/leave
- **Custom Events:** Theme toggles (`ae_theme`), cart actions (`ae_cart` includes add, remove, begin checkout, complete)
- **Errors:** JS runtime errors, resource load failures, unhandled promise rejections (rate-limited to 10 per page)

### Authentication & Authorization

The reporting site has different sections that require different authorizations. There are four roles for users: `super_admin`, `admin`, `analyst`, and `viewer`. 

- **Users table** with bcrypt-hashed passwords
- **Sessions table** with bearer tokens that have 2 hour expiry
- **Permissions table** with various access controls (view-dashboard, view-logs, view-performance, view-behavioral, view-errors, view-users, edit-users, make-users, delete-users, export-reports, manage-comments, etc.)
- **Role-permission mapping** via a join table
- **Analyst scope** for analysts their authorizations to different sections of reports is controlled using a JSONB `allowed_sections` column

Roles: `super_admin`, `admin`, `analyst`, `viewer`

I also added safeguards that prevent admins from accidentally deleting themselves and admins cannot delete superadmins. This is enforced on the server side so that clients cannot modify the script and break the platform. 

### Test Store Features

- Product catalog loaded from `products.json` with theme-aware images (light/dark variants)
- Cart with localStorage persistence, slide-out drawer UI, quantity tracking
- Checkout flow collecting name, email, and PID — stored in an `orders` table
- Contact/procurement request form with server-side validation, IP-based rate limiting (5/hour), and PostgreSQL storage
- All user actions (cart adds, checkout, theme changes) are events captured by the collector
- Shared navigation via `nav.js` (injected via deferred script)
- Custom 404 page via Apache ErrorDocument

### Reporting Dashboard Features

- **Overview:** KPIs (sessions, page views, avg load, errors), events timeline, top pages (with 404 aggregation), browser/connection breakdowns
- **Event Log:** Paginated table with expandable detail rows, filterable by type/session/date range, formatted timestamps
- **Speed & Vitals:** Median LCP/CLS/INP with color-coded scoring, load time distribution histogram, vitals score stacked bar, per-page performance table
- **Errors:** Grouped by type/message with occurrence counts, affected sessions, expandable detail rows with stack traces
- **Customers & Behavior:** Purchase funnel (visitors → cart → checkout → purchase), orders over time, top products, device breakdown, orders table with expandable details, session profiles linking browsing to purchase behavior
- **User Administration:** Full CRUD for users, role assignment with analyst section scoping, self-service password change for all users
- **Report Generation:** Print-optimized report page with analyst commentary, auto-saving comments, per-source permission enforcement, data labels on charts

All pages support server-side date range filtering via API query parameters, with date range persistence across pages via sessionStorage.

## AI Usage Disclosure

Claude.ai, ChatGPT, and Gemini were used for parts of this development. Some common uses include debugging, UI fixes, test site blurb generation, chart fixes, JSON file generation (ie. products), and test plan generation. 

AI was less useful when it came to understanding the specific Apache/ModSecurity configuration on the droplet and occasionally generated code with subtle bugs (duplicate variable declarations, missing chart creation calls) that required manual debugging.

## Roadmap / Future Improvements

If I had more time, I would continue to implement and improve these features:
- Export event log for selected time frame
- Heatmap visualization of the webpage using mouse movemet and click data
- Session replays that follow user sessions
- Report comments saved in a database for reuse or reviewing
- Customizable reports so that analyst can choose which data to visualize/include in their reports
- Potentially implement an A/B testing system
- Dark mode for reporting site because developing this has given me eye strain
- Test site to include customer account creation, log in, and more data
- Test site to ask for permissions to collect data