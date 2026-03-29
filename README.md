# CWDS Kanban Board Plugin

**Version:** 1.1.0
**Author:** Dan Lee  
**Website:** [charlestonwebsitestudio.com](https://charlestonwebsitestudio.com/)

## Description

Client-facing Kanban project management boards for WordPress. Trello-style light UI with CWDS brand accents. Magic link auth — no WordPress login required for clients.

## Changelog

### v1.1.0 — Label Delete & Duplicate Prevention
- **NEW: Delete labels.** Trash icon appears on hover for each label in the picker. Confirms before deleting. Removes label from all cards on the board.
- **NEW: `DELETE /labels/{id}` API endpoint.** Cleans up card-label associations before deleting the label.
- **FIX: Duplicate label prevention.** API now returns 409 if a label with the same title and color already exists on the board. Frontend shows a friendly error message.

### v1.0.9 — Card Actions Menu & Save Confirmation
- **NEW: Card actions menu (`...` button).** Trello-style three-dot menu in the top-right of the card modal with: Move, Copy, Share, and Archive actions.
- **NEW: Move card.** Opens a popover listing all columns — click to move the card to a different list. Current column is highlighted.
- **NEW: Copy card.** Creates a duplicate card in the same column with a customizable title.
- **NEW: Share card.** Copies the board URL to clipboard.
- **NEW: Archive card.** Replaces the old inline Delete button. Confirms before deleting.
- **NEW: Save confirmation toast.** After saving a description, a green "Saved" toast appears at the bottom of the screen with a checkmark icon, then auto-dismisses after 2 seconds.

### v1.0.8 — Add Lists & Drag-and-Drop Column Reordering
- **NEW: "Add another list" button.** Trello-style button after the last column lets you create new lists from the frontend. Click to reveal an inline form with title input and Add/Cancel.
- **NEW: Drag-and-drop column reordering.** Grab any column by its header and drag to reorder. Uses SortableJS with horizontal dragging. Column order persists via the existing `columns/reorder` API.
- **NEW: Column drag visual feedback.** Ghost (opacity) and drag (rotation + shadow) states match Trello's feel.

### v1.0.7 — Rich Text Description Editor
- **NEW: Rich text editor for card descriptions.** Trello-style toolbar with Bold, Italic, Strikethrough, Bullet list, Numbered list, and Insert link buttons.
- **NEW: Edit/Save/Cancel workflow.** Description shows as rendered HTML in read mode. Click or press "Edit" to switch to the editor. "Save" persists changes, "Cancel" reverts.
- **NEW: Clickable links in descriptions.** URLs inserted via the link button render as clickable links in both edit and read modes.

### v1.0.6 — Trello-Style Card Modal Redesign
- **NEW: "Add to Card" dropdown.** Trello-style `+ Add` button in the action bar opens a menu with Labels, Dates, Checklist, Members, and Attachment — each with icon and description.
- **NEW: Floating popover positioning.** Label and Member pickers now render as absolutely-positioned overlays anchored to the modal, instead of inline elements that broke the layout.
- **NEW: Trello-style Member picker.** Shows "Card members" (with remove ×) and "Board members" sections, with search filter. Toggle adds/removes instantly.
- **NEW: Completion circle.** Replaced the checkbox toggle with a Trello-style circle next to the card title (green checkmark when complete).
- **FIX: Popover layout breaking.** Popovers no longer push action buttons around or resize the modal. Click outside or press Escape to dismiss.
- **FIX: Members not toggling.** Member assignment now works correctly with live UI refresh.
- **CHANGE: Streamlined action bar.** `+ Add` | Dates | Checklist | Attachment. Labels and Members accessible via metadata row `+` buttons and the Add dropdown (no duplicate buttons).
- **CHANGE: Delete moved to bottom.** Admin-only delete button moved to the bottom of the left panel.

### v1.0.5 — Frontend Label Creation + Description Fix
- **NEW: Create labels from the frontend.** Label picker now includes a "Create a new label" button. Clicking it shows a Trello-style create view with: color preview bar, title input, 30-color swatch grid (5×6), and Create button. New labels auto-assign to the current card.
- **NEW: Label search.** Search field at the top of the label picker filters labels by name.
- **NEW: Trello-style label picker layout.** Labels displayed as colored pills with checkbox toggles, matching Trello's visual style.
- **FIX: Label creation no longer admin-only.** API endpoint now allows any authenticated board member to create labels (with board access verification).
- **FIX: Description field.** Textarea saves on blur via the existing updateDescription API call. Confirmed working.
- **NEW CSS: Label picker styles.** Search input, pill-style labels with checkmarks, color grid with selected state, create label sub-view with back button.

### v1.0.4 — Card Modal Redesign + Comments
- Two-panel card modal (left: card details, right: comments & activity). Comments with @mention autocomplete. 13 database tables, 20 API endpoints.

### v1.0.3 — On-Brand Design Fix
- Removed all dark/black backgrounds. Brand accents only.

### v1.0.2 — Template Fix & Error Handling
- Blank page template. Visible error alerts.

### v1.0.1 — Security & Routing Fixes
- Random slugs, public board list removed, cwds_board query param.

### v1.0.0 — Initial Release
- Full Kanban system: 12 tables, REST API, magic link auth, drag-and-drop, labels, members, due dates, checklists, attachments, activity log.
