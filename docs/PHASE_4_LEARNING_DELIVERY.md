# Phase 4 — Unified course delivery

## Status

Scope and implementation contract. Build work must preserve the existing PHP 8.2+ / SQLite runtime, the single `index.php` front controller, the current identity and payment services, and the configured `APP_URL` as the only canonical application URL source.

Phase 4 follows the commerce milestone. It must not bypass or weaken checkout, course entitlements, legal acceptance, existing self-paced learning, or payment verification.

## Outcome

A learner can discover, purchase or enrol in a course and continue learning through the delivery mode selected by the course:

1. **Self-paced in-app** — existing chapters, lessons, protected video/resources, bookmarks, progress, quizzes, grading and attempt history.
2. **Live online** — scheduled instructor-led sessions delivered through Zoom or Google Meet. Session access is restricted to eligible enrolled learners; meeting credentials are never exposed in public course listings.
3. **In-person** — scheduled classroom sessions with a venue, capacity, learner roster and attendance tracking.

A course may offer one delivery mode. If a future course needs multiple modes, model those as separate offerings linked to one course rather than mixing incompatible enrolment rules.

## Learner experience

- Course catalogue and detail pages clearly identify the delivery mode, schedule expectations, location or online platform, price and availability.
- Self-paced learners continue from their saved lesson position and see course completion and assessment status.
- Live learners see upcoming sessions in their local time, receive a protected join action, and can access the meeting only while their enrolment is active.
- In-person learners see the venue and session schedule after enrolment, plus attendance and completion status.
- Learners can view upcoming, completed and cancelled sessions and their attendance history.
- Assessment results and progress are shown in one learner dashboard, not separate disconnected portals.

## Administration

- Create and edit course offerings and delivery modes.
- Schedule, reschedule and cancel sessions; store timezone explicitly and render dates in the learner's local timezone.
- For live sessions, store platform (Zoom or Google Meet), join URL and optional host-only details. Do not put host links or credentials in public HTML, catalogue JSON, logs or emails.
- For in-person sessions, store venue name, address, optional directions and capacity.
- Manage rosters, waitlists where enabled, attendance, late/absent/excused status, and attendance corrections with an audit trail.
- Prevent overbooking and duplicate enrolments using database constraints and transactional checks.
- Preserve completed attendance and assessment history when a session is rescheduled or cancelled.

## Data and service boundaries

Use additive SQLite migrations. Proposed concepts:

- `course_offerings`: course, delivery mode, status, capacity and mode-specific public details.
- `course_sessions`: offering, start/end UTC timestamps, IANA timezone, status, venue or online platform metadata.
- `session_enrolments`: learner/session reservation and state; unique learner/session constraint.
- `session_attendance`: attendance status, recorded-by actor, timestamp and correction reason.
- `learning_audit_events`: append-only administrative changes to schedules, rosters and attendance where existing audit tables do not already cover them.

Do not duplicate the current course purchase entitlement model. A verified paid order activates course access; free enrolment remains supported. Session booking must validate that entitlement and enforce capacity in one transaction.

## Security and reliability

- All mutations require authentication, role/permission checks, CSRF protection, input validation and ownership checks.
- Meeting URLs are private data. Return them only to the authenticated enrolled learner for the relevant upcoming session.
- Do not trust browser-submitted course prices, session capacities, attendance values or user IDs.
- Use UTC for stored timestamps and explicit IANA timezones for display and daylight-saving transitions.
- Make session creation, enrolment and attendance updates transactional and idempotent where retries are possible.
- Keep all internal routes and asset URLs generated through `App\\Core\\Url`; absolute callbacks and links must derive from `APP_URL`. Never hardcode a hostname or installation path.
- Maintain compatibility with shared hosting and SQLite; no Node runtime, Composer runtime, Redis, or separate database server.

## Acceptance criteria

- Fresh-database migrations and upgrades from the current schema both succeed.
- Existing self-paced courses, quizzes, progress, bookmarks, certificates and payment enrolments continue to work.
- Live and in-person course offerings can be created, published, scheduled and purchased/enrolled.
- Unenrolled users cannot retrieve session links, rosters or attendance.
- Concurrent enrolment attempts cannot exceed session capacity.
- Rescheduling/cancellation updates learner views and preserves audit history.
- Attendance can be recorded and corrected only by authorized staff, with a traceable reason.
- All relevant routes pass with root and subdirectory `APP_URL` values.
- PHP 8.2 and 8.3 CI, SQLite integration tests, security tests and page smoke tests pass.

## Delivery sequence

1. Inspect and map the existing course, enrolment, payment, quiz and dashboard services before changing schema.
2. Add migrations and service-level tests for offerings, sessions, capacity and attendance.
3. Add administrator workflows and permissions.
4. Add learner catalogue, schedule, booking and attendance views.
5. Integrate protected Zoom/Google Meet access and notifications.
6. Run regression, security, routing and production-hosting validation before release.

Do not implement video conferencing APIs or automatically create third-party meetings in the first delivery. Phase 4 initially manages trusted meeting URLs entered by an authorized administrator; API automation can be added later without changing the learner entitlement model.
