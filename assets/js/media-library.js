(() => {
    const root = document.getElementById('media-library');
    if (!root) return;

    const grid = document.getElementById('media-grid');
    const cards = Array.from(grid.querySelectorAll('[data-media-item]'));
    const search = document.getElementById('media-search');
    const typeFilter = document.getElementById('media-type-filter');
    const visibilityFilter = document.getElementById('media-visibility-filter');
    const sort = document.getElementById('media-sort');
    const count = document.getElementById('media-count');
    const empty = document.getElementById('media-empty');
    const noResults = document.getElementById('media-no-results');
    const dialog = document.getElementById('media-dialog');
    const preview = document.getElementById('media-dialog-preview');
    const title = document.getElementById('media-dialog-title');
    const meta = document.getElementById('media-dialog-meta');
    const editForm = document.getElementById('media-edit-form');
    const editId = document.getElementById('media-edit-id');
    const editAlt = document.getElementById('media-edit-alt');
    const editVisibility = document.getElementById('media-edit-visibility');
    const openFile = document.getElementById('media-open-file');
    const fileInput = document.getElementById('media-files');
    const dropzone = document.getElementById('media-dropzone');
    const selectedFiles = document.getElementById('media-selected-files');

    const formatSize = bytes => {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    };

    const applyFilters = () => {
        const query = search.value.trim().toLowerCase();
        const type = typeFilter.value;
        const visibility = visibilityFilter.value;
        let visible = 0;

        cards.forEach(card => {
            const matchesQuery = !query || (card.dataset.name + ' ' + card.dataset.mime + ' ' + card.dataset.id).includes(query);
            const matchesType = type === 'all' || card.dataset.kind === type;
            const matchesVisibility = visibility === 'all' || card.dataset.visibility === visibility;
            const show = matchesQuery && matchesType && matchesVisibility;
            card.hidden = !show;
            if (show) visible++;
        });

        const noun = visible === 1 ? 'asset' : 'assets';
        count.textContent = visible + ' of ' + cards.length + ' ' + noun;
        noResults.hidden = visible !== 0 || cards.length === 0;
        empty.hidden = cards.length !== 0;
    };

    const sortCards = () => {
        const sorted = [...cards].sort((a, b) => {
            if (sort.value === 'oldest') return a.dataset.created.localeCompare(b.dataset.created);
            if (sort.value === 'name') return a.dataset.name.localeCompare(b.dataset.name);
            if (sort.value === 'largest') return Number(b.dataset.size) - Number(a.dataset.size);
            return b.dataset.created.localeCompare(a.dataset.created) || Number(b.dataset.id) - Number(a.dataset.id);
        });
        sorted.forEach(card => grid.appendChild(card));
    };

    [search, typeFilter, visibilityFilter].forEach(control => control.addEventListener('input', applyFilters));
    sort.addEventListener('change', () => {
        sortCards();
        applyFilters();
    });

    const appendPreview = (mime, url, name) => {
        let element;
        if (mime.startsWith('image/')) {
            element = document.createElement('img');
            element.alt = name;
            element.src = url;
        } else if (mime === 'application/pdf' || mime === 'text/plain' || mime === 'text/csv') {
            element = document.createElement('iframe');
            element.title = name;
            element.src = url;
        } else if (mime === 'video/mp4') {
            element = document.createElement('video');
            element.src = url;
            element.controls = true;
            element.preload = 'metadata';
        } else if (mime === 'audio/mpeg') {
            element = document.createElement('audio');
            element.src = url;
            element.controls = true;
            element.preload = 'metadata';
        } else {
            element = document.createElement('div');
            element.className = 'media-preview-text';
            element.textContent = 'This file type is stored safely and can be downloaded, but your browser does not provide an inline preview for it.';
        }
        preview.replaceChildren(element);
    };

    const openDetails = button => {
        const data = button.dataset;
        const card = button.closest('[data-media-item]');
        const url = data.previewUrl;
        title.textContent = data.name;
        editId.value = data.id;
        editAlt.value = data.alt || '';
        editVisibility.value = data.visibility;
        editVisibility.querySelector('option[value="public"]').disabled = !data.mime.startsWith('image/');
        openFile.href = url + '&download=1';
        meta.replaceChildren();
        [
            'Media ID ' + data.id,
            data.mime,
            formatSize(Number(data.size)),
            data.visibility === 'public' ? 'Public asset' : 'Private asset',
            'Uploaded by ' + (data.uploader || 'Administrator'),
            'Uploaded ' + new Date(data.created).toLocaleDateString()
        ].forEach(value => {
            const item = document.createElement('span');
            item.textContent = value;
            meta.appendChild(item);
        });
        appendPreview(data.mime, url, data.name);
        if (!dialog.open) dialog.showModal();
        if (card) editForm.dataset.asset = card.dataset.id;
    };

    grid.addEventListener('click', event => {
        const previewButton = event.target.closest('[data-media-preview]');
        const editButton = event.target.closest('[data-media-edit]');
        if (previewButton) openDetails(previewButton);
        if (editButton) openDetails(editButton.closest('[data-media-item]').querySelector('[data-media-preview]'));
    });

    dialog.querySelectorAll('[data-media-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', event => {
        if (event.target === dialog) dialog.close();
    });
    dialog.addEventListener('close', () => preview.replaceChildren());

    const showSelected = files => {
        selectedFiles.replaceChildren();
        const list = Array.from(files || []);
        if (list.length > 20) {
            selectedFiles.textContent = 'Select no more than 20 files per upload.';
            fileInput.setCustomValidity('Select no more than 20 files.');
            return;
        }
        fileInput.setCustomValidity('');
        list.forEach(file => {
            const row = document.createElement('div');
            row.className = 'media-selected-row';
            const name = document.createElement('span');
            name.textContent = file.name;
            const size = document.createElement('span');
            size.textContent = formatSize(file.size);
            row.append(name, size);
            selectedFiles.appendChild(row);
        });
        if (list.length) {
            const total = document.createElement('small');
            total.className = 'muted';
            total.textContent = list.length + ' file(s) selected';
            selectedFiles.appendChild(total);
        }
    };

    fileInput.addEventListener('change', () => showSelected(fileInput.files));
    ['dragenter', 'dragover'].forEach(name => dropzone.addEventListener(name, event => {
        event.preventDefault();
        dropzone.classList.add('is-dragging');
    }));
    ['dragleave', 'drop'].forEach(name => dropzone.addEventListener(name, event => {
        event.preventDefault();
        dropzone.classList.remove('is-dragging');
    }));
    dropzone.addEventListener('drop', event => {
        const files = event.dataTransfer?.files;
        if (!files?.length) return;
        fileInput.files = files;
        showSelected(files);
    });

    document.querySelectorAll('[data-media-view]').forEach(button => button.addEventListener('click', () => {
        const list = button.dataset.mediaView === 'list';
        grid.classList.toggle('is-list', list);
        document.querySelectorAll('[data-media-view]').forEach(option => {
            const active = option === button;
            option.classList.toggle('active', active);
            option.setAttribute('aria-pressed', String(active));
        });
    }));

    editForm.addEventListener('submit', event => {
        if (editVisibility.value === 'public' && !editVisibility.querySelector('option[value="public"]').disabled && !editId.value) event.preventDefault();
    });

    applyFilters();
})();
