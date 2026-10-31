# Notifications, achievements, certificates and analytics

Phase 7 adds a native PHP and SQLite recognition layer to SmartStudyPro.

## Student experience

- Notifications are stored per account, support unread counts, mark-one-read and mark-all-read, and use unique event keys to prevent duplicate milestone notices.
- Achievements are awarded from persisted learning activity: completed lessons, passed quizzes and completed courses. The browser cannot submit an achievement claim.
- Certificates are issued once per student and course after the course enrolment reaches the completed state. Each certificate receives a cryptographically random 24-character hexadecimal verification code.
- The public verification page exposes only the learner name, course title, issue date and verification code. It does not expose email addresses, account IDs, grades or other learner records.

## Administrator analytics

The administrator-only analytics page reports aggregate student, enrolment, completion, certificate, quiz and paid-revenue totals, plus course outcomes and recent completions. It is protected by the existing administrator role check.

## Database and compatibility

Migration 008 creates notification, achievement, earned-achievement and certificate tables with ownership constraints, unique event/certificate keys and query indexes. The application remains PHP 8.1+ and SQLite based, with no background worker or third-party service required.

The recognition service synchronizes persisted activity when a student opens the learning portal, study page, achievements or certificates page. This also backfills certificates for courses already completed before this migration was installed.

## Validation

Run php tests/recognition.php and the complete foundation suite with php tests/run.php, php tests/unit.php and php tests/assistant.php. CI runs the suite on PHP 8.1 and 8.3.
