# AI learning assistant

The assistant is a PHP and SQLite feature for authenticated students. It uses Gemini through a server-side HTTPS request and retrieves reference passages only from published lessons in courses where the current student has an active or completed enrolment.

## Configuration

Set GEMINI_API_KEY in the hosting environment or the private .env file. GEMINI_MODEL is optional and defaults to gemini-2.5-flash. Keep the API key private and never expose it in browser JavaScript.

The key is optional while the feature is being installed. Questions without matching course material receive a grounded response without calling the AI provider. Questions with matching material require a configured key.

## Retrieval and answer boundaries

- Retrieval reads published lesson notes through the student's active or completed course enrolments.
- Lesson text is converted to plain text, split into bounded passages, ranked by keyword relevance and limited to eight passages.
- The model receives only those passages and is instructed to treat them as untrusted reference data, ignore embedded instructions, avoid unsupported claims and cite source labels.
- The interface links each returned source to its lesson in the study portal.
- The assistant does not search private media, unpublished lessons, other students' records or unowned conversations.

## Privacy and limits

Conversations and source references are stored in the private SQLite database and belong to the authenticated student. Students can only load their own conversations. Requests require a valid CSRF token and are limited to eight per minute per account. Each request is limited to 4,000 characters; conversation history is limited to 80 messages in the interface.

Provider calls use HTTPS, TLS certificate verification and a 25-second timeout. API credentials are sent in a request header and are never returned to the browser. Provider failures are reported without exposing provider response bodies or credentials.

## Hosting requirements

No Node.js service, vector database, queue worker or Composer package is required. The host must allow outbound HTTPS requests to the Gemini API. If outbound requests are disabled, the assistant reports that it is temporarily unavailable while the rest of the learning portal continues to work.
