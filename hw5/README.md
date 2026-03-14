# CSE 135 Final Project
Alessio Yu (A17474233)

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

- Static data
  - User agent
  - Screen/window dimensions
  - Language
  - Connection type
  - Device memory
  - Color scheme
  - Applied theme
- Performance
  - Navigation timing (DNS, TCP, TLS, TTFB, DOM events)
  - Resource timing grouped by type
  - Total load time
- Web Vitals:
  - LCP
  - CLS
  - INP via PerformanceObserver with standard scoring (good/needs-improvement/poor)
- Activity
  - Mouse movement
  - Clicks with selector info
  - Scroll depth, keyboard events
  - Idle detection (2s threshold)
  - Page enter/leave/hide
- Other Events
  - Theme toggles
  - cart actions (add, remove, begin checkout, complete)
- Errors
  - JS runtime errors
  - Resource load failures
  - Unhandled promise rejections

### Authentication & Authorization

The reporting site has different sections that require different authorizations. There are four roles for users: `super_admin`, `admin`, `analyst`, and `viewer`.

| Permission | super_admin | admin | analyst | viewer |
|--|:--:|:--:|:--:|:--:|
| view-dashboard | ✓ | ✓ | ✓ | ✓ |
| view-logs | ✓ | ✓ | scoped | ✗ |
| view-performance | ✓ | ✓ | scoped | ✗ |
| view-behavioral | ✓ | ✓ | scoped | ✗ |
| view-errors | ✓ | ✓ | scoped | ✗ |
| view-users | ✓ | ✓ | ✗ | ✗ |
| edit-users | ✓ | ✓ | ✗ | ✗ |
| make-users | ✓ | ✓ | ✗ | ✗ |
| delete-users | ✓ | ✗ | ✗ | ✗ |
| export-reports | ✓ | ✓ | ✓ | ✗ |
| create-reports (save) | ✓ | ✓ | ✓ | ✗ |
| view-reports (browse saved) | ✓ | ✓ | ✓ | ✓ |
| change own password | ✓ | ✓ | ✓ | ✓ |

Analyst roles have scoped permissions meaning that we can toggle on and off different sections depending on the analyst's focus. The authorized sections is stored in `allowed_sections` JSONB on the database and enforced on the server to ensure that the user only has access to what has been granted to them.

User passwords are bcrypt-hashed so that they cannot be reversed. If a user needs their passwords changed, they can change it themselves or ask an admin to do it for them. 

I also added safeguards that prevent admins from accidentally deleting themselves and admins cannot delete superadmins. This is also enforced on the server side so that clients cannot modify the script and break the platform. 

### Test Site Features

- Product catalog loaded from `products.json` with theme-aware images (light/dark variants)
- Cart with localStorage persistence, slide-out drawer UI, quantity tracking
- Checkout flow collecting name, email, and PID — stored in an `orders` table
- Contact/procurement request form with server-side validation, IP-based rate limiting (5/hour), and PostgreSQL storage
- All user actions (cart adds, checkout, theme changes) are events captured by the collector
- Shared navigation via `nav.js` (injected via deferred script)
- Custom 404 page via Apache ErrorDocument

### Reporting Dashboard Features

- 5 reporting pages including `Overview`, `Event log`, `Speed and vitals`, `Errors`, `Customers`
- Top pages statistics will exclude incorrect links entered by users and are grouped into 404 page
- Date range filter is persistent across all pages by saving it in `sessionStorage` so if an analyst selects the date, they don't have to reenter it when they go to another report.
- Overview page shows critical KPIs and general information about user connections
- Event log shows a table with summary of the events, they can be expanded to view the full logged item
- Errors page group the type of error with occurence counts. They show which users were affected and allows me to trace and fix.
- Customer behavior page shows the purchasing funnel which we can analyze the amount of people who go from a visitor, to cart, to checkout, and then finalizing the transaction. It also shows charts about user session profiles and orders. 
- User admin which allows admins to perform CRUD operations for users, and role assignments. All users can access this page to change their own password. 
- Users with analyst role or higher can generate reports from dashboards, add commentary, and then save or export the report
- Users with viewer role can read all report.

## AI Usage

For this project, I used Claude.ai, ChatGPT, and Gemini for some parts of the development. AI tools proved useful when I was debugging, generating random documents such as the products JSON file, writing test site blurbs, fixing UI components, and generating test plans.

I used AI less when it came to understand the Apache or ModSecurity configurations as it occasionally came up with bugs. There were also some parts where I felt it more efficient to complete myself rather than writing a prompt and trial and erroring the solutions given by the LLM. 

## Roadmap / Future Improvements

If I had more time, I would continue to implement and improve these features:
- 404 page redirects should still have a list of links that users are going to so that we can identify what is causing users to end up on 404 pages
- Export event log for selected time frame
- Heatmap visualization of the webpage using mouse movemet and click data
- Session replays that follow user sessions
- Show products that are viewed the most (even if they weren't added to the cart)
- Report comment editable after saving
- Customizable reports so that analyst can choose which data to visualize/include in their reports
- Potentially implement an A/B testing system
- Dark mode for reporting site because developing this has given me eye strain
- Test site to include customer account creation, log in, and more data
- Test site to ask for permissions to collect data
- Prompt user to change their passwords if not set by the user themselves