(function() {
    'use strict';

    const API = cwdsKanban.restUrl;
    const BOARD_ID = cwdsKanban.boardId;
    const NONCE = cwdsKanban.nonce;

    let boardData = null;
    let currentModal = null;

    // ═══════════════════════════════════════════════
    // API HELPERS
    // ═══════════════════════════════════════════════

    async function apiGet(endpoint) {
        const res = await fetch(API + endpoint, {
            headers: { 'X-WP-Nonce': NONCE },
            credentials: 'same-origin'
        });
        if (!res.ok) {
            const body = await res.text().catch(() => '');
            throw new Error(res.status + ': ' + (body || res.statusText));
        }
        return res.json();
    }

    async function apiPost(endpoint, data) {
        const res = await fetch(API + endpoint, {
            method: 'POST',
            headers: { 'X-WP-Nonce': NONCE, 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(data)
        });
        if (!res.ok) {
            const body = await res.text().catch(() => '');
            throw new Error(res.status + ': ' + (body || res.statusText));
        }
        return res.json();
    }

    async function apiPut(endpoint, data) {
        const res = await fetch(API + endpoint, {
            method: 'PUT',
            headers: { 'X-WP-Nonce': NONCE, 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(data)
        });
        if (!res.ok) {
            const body = await res.text().catch(() => '');
            throw new Error(res.status + ': ' + (body || res.statusText));
        }
        return res.json();
    }

    async function apiDelete(endpoint) {
        const res = await fetch(API + endpoint, {
            method: 'DELETE',
            headers: { 'X-WP-Nonce': NONCE },
            credentials: 'same-origin'
        });
        if (!res.ok) {
            const body = await res.text().catch(() => '');
            throw new Error(res.status + ': ' + (body || res.statusText));
        }
        return res.json();
    }

    async function apiUpload(endpoint, formData) {
        const res = await fetch(API + endpoint, {
            method: 'POST',
            headers: { 'X-WP-Nonce': NONCE },
            credentials: 'same-origin',
            body: formData
        });
        if (!res.ok) throw new Error(res.statusText);
        return res.json();
    }

    // ═══════════════════════════════════════════════
    // SVG ICONS
    // ═══════════════════════════════════════════════

    const ICONS = {
        calendar: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
        check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
        checklist: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>',
        paperclip: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>',
        tag: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>',
        users: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>',
        plus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
        x: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        desc: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
        download: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
        trash: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>'
    };

    function getInitials(name) {
        return name.split(' ').map(w => w[0]).join('').substring(0, 2);
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return months[d.getMonth()] + ' ' + d.getDate();
    }

    function isOverdue(dateStr) {
        if (!dateStr) return false;
        return new Date(dateStr) < new Date();
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function getFileExt(name) {
        return name.split('.').pop().toUpperCase().substring(0, 4);
    }

    function timeAgo(dateStr) {
        const diff = Date.now() - new Date(dateStr).getTime();
        const mins = Math.floor(diff / 60000);
        if (mins < 1) return 'just now';
        if (mins < 60) return mins + 'm ago';
        const hrs = Math.floor(mins / 60);
        if (hrs < 24) return hrs + 'h ago';
        const days = Math.floor(hrs / 24);
        return days + 'd ago';
    }

    // ═══════════════════════════════════════════════
    // BOARD RENDER
    // ═══════════════════════════════════════════════

    async function init() {
        const app = document.getElementById('cwds-kanban-app');
        if (!app) return;

        app.innerHTML = '<div class="cwds-kanban-loading"><div class="spinner"></div><span style="font-size:12px;color:#888;">Loading board...</span></div>';

        try {
            boardData = await apiGet('board/' + BOARD_ID);
            renderBoard(app);
        } catch (err) {
            app.innerHTML = '<div class="cwds-kanban-loading" style="color:#ff4444;">Failed to load board. Please try refreshing.</div>';
            console.error('CWDS Kanban:', err);
        }
    }

    function renderBoard(app) {
        const { board, columns, auth } = boardData;

        let html = '<div class="cwds-board-header">';
        html += '<h2>' + escHtml(board.title) + '</h2>';
        html += '<div class="cwds-user-badge">';
        html += '<div class="cwds-user-avatar" style="background:var(--cwds-lime);">' + getInitials(auth.name) + '</div>';
        html += escHtml(auth.name);
        html += '</div></div>';

        html += '<div class="cwds-board" id="cwds-board">';

        for (const col of columns) {
            html += renderColumn(col);
        }

        // Add another list button
        html += '<div class="cwds-add-list-wrap">';
        html += '<button class="cwds-add-list-btn" onclick="cwdsKanbanApp.showAddList(this)">' + ICONS.plus + ' Add another list</button>';
        html += '</div>';

        html += '</div>';
        app.innerHTML = html;

        // Initialize drag and drop
        initDragDrop();
    }

    function renderColumn(col) {
        const cards = col.cards || [];
        let html = '<div class="cwds-column" data-column-id="' + col.id + '">';
        html += '<div class="cwds-column-header">';
        html += '<h3>' + escHtml(col.title) + '</h3>';
        html += '<span class="cwds-column-count">' + cards.length + '</span>';
        html += '</div>';

        html += '<div class="cwds-column-cards" data-column-id="' + col.id + '">';
        for (const card of cards) {
            html += renderCard(card);
        }
        html += '</div>';

        // Add card button
        html += '<button class="cwds-add-card-btn" onclick="cwdsKanbanApp.showAddCard(this, ' + col.id + ')">';
        html += ICONS.plus + ' Add a card</button>';
        html += '</div>';

        return html;
    }

    function renderCard(card) {
        const completeClass = card.is_complete == 1 ? ' is-complete' : '';
        let html = '<div class="cwds-card' + completeClass + '" data-card-id="' + card.id + '" onclick="cwdsKanbanApp.openCard(' + card.id + ')">';

        // Labels
        if (card.labels && card.labels.length) {
            html += '<div class="cwds-card-labels">';
            for (const label of card.labels) {
                html += '<div class="cwds-card-label" style="background:' + escHtml(label.color) + ';" title="' + escHtml(label.title) + '"></div>';
            }
            html += '</div>';
        }

        // Title
        html += '<div class="cwds-card-title">' + escHtml(card.title) + '</div>';

        // Footer metadata
        const hasMeta = card.due_date || card.checklist_total > 0 || card.attachment_count > 0 || (card.members && card.members.length);
        if (hasMeta) {
            html += '<div class="cwds-card-footer">';

            if (card.due_date) {
                const overdue = !card.is_complete && isOverdue(card.due_date);
                const doneClass = card.is_complete == 1 ? ' is-complete' : (overdue ? ' is-overdue' : '');
                html += '<span class="cwds-card-meta' + doneClass + '">' + ICONS.calendar + formatDate(card.due_date) + '</span>';
            }

            if (card.checklist_total > 0) {
                const clDone = card.checklist_done == card.checklist_total;
                html += '<span class="cwds-card-meta' + (clDone ? ' is-complete' : '') + '">' + ICONS.checklist + card.checklist_done + '/' + card.checklist_total + '</span>';
            }

            if (card.attachment_count > 0) {
                html += '<span class="cwds-card-meta">' + ICONS.paperclip + card.attachment_count + '</span>';
            }

            if (card.members && card.members.length) {
                html += '<div class="cwds-card-members">';
                for (const m of card.members) {
                    html += '<div class="cwds-card-member-avatar" style="background:' + escHtml(m.avatar_color) + ';" title="' + escHtml(m.name) + '">' + getInitials(m.name) + '</div>';
                }
                html += '</div>';
            }

            html += '</div>';
        }

        html += '</div>';
        return html;
    }

    // ═══════════════════════════════════════════════
    // DRAG & DROP
    // ═══════════════════════════════════════════════

    function initDragDrop() {
        // Card drag-and-drop within and across columns
        const lists = document.querySelectorAll('.cwds-column-cards');
        lists.forEach(list => {
            new Sortable(list, {
                group: 'kanban',
                animation: 150,
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                handle: '.cwds-card',
                draggable: '.cwds-card',
                onEnd: function(evt) {
                    const cardId = parseInt(evt.item.dataset.cardId);
                    const newColumnId = parseInt(evt.to.dataset.columnId);
                    const newPosition = evt.newIndex;

                    // Collect all card orders per column
                    const columnCards = {};
                    document.querySelectorAll('.cwds-column-cards').forEach(col => {
                        const colId = col.dataset.columnId;
                        columnCards[colId] = Array.from(col.querySelectorAll('.cwds-card')).map(c => parseInt(c.dataset.cardId));
                    });

                    // Update column counts
                    document.querySelectorAll('.cwds-column').forEach(col => {
                        const count = col.querySelector('.cwds-column-cards').children.length;
                        col.querySelector('.cwds-column-count').textContent = count;
                    });

                    apiPost('cards/move', {
                        card_id: cardId,
                        column_id: newColumnId,
                        position: newPosition,
                        column_cards: columnCards
                    }).catch(err => console.error('Move failed:', err));
                }
            });
        });

        // Column drag-and-drop (reorder lists)
        const board = document.getElementById('cwds-board');
        if (board) {
            new Sortable(board, {
                animation: 200,
                ghostClass: 'cwds-column-ghost',
                dragClass: 'cwds-column-drag',
                handle: '.cwds-column-header',
                draggable: '.cwds-column',
                direction: 'horizontal',
                onEnd: function() {
                    const order = Array.from(board.querySelectorAll('.cwds-column'))
                        .map(col => parseInt(col.dataset.columnId));

                    // Update boardData column order
                    const reordered = [];
                    for (const id of order) {
                        const col = boardData.columns.find(c => parseInt(c.id) === id);
                        if (col) reordered.push(col);
                    }
                    boardData.columns = reordered;

                    apiPost('columns/reorder', { order: order })
                        .catch(err => console.error('Reorder columns failed:', err));
                }
            });
        }
    }

    // ═══════════════════════════════════════════════
    // ADD LIST (column)
    // ═══════════════════════════════════════════════

    function showAddList(btn) {
        btn.style.display = 'none';
        const wrap = btn.parentElement;
        const form = document.createElement('div');
        form.className = 'cwds-add-list-form';
        form.innerHTML = '<input type="text" placeholder="Enter list title..." autofocus>'
            + '<div class="cwds-form-actions">'
            + '<button class="cwds-btn cwds-btn-primary" onclick="cwdsKanbanApp.submitAddList(this)">Add list</button>'
            + '<button class="cwds-btn cwds-btn-ghost" onclick="cwdsKanbanApp.cancelAddList(this)">' + ICONS.x + '</button>'
            + '</div>';
        wrap.appendChild(form);

        const input = form.querySelector('input');
        input.focus();
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitAddList(form.querySelector('.cwds-btn-primary'));
            } else if (e.key === 'Escape') {
                cancelAddList(form.querySelector('.cwds-btn-ghost'));
            }
        });
    }

    async function submitAddList(btn) {
        const form = btn.closest('.cwds-add-list-form');
        const input = form.querySelector('input');
        const title = input.value.trim();
        if (!title) return;

        btn.disabled = true;
        btn.textContent = '...';

        try {
            const col = await apiPost('columns', {
                board_id: parseInt(BOARD_ID),
                title: title
            });

            // Add to boardData
            col.cards = [];
            boardData.columns.push(col);

            // Insert new column before the add-list wrapper
            const board = document.getElementById('cwds-board');
            const addWrap = form.closest('.cwds-add-list-wrap');
            const colEl = document.createElement('div');
            colEl.innerHTML = renderColumn(col);
            board.insertBefore(colEl.firstChild, addWrap);

            // Re-init drag-drop to include new column
            initDragDrop();

            // Reset form
            input.value = '';
            input.focus();
            btn.disabled = false;
            btn.textContent = 'Add list';
        } catch (err) {
            console.error('Add list failed:', err);
            alert('Failed to add list: ' + err.message);
            btn.disabled = false;
            btn.textContent = 'Add list';
        }
    }

    function cancelAddList(btn) {
        const form = btn.closest('.cwds-add-list-form');
        const wrap = form.parentElement;
        form.remove();
        wrap.querySelector('.cwds-add-list-btn').style.display = '';
    }

    // ═══════════════════════════════════════════════
    // ADD CARD
    // ═══════════════════════════════════════════════

    function showAddCard(btn, columnId) {
        // Hide any existing add-card forms
        document.querySelectorAll('.cwds-add-card-form').forEach(f => f.remove());
        document.querySelectorAll('.cwds-add-card-btn').forEach(b => b.style.display = '');

        btn.style.display = 'none';
        const form = document.createElement('div');
        form.className = 'cwds-add-card-form';
        form.innerHTML = '<input type="text" placeholder="Enter card title..." autofocus>'
            + '<div class="cwds-form-actions">'
            + '<button class="cwds-btn cwds-btn-primary" onclick="cwdsKanbanApp.submitAddCard(this, ' + columnId + ')">Add</button>'
            + '<button class="cwds-btn cwds-btn-ghost" onclick="cwdsKanbanApp.cancelAddCard(this)">Cancel</button>'
            + '</div>';
        btn.parentElement.appendChild(form);

        const input = form.querySelector('input');
        input.focus();
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitAddCard(form.querySelector('.cwds-btn-primary'), columnId);
            } else if (e.key === 'Escape') {
                cancelAddCard(form.querySelector('.cwds-btn-ghost'));
            }
        });
    }

    async function submitAddCard(btn, columnId) {
        const form = btn.closest('.cwds-add-card-form');
        const input = form.querySelector('input');
        const title = input.value.trim();
        if (!title) return;

        btn.disabled = true;
        btn.textContent = '...';

        try {
            const card = await apiPost('cards', {
                board_id: parseInt(BOARD_ID),
                column_id: columnId,
                title: title
            });

            // Add card to DOM
            const cardList = form.parentElement.querySelector('.cwds-column-cards');
            const cardEl = document.createElement('div');
            cardEl.innerHTML = renderCard(card);
            cardList.appendChild(cardEl.firstChild);

            // Update count
            const col = form.closest('.cwds-column');
            col.querySelector('.cwds-column-count').textContent = cardList.children.length;

            // Also update boardData
            const colData = boardData.columns.find(c => c.id == columnId);
            if (colData) colData.cards.push(card);

            // Reset input
            input.value = '';
            input.focus();
        } catch (err) {
            console.error('Add card failed:', err);
            alert('Failed to add card: ' + err.message);
            btn.disabled = false;
            btn.textContent = 'Add';
        }
    }

    function cancelAddCard(btn) {
        const form = btn.closest('.cwds-add-card-form');
        const col = form.closest('.cwds-column');
        form.remove();
        col.querySelector('.cwds-add-card-btn').style.display = '';
    }

    // ═══════════════════════════════════════════════
    // CARD MODAL
    // ═══════════════════════════════════════════════

    async function openCard(cardId) {
        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'cwds-modal-overlay';
        overlay.innerHTML = '<div class="cwds-modal"><div class="cwds-kanban-loading" style="padding:60px;"><div class="spinner"></div></div></div>';
        document.body.appendChild(overlay);

        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) closeModal();
        });

        document.addEventListener('keydown', modalEscHandler);

        try {
            const card = await apiGet('cards/' + cardId);
            currentModal = card;
            renderModal(overlay.querySelector('.cwds-modal'), card);
        } catch (err) {
            console.error('Failed to load card:', err);
            closeModal();
        }
    }

    function modalEscHandler(e) {
        if (e.key === 'Escape') {
            // Close popover first if one is open, otherwise close modal
            const popover = document.querySelector('.cwds-popover');
            if (popover) {
                closePopovers();
            } else {
                closeModal();
            }
        }
    }

    function closeModal() {
        const overlay = document.querySelector('.cwds-modal-overlay');
        if (overlay) overlay.remove();
        document.removeEventListener('keydown', modalEscHandler);
        currentModal = null;
        // Refresh board to sync changes
        refreshBoard();
    }

    async function refreshBoard() {
        try {
            boardData = await apiGet('board/' + BOARD_ID);
            const app = document.getElementById('cwds-kanban-app');
            renderBoard(app);
        } catch (err) {
            console.error('Refresh failed:', err);
        }
    }

    function renderModal(modal, card) {
        // Find column name
        let colName = '';
        for (const col of boardData.columns) {
            if (col.cards && col.cards.find(c => c.id == card.id)) {
                colName = col.title;
                break;
            }
        }

        let html = '<button class="cwds-modal-close" onclick="cwdsKanbanApp.closeModal()">' + ICONS.x + '</button>';

        // ── Two-column layout: Left (card detail) | Right (comments & activity) ──
        html += '<div class="cwds-modal-layout">';

        // ══════ LEFT PANEL ══════
        html += '<div class="cwds-modal-left">';

        // Complete toggle circle + Title (Trello style)
        html += '<div class="cwds-modal-header">';
        html += '<div class="cwds-modal-title-row">';
        html += '<div class="cwds-complete-circle' + (card.is_complete == 1 ? ' is-complete' : '') + '" onclick="cwdsKanbanApp.toggleComplete(' + card.id + ', this)" title="' + (card.is_complete == 1 ? 'Completed' : 'Mark as complete') + '">' + (card.is_complete == 1 ? ICONS.check : '') + '</div>';
        html += '<div class="cwds-modal-title" contenteditable="true" data-card-id="' + card.id + '" onblur="cwdsKanbanApp.updateTitle(this)">' + escHtml(card.title) + '</div>';
        html += '</div>';
        html += '<div class="cwds-modal-column-name">in <strong>' + escHtml(colName) + '</strong></div>';
        html += '</div>';

        // Action buttons row (Trello style: + Add, Dates, Checklist, Attachment)
        html += '<div class="cwds-modal-actions">';
        html += '<button class="cwds-action-btn cwds-action-btn-add" onclick="cwdsKanbanApp.showAddToCard(this, ' + card.id + ')">' + ICONS.plus + ' Add</button>';
        html += '<button class="cwds-action-btn" onclick="cwdsKanbanApp.focusDueDate()">' + ICONS.calendar + ' Dates</button>';
        html += '<button class="cwds-action-btn" onclick="cwdsKanbanApp.addChecklist(' + card.id + ')">' + ICONS.checklist + ' Checklist</button>';
        html += '<button class="cwds-action-btn" onclick="document.getElementById(\'cwds-upload-zone\').querySelector(\'input\').click()">' + ICONS.paperclip + ' Attachment</button>';
        html += '</div>';

        // Metadata row: Members | Labels | Due date
        html += '<div class="cwds-modal-meta-row">';

        // Members
        html += '<div class="cwds-meta-group">';
        html += '<div class="cwds-meta-label">Members</div>';
        html += '<div class="cwds-meta-value cwds-meta-members">';
        if (card.members && card.members.length) {
            for (const m of card.members) {
                html += '<div class="cwds-user-avatar" style="background:' + escHtml(m.avatar_color) + ';width:32px;height:32px;font-size:10px;" title="' + escHtml(m.name) + '">' + getInitials(m.name) + '</div>';
            }
        }
        html += '<button class="cwds-meta-add-btn" onclick="cwdsKanbanApp.showMemberPicker(this, ' + card.id + ')">+</button>';
        html += '</div></div>';

        // Labels
        html += '<div class="cwds-meta-group">';
        html += '<div class="cwds-meta-label">Labels</div>';
        html += '<div class="cwds-meta-value cwds-meta-labels">';
        if (card.labels && card.labels.length) {
            for (const label of card.labels) {
                html += '<span class="cwds-modal-label" style="background:' + escHtml(label.color) + ';">' + escHtml(label.title) + '</span>';
            }
        }
        html += '<button class="cwds-meta-add-btn" onclick="cwdsKanbanApp.showLabelPicker(this, ' + card.id + ')">+</button>';
        html += '</div></div>';

        // Due date
        html += '<div class="cwds-meta-group">';
        html += '<div class="cwds-meta-label">Due date</div>';
        html += '<div class="cwds-meta-value">';
        html += '<input type="datetime-local" class="cwds-due-date-input" id="cwds-due-date-input" value="' + (card.due_date ? card.due_date.replace(' ', 'T').substring(0, 16) : '') + '" onchange="cwdsKanbanApp.updateDueDate(' + card.id + ', this.value)">';
        if (card.due_date) {
            html += ' <button class="cwds-btn cwds-btn-ghost cwds-btn-sm" onclick="cwdsKanbanApp.clearDueDate(' + card.id + ', this)" style="margin-left:4px;">×</button>';
        }
        html += '</div></div>';

        html += '</div>'; // end meta row

        // Description
        html += '<div class="cwds-modal-section">';
        html += '<div class="cwds-section-header">';
        html += '<div class="cwds-section-title">' + ICONS.desc + ' Description</div>';
        if (card.description) {
            html += '<button class="cwds-btn cwds-btn-ghost cwds-btn-sm" onclick="cwdsKanbanApp.editDescription(' + card.id + ')">Edit</button>';
        }
        html += '</div>';
        if (card.description) {
            // Show rendered description (read mode)
            html += '<div class="cwds-desc-display" id="cwds-desc-display" onclick="cwdsKanbanApp.editDescription(' + card.id + ')">' + (card.description || '') + '</div>';
            // Editor hidden initially
            html += '<div class="cwds-desc-editor" id="cwds-desc-editor" style="display:none;">';
        } else {
            // No description — show editor immediately
            html += '<div class="cwds-desc-editor" id="cwds-desc-editor">';
        }
        html += '<div class="cwds-editor-toolbar" id="cwds-editor-toolbar">';
        html += '<button type="button" class="cwds-toolbar-btn" onclick="cwdsKanbanApp.execFormat(\'bold\')" title="Bold"><strong>B</strong></button>';
        html += '<button type="button" class="cwds-toolbar-btn" onclick="cwdsKanbanApp.execFormat(\'italic\')" title="Italic"><em>I</em></button>';
        html += '<button type="button" class="cwds-toolbar-btn" onclick="cwdsKanbanApp.execFormat(\'strikeThrough\')" title="Strikethrough"><s>S</s></button>';
        html += '<span class="cwds-toolbar-sep"></span>';
        html += '<button type="button" class="cwds-toolbar-btn" onclick="cwdsKanbanApp.execFormat(\'insertUnorderedList\')" title="Bullet list">• —</button>';
        html += '<button type="button" class="cwds-toolbar-btn" onclick="cwdsKanbanApp.execFormat(\'insertOrderedList\')" title="Numbered list">1.</button>';
        html += '<span class="cwds-toolbar-sep"></span>';
        html += '<button type="button" class="cwds-toolbar-btn" onclick="cwdsKanbanApp.insertLink()" title="Insert link">' + ICONS.paperclip + '</button>';
        html += '</div>';
        html += '<div class="cwds-desc-editable" id="cwds-desc-editable" contenteditable="true" data-placeholder="Add a more detailed description...">' + (card.description || '') + '</div>';
        html += '<div class="cwds-editor-actions">';
        html += '<button class="cwds-btn cwds-btn-primary cwds-btn-sm" onclick="cwdsKanbanApp.saveDescription(' + card.id + ')">Save</button>';
        html += '<button class="cwds-btn cwds-btn-ghost cwds-btn-sm" onclick="cwdsKanbanApp.cancelDescription()">Cancel</button>';
        html += '</div>';
        html += '</div>'; // end editor
        html += '</div>';

        // Checklists
        if (card.checklists && card.checklists.length) {
            for (const cl of card.checklists) {
                html += renderChecklist(cl, card.id);
            }
        }

        // Attachments
        html += '<div class="cwds-attachments">';
        html += '<div class="cwds-section-title">' + ICONS.paperclip + ' Attachments</div>';
        if (card.attachments && card.attachments.length) {
            for (const att of card.attachments) {
                html += renderAttachment(att);
            }
        }
        html += '<div class="cwds-upload-zone" onclick="this.querySelector(\'input\').click()" id="cwds-upload-zone">';
        html += '<input type="file" onchange="cwdsKanbanApp.uploadFile(' + card.id + ', this.files[0])">';
        html += ICONS.plus + ' Drop file or click to upload';
        html += '</div></div>';

        // Delete button (admin only, at bottom)
        if (boardData.auth.type === 'admin') {
            html += '<div class="cwds-modal-delete-row">';
            html += '<button class="cwds-action-btn cwds-action-danger" onclick="cwdsKanbanApp.deleteCard(' + card.id + ')">' + ICONS.trash + ' Delete card</button>';
            html += '</div>';
        }

        html += '</div>'; // end left panel

        // ══════ RIGHT PANEL: Comments & Activity ══════
        html += '<div class="cwds-modal-right">';
        html += '<div class="cwds-right-title">Comments & Activity</div>';

        // Comment input
        html += '<div class="cwds-comment-input-wrap">';
        html += '<textarea class="cwds-comment-input" id="cwds-comment-input" placeholder="Write a comment..." rows="2"></textarea>';
        html += '<button class="cwds-btn cwds-btn-primary cwds-btn-sm" onclick="cwdsKanbanApp.postComment(' + card.id + ')" style="margin-top:6px;">Save</button>';
        html += '</div>';

        // Comments list
        html += '<div class="cwds-comments-list" id="cwds-comments-list">';
        if (card.comments && card.comments.length) {
            for (const comment of card.comments) {
                html += renderComment(comment);
            }
        }
        html += '</div>';

        // Activity log
        html += '<div class="cwds-activity-section">';
        html += '<div class="cwds-right-subtitle">Activity</div>';
        if (card.activity && card.activity.length) {
            for (const act of card.activity) {
                html += '<div class="cwds-activity-item">';
                html += '<div class="cwds-activity-avatar" style="background:' + escHtml(act.actor_type === 'admin' ? '#E3FF04' : '#04FFE3') + ';">' + getInitials(act.actor_name) + '</div>';
                html += '<div><div class="cwds-activity-text"><strong>' + escHtml(act.actor_name) + '</strong> ' + escHtml(act.details) + '</div>';
                html += '<div class="cwds-activity-time">' + timeAgo(act.created_at) + '</div></div>';
                html += '</div>';
            }
        } else {
            html += '<div style="font-size:11px;color:var(--cwds-text-dim);padding:4px 0;">No activity yet.</div>';
        }
        html += '</div>';

        html += '</div>'; // end right panel
        html += '</div>'; // end layout

        modal.innerHTML = html;

        // Setup drag-drop on upload zone
        setupUploadZone(card.id);

        // Setup @mention autocomplete
        setupMentionAutocomplete();

        // Close popovers when clicking outside them
        modal.addEventListener('click', function(e) {
            // If the click target is detached from the DOM (e.g. after popover content swap),
            // don't close — the popover was just rebuilt
            if (!document.body.contains(e.target)) return;
            if (!e.target.closest('.cwds-popover') && !e.target.closest('.cwds-action-btn') && !e.target.closest('.cwds-meta-add-btn')) {
                closePopovers();
            }
        });
    }

    function renderComment(comment) {
        let html = '<div class="cwds-comment" data-comment-id="' + comment.id + '">';
        html += '<div class="cwds-comment-header">';
        html += '<div class="cwds-activity-avatar" style="background:' + escHtml(comment.author_avatar_color || '#E3FF04') + ';">' + getInitials(comment.author_name) + '</div>';
        html += '<div class="cwds-comment-meta">';
        html += '<strong>' + escHtml(comment.author_name) + '</strong>';
        html += '<span class="cwds-activity-time">' + timeAgo(comment.created_at) + '</span>';
        html += '</div>';
        // Delete button (admin or author)
        if (boardData.auth.type === 'admin' || boardData.auth.name === comment.author_name) {
            html += '<button class="cwds-comment-delete" onclick="cwdsKanbanApp.deleteComment(' + comment.id + ',' + comment.card_id + ')" title="Delete">' + ICONS.x + '</button>';
        }
        html += '</div>';
        // Body with @mentions highlighted
        let body = escHtml(comment.body);
        body = body.replace(/@(\w[\w\s]*?)(?=\s|$|,|\.)/g, '<span class="cwds-mention">@$1</span>');
        html += '<div class="cwds-comment-body">' + body + '</div>';
        html += '</div>';
        return html;
    }

    function setupMentionAutocomplete() {
        const input = document.getElementById('cwds-comment-input');
        if (!input) return;

        input.addEventListener('input', function() {
            const val = this.value;
            const cursorPos = this.selectionStart;
            // Find @ before cursor
            const textBeforeCursor = val.substring(0, cursorPos);
            const atMatch = textBeforeCursor.match(/@(\w*)$/);

            // Remove existing dropdown
            const existing = document.getElementById('cwds-mention-dropdown');
            if (existing) existing.remove();

            if (!atMatch) return;

            const query = atMatch[1].toLowerCase();
            const matches = boardData.members.filter(m =>
                m.name.toLowerCase().includes(query)
            );

            if (!matches.length) return;

            const dropdown = document.createElement('div');
            dropdown.id = 'cwds-mention-dropdown';
            dropdown.className = 'cwds-mention-dropdown';

            for (const m of matches) {
                const item = document.createElement('div');
                item.className = 'cwds-mention-item';
                item.innerHTML = '<div class="cwds-user-avatar" style="background:' + escHtml(m.avatar_color) + ';width:22px;height:22px;font-size:8px;">' + getInitials(m.name) + '</div><span>' + escHtml(m.name) + '</span>';
                item.addEventListener('click', function() {
                    // Replace @partial with @FullName
                    const before = val.substring(0, cursorPos - atMatch[1].length);
                    const after = val.substring(cursorPos);
                    input.value = before + m.name + ' ' + after;
                    dropdown.remove();
                    input.focus();
                });
                dropdown.appendChild(item);
            }

            input.parentElement.style.position = 'relative';
            input.parentElement.appendChild(dropdown);
        });

        // Close dropdown on blur (delayed to allow click)
        input.addEventListener('blur', function() {
            setTimeout(function() {
                const dd = document.getElementById('cwds-mention-dropdown');
                if (dd) dd.remove();
            }, 200);
        });
    }

    function renderChecklist(cl, cardId) {
        const total = cl.items ? cl.items.length : 0;
        const done = cl.items ? cl.items.filter(i => i.is_checked == 1).length : 0;
        const pct = total > 0 ? (done / total * 100) : 0;

        let html = '<div class="cwds-checklist" data-checklist-id="' + cl.id + '">';
        html += '<div class="cwds-checklist-header">';
        html += '<span class="cwds-checklist-title">' + ICONS.checklist + ' ' + escHtml(cl.title) + '</span>';
        html += '<span style="font-size:10px;color:var(--cwds-text-dim);">' + done + '/' + total + '</span>';
        html += '</div>';

        html += '<div class="cwds-checklist-progress"><div class="cwds-checklist-progress-bar" style="width:' + pct + '%;"></div></div>';

        if (cl.items) {
            for (const item of cl.items) {
                const checked = item.is_checked == 1;
                html += '<div class="cwds-checklist-item' + (checked ? ' is-checked' : '') + '" onclick="cwdsKanbanApp.toggleChecklistItem(' + item.id + ',' + (checked ? 0 : 1) + ',' + cardId + ')">';
                html += '<div class="cwds-checklist-checkbox">' + (checked ? ICONS.check : '') + '</div>';
                html += '<span>' + escHtml(item.text) + '</span>';
                html += '</div>';
            }
        }

        html += '<div class="cwds-checklist-add-item">';
        html += '<input type="text" placeholder="Add item..." onkeydown="if(event.key===\'Enter\')cwdsKanbanApp.addChecklistItem(' + cl.id + ',this,' + cardId + ')">';
        html += '<button class="cwds-btn cwds-btn-primary cwds-btn-sm" onclick="cwdsKanbanApp.addChecklistItem(' + cl.id + ',this.previousElementSibling,' + cardId + ')">Add</button>';
        html += '</div></div>';

        return html;
    }

    function renderAttachment(att) {
        let html = '<div class="cwds-attachment-item">';
        html += '<div class="cwds-attachment-icon">' + getFileExt(att.file_name) + '</div>';
        html += '<div class="cwds-attachment-info">';
        html += '<div class="cwds-attachment-name">' + escHtml(att.file_name) + '</div>';
        html += '<div class="cwds-attachment-meta">' + formatFileSize(att.file_size) + ' · ' + escHtml(att.uploaded_by) + ' · ' + timeAgo(att.created_at) + '</div>';
        html += '</div>';
        html += '<div class="cwds-attachment-actions">';
        html += '<a href="' + escHtml(att.file_url) + '" target="_blank" download>' + ICONS.download + '</a> ';
        html += '<button onclick="cwdsKanbanApp.deleteAttachment(' + att.id + ',' + att.card_id + ')">' + ICONS.trash + '</button>';
        html += '</div></div>';
        return html;
    }

    // ═══════════════════════════════════════════════
    // CARD ACTIONS
    // ═══════════════════════════════════════════════

    async function updateTitle(el) {
        const cardId = el.dataset.cardId;
        const title = el.textContent.trim();
        if (!title) return;
        try {
            await apiPut('cards/' + cardId, { title: title });
        } catch (err) {
            console.error('Update title failed:', err);
        }
    }

    function editDescription(cardId) {
        const display = document.getElementById('cwds-desc-display');
        const editor = document.getElementById('cwds-desc-editor');
        if (display) display.style.display = 'none';
        if (editor) {
            editor.style.display = '';
            const editable = document.getElementById('cwds-desc-editable');
            if (editable) editable.focus();
        }
    }

    async function saveDescription(cardId) {
        const editable = document.getElementById('cwds-desc-editable');
        if (!editable) return;
        const value = editable.innerHTML.trim();
        try {
            await apiPut('cards/' + cardId, { description: value });
            // Update display and swap back to read mode
            const display = document.getElementById('cwds-desc-display');
            const editor = document.getElementById('cwds-desc-editor');
            if (value) {
                if (display) {
                    display.innerHTML = value;
                    display.style.display = '';
                } else {
                    // Create display element if it didn't exist (was empty before)
                    const section = editor.parentElement;
                    const div = document.createElement('div');
                    div.className = 'cwds-desc-display';
                    div.id = 'cwds-desc-display';
                    div.innerHTML = value;
                    div.onclick = function() { editDescription(cardId); };
                    section.insertBefore(div, editor);
                    // Also add Edit button if missing
                    const header = section.querySelector('.cwds-section-header');
                    if (header && !header.querySelector('.cwds-btn')) {
                        header.insertAdjacentHTML('beforeend', '<button class="cwds-btn cwds-btn-ghost cwds-btn-sm" onclick="cwdsKanbanApp.editDescription(' + cardId + ')">Edit</button>');
                    }
                }
                editor.style.display = 'none';
            } else {
                // Empty description — keep editor visible
                if (display) display.style.display = 'none';
            }
            // Update currentModal
            if (currentModal) currentModal.description = value;
        } catch (err) {
            console.error('Save description failed:', err);
        }
    }

    function cancelDescription() {
        const display = document.getElementById('cwds-desc-display');
        const editor = document.getElementById('cwds-desc-editor');
        const editable = document.getElementById('cwds-desc-editable');
        if (display && display.innerHTML.trim()) {
            // Revert editor to saved content and show display
            if (editable) editable.innerHTML = display.innerHTML;
            display.style.display = '';
            editor.style.display = 'none';
        } else {
            // No saved content — clear editor
            if (editable) editable.innerHTML = '';
        }
    }

    function execFormat(command) {
        document.execCommand(command, false, null);
        document.getElementById('cwds-desc-editable')?.focus();
    }

    function insertLink() {
        const url = prompt('Enter URL:');
        if (url) {
            document.execCommand('createLink', false, url);
            document.getElementById('cwds-desc-editable')?.focus();
        }
    }

    async function updateDueDate(cardId, value) {
        try {
            await apiPut('cards/' + cardId, { due_date: value ? value.replace('T', ' ') + ':00' : null });
        } catch (err) {
            console.error('Update due date failed:', err);
        }
    }

    async function clearDueDate(cardId, btn) {
        try {
            await apiPut('cards/' + cardId, { due_date: null });
            const input = btn.previousElementSibling;
            input.value = '';
            btn.remove();
        } catch (err) {
            console.error('Clear due date failed:', err);
        }
    }

    async function toggleComplete(cardId, el) {
        const isComplete = el.classList.contains('is-complete') ? 0 : 1;
        try {
            await apiPut('cards/' + cardId, { is_complete: isComplete });
            el.classList.toggle('is-complete');
            el.innerHTML = isComplete ? ICONS.check : '';
            el.title = isComplete ? 'Completed' : 'Mark as complete';
        } catch (err) {
            console.error('Toggle complete failed:', err);
        }
    }

    // ═══════════════════════════════════════════════
    // ADD TO CARD DROPDOWN (Trello-style)
    // ═══════════════════════════════════════════════

    function showAddToCard(btn, cardId) {
        closePopovers();

        let html = '<div class="cwds-popover cwds-add-to-card-popover">';
        html += '<div class="cwds-popover-title">Add to card</div>';
        html += '<button class="cwds-popover-close" onclick="cwdsKanbanApp.closePopovers()">' + ICONS.x + '</button>';

        html += '<div class="cwds-add-to-card-list">';
        html += '<div class="cwds-add-to-card-item" onclick="cwdsKanbanApp.closePopovers();cwdsKanbanApp.showLabelPicker(document.querySelector(\'.cwds-meta-add-btn[onclick*=showLabelPicker]\'), ' + cardId + ')">';
        html += '<div class="cwds-add-to-card-icon">' + ICONS.tag + '</div>';
        html += '<div><div class="cwds-add-to-card-name">Labels</div><div class="cwds-add-to-card-desc">Organize, categorize, and prioritize</div></div>';
        html += '</div>';

        html += '<div class="cwds-add-to-card-item" onclick="cwdsKanbanApp.closePopovers();cwdsKanbanApp.focusDueDate()">';
        html += '<div class="cwds-add-to-card-icon">' + ICONS.calendar + '</div>';
        html += '<div><div class="cwds-add-to-card-name">Dates</div><div class="cwds-add-to-card-desc">Start dates, due dates, and reminders</div></div>';
        html += '</div>';

        html += '<div class="cwds-add-to-card-item" onclick="cwdsKanbanApp.closePopovers();cwdsKanbanApp.addChecklist(' + cardId + ')">';
        html += '<div class="cwds-add-to-card-icon">' + ICONS.checklist + '</div>';
        html += '<div><div class="cwds-add-to-card-name">Checklist</div><div class="cwds-add-to-card-desc">Add subtasks</div></div>';
        html += '</div>';

        html += '<div class="cwds-add-to-card-item" onclick="cwdsKanbanApp.closePopovers();cwdsKanbanApp.showMemberPicker(document.querySelector(\'.cwds-meta-add-btn[onclick*=showMemberPicker]\'), ' + cardId + ')">';
        html += '<div class="cwds-add-to-card-icon">' + ICONS.users + '</div>';
        html += '<div><div class="cwds-add-to-card-name">Members</div><div class="cwds-add-to-card-desc">Assign members</div></div>';
        html += '</div>';

        html += '<div class="cwds-add-to-card-item" onclick="cwdsKanbanApp.closePopovers();document.getElementById(\'cwds-upload-zone\').querySelector(\'input\').click()">';
        html += '<div class="cwds-add-to-card-icon">' + ICONS.paperclip + '</div>';
        html += '<div><div class="cwds-add-to-card-name">Attachment</div><div class="cwds-add-to-card-desc">Add links, pages, work items, and more</div></div>';
        html += '</div>';

        html += '</div></div>';

        positionPopover(btn, html);
    }

    function focusDueDate() {
        const input = document.getElementById('cwds-due-date-input');
        if (input) {
            input.showPicker ? input.showPicker() : input.focus();
        }
    }

    async function deleteCard(cardId) {
        if (!confirm('Delete this card? This cannot be undone.')) return;
        try {
            await apiDelete('cards/' + cardId);
            closeModal();
        } catch (err) {
            console.error('Delete card failed:', err);
        }
    }

    // ═══════════════════════════════════════════════
    // LABELS PICKER
    // ═══════════════════════════════════════════════

    function showLabelPicker(btn, cardId) {
        closePopovers();
        const card = currentModal;
        const cardLabelIds = (card.labels || []).map(l => l.id);

        let html = '<div class="cwds-popover cwds-label-popover">';
        html += '<div class="cwds-popover-title">Labels</div>';
        html += '<button class="cwds-popover-close" onclick="cwdsKanbanApp.closePopovers()">' + ICONS.x + '</button>';

        // Search
        html += '<input type="text" class="cwds-label-search" placeholder="Search labels..." oninput="cwdsKanbanApp.filterLabels(this.value)">';

        // Label list
        html += '<div class="cwds-label-list" id="cwds-label-list">';
        for (const label of boardData.labels) {
            const isActive = cardLabelIds.includes(label.id);
            html += '<div class="cwds-label-row" data-label-name="' + escHtml(label.title).toLowerCase() + '">';
            html += '<div class="cwds-label-check" onclick="cwdsKanbanApp.toggleLabel(' + cardId + ',' + label.id + ',this)">' + (isActive ? '✓' : '') + '</div>';
            html += '<div class="cwds-label-pill" style="background:' + escHtml(label.color) + ';" onclick="cwdsKanbanApp.toggleLabel(' + cardId + ',' + label.id + ',this.previousElementSibling)">' + escHtml(label.title || '') + '</div>';
            html += '</div>';
        }
        if (!boardData.labels.length) {
            html += '<div style="font-size:11px;color:var(--cwds-text-dim);padding:8px 0;">No labels yet.</div>';
        }
        html += '</div>';

        // Create new label button
        html += '<button class="cwds-create-label-btn" onclick="cwdsKanbanApp.showCreateLabel(this, ' + cardId + ')">Create a new label</button>';

        html += '</div>';

        positionPopover(btn, html);
    }

    function filterLabels(query) {
        const q = query.toLowerCase();
        document.querySelectorAll('.cwds-label-row').forEach(row => {
            const name = row.dataset.labelName || '';
            row.style.display = name.includes(q) ? '' : 'none';
        });
    }

    function showCreateLabel(btn, cardId) {
        // Replace the label list with create form
        const popover = btn.closest('.cwds-popover');

        const colors = [
            '#b7e1cd', '#d4e157', '#fff9c4', '#ffccbc', '#e1bee7',
            '#4caf50', '#c0ca33', '#ffb300', '#ff7043', '#ab47bc',
            '#2e7d32', '#9e9d24', '#e65100', '#e53935', '#7b1fa2',
            '#b3e5fc', '#b2ebf2', '#dcedc8', '#f8bbd0', '#cfd8dc',
            '#64b5f6', '#4dd0e1', '#aed581', '#f06292', '#90a4ae',
            '#1976d2', '#00897b', '#689f38', '#e91e63', '#607d8b'
        ];

        let html = '<div class="cwds-popover-title" style="display:flex;align-items:center;gap:8px;">';
        html += '<button class="cwds-popover-back" onclick="cwdsKanbanApp.backToLabels(this, ' + cardId + ')">‹</button>';
        html += 'Create label</div>';

        // Color preview
        html += '<div class="cwds-label-preview" id="cwds-label-preview" style="background:#b7e1cd;"></div>';

        // Title input
        html += '<div style="margin-bottom:10px;">';
        html += '<label style="font-size:10px;font-weight:700;color:var(--cwds-text-muted);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px;">Title</label>';
        html += '<input type="text" class="cwds-label-title-input" id="cwds-label-title-input" placeholder="">';
        html += '</div>';

        // Color grid
        html += '<label style="font-size:10px;font-weight:700;color:var(--cwds-text-muted);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:6px;">Select a color</label>';
        html += '<div class="cwds-color-grid">';
        for (const color of colors) {
            html += '<div class="cwds-color-swatch" style="background:' + color + ';" data-color="' + color + '" onclick="cwdsKanbanApp.selectLabelColor(this)"></div>';
        }
        html += '</div>';

        // Create button
        html += '<button class="cwds-btn cwds-btn-primary" style="margin-top:12px;width:auto;" onclick="cwdsKanbanApp.createLabel(' + cardId + ')">Create</button>';

        // Store selected color
        popover.dataset.selectedColor = '#b7e1cd';

        // Replace content (keep close button)
        const closeBtn = popover.querySelector('.cwds-popover-close');
        popover.innerHTML = '';
        popover.appendChild(closeBtn);
        popover.insertAdjacentHTML('beforeend', html);

        // Mark first swatch as selected
        const firstSwatch = popover.querySelector('.cwds-color-swatch');
        if (firstSwatch) firstSwatch.classList.add('selected');
    }

    function selectLabelColor(el) {
        const grid = el.closest('.cwds-color-grid');
        grid.querySelectorAll('.cwds-color-swatch').forEach(s => s.classList.remove('selected'));
        el.classList.add('selected');

        const popover = el.closest('.cwds-popover');
        popover.dataset.selectedColor = el.dataset.color;

        // Update preview
        const preview = document.getElementById('cwds-label-preview');
        if (preview) preview.style.background = el.dataset.color;
    }

    function backToLabels(btn, cardId) {
        const popover = btn.closest('.cwds-popover');
        popover.remove();
        // Re-open label picker — find the labels button
        const labelsBtn = document.querySelector('.cwds-action-btn[onclick*="showLabelPicker"]') || document.querySelector('.cwds-meta-add-btn[onclick*="showLabelPicker"]');
        if (labelsBtn) showLabelPicker(labelsBtn, cardId);
    }

    async function createLabel(cardId) {
        const titleInput = document.getElementById('cwds-label-title-input');
        const popover = titleInput.closest('.cwds-popover');
        const title = titleInput.value.trim();
        const color = popover.dataset.selectedColor || '#b7e1cd';

        try {
            const newLabel = await apiPost('labels', {
                board_id: parseInt(BOARD_ID),
                title: title,
                color: color
            });

            // Add to boardData
            boardData.labels.push(newLabel);

            // Auto-assign to current card
            await apiPost('cards/' + cardId + '/labels', { label_id: newLabel.id });
            if (currentModal) {
                currentModal.labels.push(newLabel);
            }

            // Close popover and refresh modal
            popover.remove();
            const card = await apiGet('cards/' + cardId);
            currentModal = card;
            renderModal(document.querySelector('.cwds-modal'), card);
        } catch (err) {
            console.error('Create label failed:', err);
            alert('Failed to create label: ' + err.message);
        }
    }

    async function toggleLabel(cardId, labelId, el) {
        try {
            const result = await apiPost('cards/' + cardId + '/labels', { label_id: labelId });
            // Update check mark
            if (el.classList.contains('cwds-label-check')) {
                el.textContent = result.action === 'added' ? '✓' : '';
            } else {
                // Clicked on the pill — find sibling check
                const check = el.closest('.cwds-label-row').querySelector('.cwds-label-check');
                if (check) check.textContent = result.action === 'added' ? '✓' : '';
            }

            // Update currentModal labels
            if (result.action === 'added') {
                const label = boardData.labels.find(l => l.id === labelId);
                if (label && currentModal) currentModal.labels.push(label);
            } else if (currentModal) {
                currentModal.labels = currentModal.labels.filter(l => l.id !== labelId);
            }
        } catch (err) {
            console.error('Toggle label failed:', err);
        }
    }

    // ═══════════════════════════════════════════════
    // MEMBERS PICKER
    // ═══════════════════════════════════════════════

    function showMemberPicker(btn, cardId) {
        closePopovers();
        const card = currentModal;
        const cardMemberIds = (card.members || []).map(m => parseInt(m.id));

        let html = '<div class="cwds-popover cwds-member-popover">';
        html += '<div class="cwds-popover-title">Members</div>';
        html += '<button class="cwds-popover-close" onclick="cwdsKanbanApp.closePopovers()">' + ICONS.x + '</button>';

        // Search
        html += '<input type="text" class="cwds-label-search" placeholder="Search members..." oninput="cwdsKanbanApp.filterMembers(this.value)">';

        // Card members section
        const cardMembers = boardData.members.filter(m => cardMemberIds.includes(parseInt(m.id)));
        const otherMembers = boardData.members.filter(m => !cardMemberIds.includes(parseInt(m.id)));

        if (cardMembers.length) {
            html += '<div class="cwds-popover-section-label">Card members</div>';
            for (const member of cardMembers) {
                html += '<div class="cwds-popover-item" data-member-name="' + escHtml(member.name).toLowerCase() + '" onclick="cwdsKanbanApp.toggleMember(' + cardId + ',' + member.id + ',this)">';
                html += '<div class="cwds-user-avatar" style="background:' + escHtml(member.avatar_color) + ';width:28px;height:28px;font-size:9px;">' + getInitials(member.name) + '</div>';
                html += '<span class="cwds-popover-item-name">' + escHtml(member.name) + '</span>';
                html += '<button class="cwds-popover-item-remove" title="Remove">' + ICONS.x + '</button>';
                html += '</div>';
            }
        }

        if (otherMembers.length) {
            html += '<div class="cwds-popover-section-label">Board members</div>';
            for (const member of otherMembers) {
                html += '<div class="cwds-popover-item" data-member-name="' + escHtml(member.name).toLowerCase() + '" onclick="cwdsKanbanApp.toggleMember(' + cardId + ',' + member.id + ',this)">';
                html += '<div class="cwds-user-avatar" style="background:' + escHtml(member.avatar_color) + ';width:28px;height:28px;font-size:9px;">' + getInitials(member.name) + '</div>';
                html += '<span class="cwds-popover-item-name">' + escHtml(member.name) + '</span>';
                html += '</div>';
            }
        }

        html += '</div>';

        positionPopover(btn, html);
    }

    function filterMembers(query) {
        const q = query.toLowerCase();
        document.querySelectorAll('.cwds-popover-item[data-member-name]').forEach(item => {
            const name = item.dataset.memberName || '';
            item.style.display = name.includes(q) ? '' : 'none';
        });
    }

    async function toggleMember(cardId, memberId, el) {
        try {
            const result = await apiPost('cards/' + cardId + '/members', { member_id: memberId });

            // Update currentModal members
            if (result.action === 'added') {
                const member = boardData.members.find(m => parseInt(m.id) === memberId);
                if (member && currentModal) currentModal.members.push(member);
            } else if (currentModal) {
                currentModal.members = currentModal.members.filter(m => parseInt(m.id) !== memberId);
            }

            // Re-render the member picker in place
            const popover = document.querySelector('.cwds-member-popover');
            if (popover) {
                // Find the button that opened it to re-position
                const btn = document.querySelector('.cwds-action-btn[onclick*="showMemberPicker"]') || document.querySelector('.cwds-meta-add-btn[onclick*="showMemberPicker"]');
                if (btn) {
                    showMemberPicker(btn, cardId);
                }
            }
        } catch (err) {
            console.error('Toggle member failed:', err);
        }
    }

    function closePopovers() {
        document.querySelectorAll('.cwds-popover').forEach(p => p.remove());
    }

    /**
     * Position a popover as an absolute overlay near the triggering button
     */
    function positionPopover(btn, html) {
        const modal = btn.closest('.cwds-modal');
        if (!modal) return;

        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        const popover = wrapper.firstChild;
        modal.appendChild(popover);

        // Position relative to the button within the modal
        const btnRect = btn.getBoundingClientRect();
        const modalRect = modal.getBoundingClientRect();

        let top = btnRect.bottom - modalRect.top + 4;
        let left = btnRect.left - modalRect.left;

        // Ensure popover doesn't overflow right edge
        const popWidth = 300;
        if (left + popWidth > modalRect.width) {
            left = modalRect.width - popWidth - 12;
        }
        if (left < 12) left = 12;

        popover.style.position = 'absolute';
        popover.style.top = top + 'px';
        popover.style.left = left + 'px';
        popover.style.zIndex = '100';
    }

    // ═══════════════════════════════════════════════
    // CHECKLISTS
    // ═══════════════════════════════════════════════

    async function addChecklist(cardId) {
        const title = prompt('Checklist title:', 'Checklist');
        if (!title) return;

        try {
            await apiPost('cards/' + cardId + '/checklists', { title: title });
            // Reload modal
            openCard(cardId);
        } catch (err) {
            console.error('Add checklist failed:', err);
        }
    }

    async function addChecklistItem(checklistId, input, cardId) {
        const text = input.value.trim();
        if (!text) return;

        try {
            await apiPost('checklist-items', { checklist_id: checklistId, text: text });
            input.value = '';
            // Reload modal to update
            const card = await apiGet('cards/' + cardId);
            currentModal = card;
            renderModal(document.querySelector('.cwds-modal'), card);
        } catch (err) {
            console.error('Add checklist item failed:', err);
        }
    }

    async function toggleChecklistItem(itemId, newValue, cardId) {
        try {
            await apiPut('checklist-items/' + itemId, { is_checked: newValue });
            const card = await apiGet('cards/' + cardId);
            currentModal = card;
            renderModal(document.querySelector('.cwds-modal'), card);
        } catch (err) {
            console.error('Toggle checklist item failed:', err);
        }
    }

    // ═══════════════════════════════════════════════
    // ATTACHMENTS
    // ═══════════════════════════════════════════════

    function setupUploadZone(cardId) {
        const zone = document.getElementById('cwds-upload-zone');
        if (!zone) return;

        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            zone.classList.add('dragover');
        });
        zone.addEventListener('dragleave', function() {
            zone.classList.remove('dragover');
        });
        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            zone.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                uploadFile(cardId, e.dataTransfer.files[0]);
            }
        });
    }

    async function uploadFile(cardId, file) {
        if (!file) return;

        const formData = new FormData();
        formData.append('file', file);

        const zone = document.getElementById('cwds-upload-zone');
        const origHtml = zone.innerHTML;
        zone.innerHTML = '<div class="spinner" style="width:20px;height:20px;border-width:2px;margin:0 auto;"></div> Uploading...';

        try {
            await apiUpload('cards/' + cardId + '/attachments', formData);
            // Reload modal
            const card = await apiGet('cards/' + cardId);
            currentModal = card;
            renderModal(document.querySelector('.cwds-modal'), card);
        } catch (err) {
            console.error('Upload failed:', err);
            zone.innerHTML = origHtml;
            alert('Upload failed. Please try again.');
        }
    }

    async function deleteAttachment(attId, cardId) {
        if (!confirm('Delete this attachment?')) return;
        try {
            await apiDelete('attachments/' + attId);
            const card = await apiGet('cards/' + cardId);
            currentModal = card;
            renderModal(document.querySelector('.cwds-modal'), card);
        } catch (err) {
            console.error('Delete attachment failed:', err);
        }
    }

    // ═══════════════════════════════════════════════
    // UTILS
    // ═══════════════════════════════════════════════

    function escHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ═══════════════════════════════════════════════
    // COMMENTS
    // ═══════════════════════════════════════════════

    async function postComment(cardId) {
        const input = document.getElementById('cwds-comment-input');
        const body = input.value.trim();
        if (!body) return;

        try {
            const comment = await apiPost('cards/' + cardId + '/comments', { body: body });
            input.value = '';

            // Prepend comment to list
            const list = document.getElementById('cwds-comments-list');
            const tmp = document.createElement('div');
            tmp.innerHTML = renderComment(comment);
            list.insertBefore(tmp.firstChild, list.firstChild);
        } catch (err) {
            console.error('Post comment failed:', err);
            alert('Failed to post comment: ' + err.message);
        }
    }

    async function deleteComment(commentId, cardId) {
        if (!confirm('Delete this comment?')) return;
        try {
            await apiDelete('comments/' + commentId);
            // Reload modal
            const card = await apiGet('cards/' + cardId);
            currentModal = card;
            renderModal(document.querySelector('.cwds-modal'), card);
        } catch (err) {
            console.error('Delete comment failed:', err);
        }
    }

    // ═══════════════════════════════════════════════
    // PUBLIC API
    // ═══════════════════════════════════════════════

    window.cwdsKanbanApp = {
        showAddList,
        submitAddList,
        cancelAddList,
        showAddCard,
        submitAddCard,
        cancelAddCard,
        openCard,
        closeModal,
        updateTitle,
        editDescription,
        saveDescription,
        cancelDescription,
        execFormat,
        insertLink,
        updateDueDate,
        clearDueDate,
        toggleComplete,
        deleteCard,
        showAddToCard,
        focusDueDate,
        showLabelPicker,
        filterLabels,
        showCreateLabel,
        selectLabelColor,
        backToLabels,
        createLabel,
        toggleLabel,
        showMemberPicker,
        filterMembers,
        toggleMember,
        closePopovers,
        addChecklist,
        addChecklistItem,
        toggleChecklistItem,
        uploadFile,
        deleteAttachment,
        postComment,
        deleteComment
    };

    // Boot
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
