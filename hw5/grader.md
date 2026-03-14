# GRADER.md

## Login Credentials

| Role | Username | Password | What they can see |
|------|----------|----------|-------------------|
| Super Admin | `superadmin` | `super135!` | Everything, including user deletion |
| Admin | `admin` | `admin135` | Everything except deleting users |
| Analyst | `sam` | `analyst135` | Overview, Speed & Vitals, Errors only |
| Analyst | `sally` | `analyst135` | Overview, Speed & Vitals, Customers only |
| Viewer | `grader` | `grader135` | Overview, Saved Reports, and password change |

## Links

- Test Site: https://test.alessioyu.xyz
- Reporting Dashboard: https://reporting.alessioyu.xyz
- Collector: https://collector.alessioyu.xyz
- Repository: https://github.com/aleyu0/ay-cse135

## Walkthrough

Here's a scenario that demonstrates the full system.

### Test site

1. Go to https://test.alessioyu.xyz and browse around the website
   - Try the theme toggle (top right icon) to switch between light and dark mode this is tracked by the collector
2. Go to the Shop page and click on a product card to see the detail modal
3. Add 2-3 items to your cart using either the card button or the modal
4. Click the cart icon (top right) to open the cart drawer
5. Fill in a name, email, and PID (e.g. A12345678) then click Checkout
6. You should see a success message with an order number
7. Go to the Request page and submit a procurement request — fill in all fields and hit Submit
8. You should see a green success message. If you submit more than 5 times in an hour, rate limiting kicks in

### Reporting dashboard — super admin

1. Go to https://reporting.alessioyu.xyz and log in as `superadmin` / `super135!`
2. The Overview page shows KPI cards, an events timeline, top pages, and browser/connection charts
3. Try changing the date range and clicking Apply — the data should refresh
4. Go to Event Log — you can see raw events with formatted timestamps, filter by type or session, and click rows to expand full details
5. Go to Speed & Vitals — median LCP, CLS, INP are shown with color-coded scores. The page performance table at the bottom aggregates 404 traffic separately
6. Go to Errors — errors are grouped by type and message. Click a row to see the full message, stack trace, and affected sessions
7. Go to Customers — this shows the purchase funnel, orders over time, top products, device breakdown, and session profiles. The order you placed in step 6 should show up here
8. On any of these pages, click Generate Report to open a print-optimized report page
9. Write something in the Analyst Commentary box — it auto-saves
10. Click Save Report to store a snapshot. You can also click Print / Save as PDF to export via the browser
11. Go to Users to see the full user table. You can add users, edit roles, assign analyst sections, and change passwords. Try editing one of the analysts' allowed sections

### Role-based access

1. Log out and log in as `sam` / `analyst135`
2. The sidebar should only show Overview, Speed & Vitals, Errors, and My Account
3. Sam cannot see Customers or Users pages — try navigating to https://reporting.alessioyu.xyz/customers.php directly and you should get a 403 page
4. Sam can generate and save reports for the sections he has access to
5. Try accessing a report URL for a section Sam doesn't have: https://reporting.alessioyu.xyz/report.php?source=customers&from=2026-01-01&to=2026-12-31 — should also 403
6. Log out and log in as `grader` / `grader135`
7. The grader can see Overview and Saved Reports only
8. Go to Reports — you should see any reports that were saved in step 9 above
9. Click Open to view the full saved report in a new tab with print/download capability
10. The grader can also access My Account to change their own password

## Known Issues

I want to be upfront about the things I know aren't perfect.

1. Analyst comments are saved as plain text files in `site-reporting/data/`. It works but a database-backed system with versioning would be better. The directory requires `www-data` ownership to be writable.

2. Report generation relies on the browser's print-to-PDF. Server-side PDF generation with something like wkhtmltopdf would produce more consistent output across browsers.

3. The session token system creates tokens in a `sessions` table but the actual authentication check still uses PHP's `$_SESSION['authenticated']`. The DB token is created but not validated on every request. This could be tightened up.

4. The top pages chart and speed table use a hardcoded `knownPages` array to separate real pages from 404 traffic. If I add new pages to the test site, I'd need to update this list in dashboard.php and speed.php.

5. The collector running on a different subdomain produces some generic "Script error." entries due to CORS restrictions on error details. Adding `crossorigin="anonymous"` to the script tag and ACAO headers on the JS file would fix this.

6. The collector sends activity events every 2 seconds which generates a lot of data. For a real production system you'd want sampling or server-side aggregation.

7. Events near midnight can sometimes appear on the wrong day due to timezone differences between the client and the server. I set the timezone on order queries but not on all event queries.

8. The saved reports store chart images as base64 in a JSONB column which can get large. For a production system these would be stored as files or in object storage.