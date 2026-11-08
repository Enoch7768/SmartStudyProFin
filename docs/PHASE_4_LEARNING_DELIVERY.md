# Phase 4 — Unified learning, meeting planner and AI companion

## Contract

Preserve PHP 8.2+, SQLite, shared-hosting compatibility, the single `index.php` front controller, existing identity/payment/learning services, and `APP_URL` as the sole canonical URL source. Use additive migrations. Commerce requirements are separately specified in `docs/COMMERCE_REQUIREMENTS.md`.

## Course delivery modes

Every course offering has an explicit mode:

1. **Self-paced in-app:** lessons, chapters, protected video/audio/documents, bookmarks, saved position, progress, quizzes, grading, attempts and certificates.
2. **Live online:** scheduled instructor-led Zoom or Google Meet sessions managed by SmartStudyPro's own meeting planner.
3. **In-person:** scheduled classroom sessions with venue, capacity, booking, roster and attendance.

A course may have separate offerings for different modes. Keep one entitlement and account system. Verified payment activates course access; free enrolment remains supported.

## Learner experience

- Catalogue/detail pages clearly show mode, price, schedule, timezone, location/platform, capacity and prerequisites.
- Self-paced learners resume lessons and see progress/results.
- Live learners see upcoming/completed/cancelled sessions and a protected join action.
- In-person learners see venue, schedule, booking and attendance history.
- All learning modes appear in one learner dashboard.
- Only eligible enrolled learners can retrieve meeting join details.

## SmartStudyPro meeting planner

SmartStudyPro is the source of truth for meeting schedules and course session records; Zoom and Google Meet are meeting providers.

- Authorized staff create meetings linked to a course offering, choose Zoom or Google Meet, set title, agenda, host, start/end time, IANA timezone, capacity, registration rules and reminders.
- Store provider, provider meeting ID, learner join URL and host URL separately. Host URLs, credentials and provider tokens are never learner-visible.
- Support draft, scheduled, rescheduled, cancelled, completed and archived states.
- Detect host scheduling conflicts; enforce capacity and duplicate booking prevention transactionally.
- Provide calendar/list, agenda, session detail, roster and attendance views.
- Send invitations, reminders, changes and cancellations through existing notifications/email.
- Store UTC timestamps and IANA timezone; render in the learner's timezone.
- First delivery may use trusted administrator-entered meeting links. Later Zoom/Google Meet API creation/synchronization requires secure OAuth, webhook verification, permissions and recovery handling.
- No provider integration credentials in browser code, public JSON, logs or learner notifications.

## AI meeting companion — Groq

Groq is the provider for the meeting companion. Do not silently replace the existing Gemini-backed, course-grounded learning tutor.

Initial capabilities:
- Convert instructor notes and course objectives into agendas with time allocations.
- Suggest discussion questions, activities, learning outcomes and preparation material grounded in selected course content.
- Draft learner announcements, follow-up tasks and structured meeting notes.
- Summarize instructor-provided transcript excerpts only after the instructor confirms appropriate rights and participant notice/consent.

The first delivery does **not** join, listen to, record or transcribe Zoom/Meet calls. Live transcription/recording requires a separate explicit consent and retention design. AI-generated content is a draft requiring instructor review before becoming official minutes or being sent to learners.

### Groq and token limits

- Keep `GROQ_API_KEY` server-side only. Configure `GROQ_MODEL`, timeout, input/output token budgets, per-user quotas and an application usage budget through environment/private configuration.
- Use Groq's OpenAI-compatible Chat Completions API over PHP cURL; no Node or Composer runtime.
- Model context windows, completion caps, model availability and account quotas are variable. Query model metadata or use administrator-reviewed configuration; never hardcode assumed limits.
- Estimate input tokens conservatively before sending. Reserve output tokens; fit system instructions, selected course excerpts and recent history inside the configured context window. Compact/truncate oldest context first.
- Set `max_completion_tokens` or the currently supported equivalent explicitly.
- Enforce per-user and per-meeting request/token quotas in SQLite so one user cannot exhaust the shared organization quota.
- Respect RPM, RPD, TPM, TPD and any separate input/output limits. Inspect rate-limit headers; handle 429 and `Retry-After` with bounded exponential backoff and jitter. Never create retry storms.
- Record model, usage counts when returned, latency, request ID and outcome without logging secrets or unnecessary meeting content.
- Provide an admin kill switch, graceful unavailable/quota-exceeded response, and clear retry-later UI.
- Never expose raw provider payloads or API keys.

