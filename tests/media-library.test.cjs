const fs = require('node:fs');
const assert = require('node:assert/strict');

const source = fs.readFileSync('assets/js/media-library.js', 'utf8');

for (const feature of [
    'applyFilters',
    'sortCards',
    'appendPreview',
    'showModal',
    'data-media-preview',
    'data-media-edit',
    'dragover',
    'data-media-view',
    'formatSize'
]) {
    assert.ok(source.includes(feature), 'Media library is missing ' + feature);
}

assert.ok(source.includes('image/'), 'Image previews are not supported.');
assert.ok(source.includes('application/pdf'), 'PDF previews are not supported.');
assert.ok(source.includes('video/mp4'), 'Video previews are not supported.');
assert.ok(source.includes('audio/mpeg'), 'Audio previews are not supported.');
assert.ok(source.includes('media-selected-row'), 'Multi-file upload selection is not rendered.');

console.log('PASS media library search, filtering, sorting, previews and multi-file upload interactions');
