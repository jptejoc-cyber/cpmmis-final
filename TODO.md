# CPMMIS Responsive Rollout — TODO

> Backup of the pre-change project:
> `C:\Users\PC\AppData\Local\Temp\opencode\cpmmis_backup_before_responsive`

## Phase 0 — Baseline & safety
- [x] Inventory all admin, adviser, and group pages
- [x] Record findings and breakpoint plan
- [x] Create full project backup (excluding uploads)
- [x] Manual browser pass at 320/360/390/768/1024/1440 after implementation

## Phase 1 — Shared responsive foundation
- [x] Create `assets/css/responsive.css`
- [x] Create `assets/js/responsive.js`
- [x] Add mobile form sizing, typography, spacing, radii, safe-area and reduced-motion rules
- [x] Add sidebar drawer behaviour below 1024px
- [x] Add generic mobile card layout for wide tables (auto-labelled from headers)
- [x] Add scrollable-table affordance
- [x] Add modal bottom-sheet/scroll handling and background scroll lock
- [x] Add Escape-key handling for drawer/popovers
- [x] Include shared assets on every UI page

## Phase 2 — Global shell fixes
- [x] Sidebar: drawer up to 1024px, rail on desktop, scrollable nav, touch hover fix
- [x] Header/hamburger: reserve space, wrap actions, no overlap
- [x] Popovers: fixed mobile sheets within viewport
- [x] Modals: max-height + internal scroll everywhere
- [x] Tables: wrappers, min-widths, card mode for wide tables
- [x] Forms: 16px mobile controls, stacked filter rows
- [x] Typography/spacing: mobile readability and card padding
- [x] Chat widgets: full-width mobile bottom sheet at 320px
- [x] Charts: mobile sizing and options

## Phase 3 — Page-by-page fixes
### Admin
- [x] `admin/admin_dashboard.php` — popovers, progress modal, padding
- [x] `admin/advisers.php` — add-adviser modal scroll, form stacking, table
- [x] `admin/archive.php` — filter wrapping, table wrapper, remove unused Chart.js
- [x] `admin/fetch_archive_live.php` — responsive cell padding
- [x] `admin/groups.php` — wide table/card behaviour
- [x] `admin/submissions.php` — table/modal polish
- [x] `admin/reports.php` — wrapping toolbar, mobile charts, padding
- [x] `admin/maintenance.php` — padding/typography/modal polish
- [x] `admin/group_stats.php` — remove dead hamburger, wrap header cluster
- [x] `admin/chat_widget.php` — mobile sheet and 16px input

### Adviser
- [x] `adviser/adviser_dashboard.php` — popovers/modal/table polish
- [x] `adviser/groups.php` — table/card behaviour
- [x] `adviser/group_requests.php` — card/tap-target polish
- [x] `adviser/meetings.php` — table/modals/mobile spacing
- [x] `adviser/submissions.php` — table/modals
- [x] `adviser/archive_view.php` — filter wrapping
- [x] `adviser/view_group.php` — header wrap, responsive padding, tables/modals
- [x] `adviser/task_submissions.php` — responsive page/cells/header
- [x] `adviser/chat_widget.php` — mobile sheet and 16px input

### Group / Student
- [x] `group/group_dashboard.php` — hero/pending-state padding and progress circle
- [x] `group/submissions.php` — header cluster, modals
- [x] `group/meetings.php` — stack hero, mobile spacing
- [x] `group/letters.php` — viewer modal/mobile spacing
- [x] `group/advisers.php` — table/card/request modal
- [x] `group/archive_view.php` — filter wrapping
- [x] `group/pending_screen.php` — remove overflow lock, compact padding
- [x] `group/chat_widget.php` — mobile sheet and 16px input

### Shared / Login
- [x] `index.php` — form first on mobile, compact stats, input sizing
- [x] `components/custom_confirm.php` — verify mobile sheet behaviour

## Phase 4 — Performance & smoothness
- [x] Remove unused Chart.js from `admin/archive.php`
- [x] Add font preconnects
- [x] Defer/avoid unnecessary work where safe
- [x] Tune Chart.js options for mobile
- [x] Verify reduced-motion and touch-hover behaviour
- [x] Keep Tailwind Play CDN (no build pipeline) to avoid breaking runtime classes

## Phase 5 — Verification
- [x] `php -l` on every modified PHP file
- [x] Confirm no JS console errors on shell pages
- [x] Confirm sidebar/modals/filters/chat still work
- [x] Confirm desktop 1280/1440 appearance is unchanged
- [x] Confirm no unintended horizontal page scroll at 320px
- [x] Confirm all modals reachable at 320x568

## Verification notes
- Manual pass: representative flows at 320, 360, 390, 768, 1024, 1280 and 1440; every Admin, Adviser and Group page was audited for page overflow at 320 and 768.
- Desktop 1280/1440 stays on the persistent icon rail with normal tables. At 1024 the stat-card labels are allowed to wrap so they no longer ellipsize.
- Table cards now apply to tables with 3 or more headers below 1024px; wide tables still scroll inside their own wrapper at 1024+.
- `admin/archive.php` has a harmless 1px `scrollWidth` overshoot at 320px; body overflow is hidden and nothing is clipped.
- No responsive JavaScript exceptions were found. Two pre-existing CSP console notices come from the lazy PDF `<embed>` elements in `adviser/archive_view.php` and `group/letters.php` (`object-src 'none'`); they are unrelated to the responsive layer.
- Custom confirm, add-adviser modal, chat contact window, chat bar, notification popover and settings drawer were exercised at 320x568 with background scroll locking and internal scrolling.
- Chat polling fix (Admin, Adviser, Group): each poll now compares a message-key signature and skips untouched feeds, so bubbles no longer replay their entrance animation or move every 3 seconds; only genuinely new messages animate, and the feed keeps the reader's scroll position unless they were already at the bottom. Group chat also stopped rendering queued/offline pending messages twice.
- Chat verification at 390x844: Admin and Adviser feeds showed identical bubble positions across two 3-second polls, `scrollTop` stayed at 0 while scrolled to the top, and a synthetic request-level new message produced exactly one animated bubble while every existing bubble kept its position. Group chat rendered a simulated offline pending message exactly once and removed it after the queue was cleared. No console errors; `php -l` passed on all three chat widget files.
