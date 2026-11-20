# SmartStudyPro UI/UX continuation

## Design authority

Follow the research-backed direction in [SmartStudyPro Design Direction](https://github.com/Enoch7768/smartbase/blob/main/docs/DESIGN_DIRECTION.md). It is the source of truth for the visual language, experience-specific layouts, accessibility and implementation sequence.

Use expressive clarity: a memorable navy/orange education brand, real course and product imagery, editorial storytelling, varied page composition and purposeful motion. Avoid generic SaaS/AI patterns, repeated card grids, gradient-only heroes, pervasive glass effects and decorative motion.

Shared tokens and controls should be consistent, but public marketing, course discovery, commerce, learner workspace, study portal, admin and page builder must have distinct information architecture and visual composition.

## Completed in this pass

- Rebuilt the SmartStudyProFin public homepage with an editorial split hero using the published CMS hero image, a learning-story introduction, distinct self-paced/live-online/in-person pathways, published course previews and a published product shelf.
- Course previews show the available published offering modes and the lowest published offering price rather than inventing delivery options.
- Product previews distinguish physical products from online/digital products and show separate read/view and download prices for digital items.
- Reused existing clean routes and CMS services; no mock product/course records or fabricated metrics were added.
- Escaped CMS hero title and subtitle before rendering.
- Added responsive layouts, dark-theme styling, reduced-motion handling and accessible text/button contrast for the new homepage styles.
- Removed the unintegrated first draft styles and scroll-reveal script before wiring the final homepage implementation.

## Verification status

- Source-level checks confirm the homepage stylesheet is linked, the page uses published CMS courses and products, online product records are presented as digital, and the separate digital access prices are rendered.
- The homepage no longer loads Google Fonts or AOS assets.
- The project still uses its existing locally shipped Bootstrap assets and Bootstrap Icons because the current navigation and other pages depend on them.
- PHP lint, isolated SQLite HTTP smoke tests, browser screenshots, keyboard-only review, screen-reader review and manual contrast sampling have not been run in this environment. Do not describe the redesign as browser-verified.

## Next implementation sequence

1. Audit and redesign the shared public header/navigation and footer, including compact/mobile navigation, clear active states, useful account/cart access and removal of placeholder links.
2. Redesign About, Contact and Booking pages with editorial storytelling and task-first forms.
3. Redesign the course catalogue with search, filters, sorting, result counts and removable active filters while preserving query behavior.
4. Redesign course details around outcomes, instructor information, curriculum, published offering modes, schedules and a clear enrol action.
5. Redesign the product catalogue and product details around digital versus physical decisions, previews, read/download pricing, stock and fulfilment information.
6. Refine cart, checkout, payment states and order tracking.
7. Refine login, registration, recovery, learner dashboard, lesson player and study workspace.
8. Refine administrator and page-builder experiences using their own information density and interaction patterns.
9. Run PHP lint and the full project test suite locally, then inspect screenshots at compact, medium, expanded and wide widths; verify keyboard, reduced-motion, light/dark and WCAG 2.2 AA states.

Keep each batch small, preserve PHP/SQLite behavior and existing permissions, record changes in CHANGELOG.md, and update this document with completed work and the next concrete step.
