# CWDS Kanban Board Plugin

**Version:** 1.0.5  
**Author:** Dan Lee  
**Website:** [charlestonwebsitestudio.com](https://charlestonwebsitestudio.com/)

## Description

Client-facing Kanban project management boards for WordPress. Trello-style light UI with CWDS brand accents. Magic link auth — no WordPress login required for clients.

## Changelog

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