Official references: [Groq rate limits](https://console.groq.com/docs/rate-limits), [API reference](https://console.groq.com/docs/api-reference), [supported models](https://console.groq.com/docs/models), [API errors](https://console.groq.com/docs/errors).

## In-person sessions

Store venue name/address, directions/accessibility notes, capacity and contact instructions. Track present, late, absent, excused and pending. Only authorized staff can record/correct attendance; every correction stores actor, time and reason. Learners cannot see other learners' private roster details.

## Data boundaries

Inspect existing schema first and add migrations only where needed. Expected concepts: `course_offerings`, `course_sessions`, `session_enrolments`, `session_attendance`, meeting AI usage ledger, and append-only learning audit events. Reuse existing course entitlements, notifications, identity, AI tutor and audit infrastructure. Separate host/provider secrets from learner-visible session fields.

## Security and reliability

Authentication, role/permission checks, CSRF, ownership validation, transactions and idempotency are required. Never trust browser-submitted prices, capacity, attendance, user IDs, provider IDs or token counts. Treat retrieved lesson content and meeting notes as untrusted prompt-injection material. Use UTC and IANA timezone identifiers. All routes/assets use `App\\Core\\Url`; absolute links derive from `APP_URL`. Keep compatibility with cPanel/shared hosting and SQLite.

## Acceptance criteria

- Fresh and upgrade migrations succeed.
- Existing self-paced courses, progress, bookmarks, quizzes, certificates, payments and notifications remain functional.
- Admins can create all three offering modes and schedule live/in-person sessions.
- Unenrolled users cannot retrieve meeting links, rosters or attendance.
- Concurrent bookings cannot exceed capacity; duplicate bookings are prevented.
- Reschedules/cancellations update learner views and retain audit history.
- Attendance corrections are authorized and traceable.
- Planner works with manually entered provider links if provider API credentials are absent.
- Groq requests enforce context/output budgets, quotas, rate-limit handling, timeout and safe fallback.
- No recording/transcription occurs without an explicit consent workflow.
- Routes work with root and subdirectory `APP_URL`; PHP 8.2/8.3, SQLite, security and smoke tests pass.

## Delivered implementation

- Added additive SQLite migrations for course offerings, live/in-person sessions, enrolments, attendance, meeting AI usage and idempotent reminder tracking.
- Added administrator workflows for offering creation, meeting scheduling, provider link validation, host conflict detection, roster/attendance, rescheduling, cancellation, completion and archive.
- Added learner session booking, capacity enforcement, protected join links, venue details and attendance history.
- Added in-app notifications for new sessions, bookings, reschedules and cancellations, plus a CLI reminder job for 24-hour and one-hour reminders.
- Added the Groq meeting companion with private server-side API key, configurable model and token budgets, per-user daily limits, usage logging, bounded rate-limit retry, timeout, safe fallback and environment kill switch. Gemini course tutor remains separate.
- Added PHP 8.2/8.3 tests for migrations, provider link validation, protected links, bookings, attendance, lifecycle actions, reminder idempotency and the AI kill switch.

Provider API automation is deliberately deferred: the first release uses administrator-entered Zoom/Google Meet links. It does not join, record, listen to or transcribe meetings. Email delivery depends on the separately configured application mail transport; reminders in this implementation are in-app notifications.

## Delivery sequence

1. Map existing course, enrolment, payment, quiz, notification, media and AI services.
2. Add offering/session/booking/attendance migrations and service tests.
3. Build admin course-mode and meeting-planner workflows.
4. Build learner schedule, booking, protected join and attendance views.
5. Add Groq meeting companion with token budgets, quotas, usage audit and graceful degradation.
6. Add provider API automation only after OAuth/webhook security design.
7. Run migration, regression, security, routing and shared-hosting validation.
