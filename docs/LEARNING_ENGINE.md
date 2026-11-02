# Native learning engine

The native learning engine replaces the legacy Cockpit-based study portal with PHP and SQLite workflows.

## Student experience

- Authenticated learning dashboard with enrolled courses, available courses, progress summaries, recently studied lessons, bookmarks, and recent quiz results.
- Free self-enrolment for published courses priced at zero.
- Paid course purchases through the shared cart and checkout. Catalogue prices are reloaded server-side; enrolment is activated only after a verified payment confirmation.
- Clean learning routes: `/courses`, `/courses/{course-slug}`, `/learn`, `/learn/{course-slug}`, and `/learn/{course-slug}/{lesson-id}`. Legacy PHP URLs remain compatible and redirect to canonical routes where an unambiguous slug is available.
- Course outline grouped by chapters, published lesson navigation, lesson notes, MP4 video playback, and PDF lesson resources.
- Lesson progress, video position checkpoints, completion state, course completion, and bookmarks.
- Lesson quizzes with randomized question order, configurable pass marks, optional server-enforced time limits, automatic grading, answer review, and saved attempts.

## Access and media security

- Course access requires an active or completed row in `course_enrolments`.
- A paid course cannot be unlocked through the free-enrolment form or by changing browser cart prices. Payment-verified activation is performed by the commerce service after provider verification.
- Lesson resources are stored in private application storage and streamed through `lesson-media.php` only after the user, course enrolment, published lesson, private media record, and file path are checked.
- Video streaming supports HTTP byte ranges for browser seeking.
- The old arbitrary-path video stream, Cockpit quiz grader, and arbitrary-path product download endpoints are retired. Product downloads return in the verified purchase library in Phase 5.

## CMS authoring

In Admin → CMS:
1. Create a course and assign its category, price, slug, image, and SEO metadata.
2. Add chapters and lessons in the intended learning order.
3. Add lesson content and optionally select an MP4 video from private media.
4. Upload a PDF as private media and select it as the lesson resource.
5. Set a quiz time limit (0 means untimed) and pass mark (1–100).
6. Add quiz questions, one option per line, and choose a correct answer that exactly matches an option.
7. Publish the course and its lessons when the learning content is ready.
8. Open Student enrolments to review access, revoke an active enrolment, or restore a revoked enrolment. Completed enrolments remain as completion records.

The media library accepts supported image, PDF, MP4, and MP3 uploads up to 50 MB. Private lesson media is not served through the public image endpoint.

## Database migrations

Migrations `004_learning_engine` and `005_lesson_resources_quiz_settings` are applied automatically by the existing installer/migration runner. They add enrolments, lesson progress, bookmarks, quiz attempts, private lesson resource references, and quiz configuration.

## Current boundary

- No previous Cockpit learning data or old booking database was available to import.
- Existing paid course bookings are not treated as proof of payment and do not automatically create enrolments.
- Offline lesson sync and proctored exams are not part of this learning engine.
