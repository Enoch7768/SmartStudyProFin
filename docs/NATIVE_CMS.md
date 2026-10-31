# Native SmartStudyPro CMS

The native content manager is available at `/admin/cms.php` after the first-run installer has created the administrator account.

## Requirements

- PHP 8.1 or newer
- PDO SQLite
- Fileinfo
- OpenSSL
- A writable private storage directory outside the public document root
- A SQLite database file inside that private storage directory

The installer applies the versioned migrations, including the native CMS content schema. The CMS uses the Phase 1 administrator role and `manage_content` permission. It does not create a default login.

## Content areas

- Courses: title, slug, description, full content, UGX price, category, image, SEO fields, status, and display order
- Chapters: course relationship, title, and display order
- Lessons: chapter relationship, lesson content, video URL or uploaded video, status, and display order
- Quiz questions: lesson relationship, prompt, answer options, correct answer, and display order
- Products: description, UGX price, category, image, physical/online type, downloadable asset, and publication status
- Pages: editable Home and About page title, subtitle, body, and publication status
- Contact details: phone, email, and address
- Media library: private uploads and explicitly public image assets

## Media handling

Uploads are stored below the private storage directory, not in a public upload folder. Files are assigned random names and SHA-256 checksums. MIME types are detected from file contents. The upload limit is 50 MB. Only image assets explicitly marked public can be delivered through `/media.php?id=...`; other media stays private.

## Current migration boundary

This phase adds the native CMS schema, admin interface, repository, and media handling. Home, About, Courses, Course Details, Products, Product Details, Contact, and booking item validation now read from the native SQLite CMS. The study portal and quiz grading still depend on the legacy Cockpit learning-content structure and are scheduled for the learning-engine migration. Keep the Cockpit runtime and original media assets until that migration is complete. No existing Cockpit records are imported because the old database contents are not available to the project owner.
