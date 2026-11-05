---
name: update-roadmap
description: Check the real state of the project (git, repo files, and this conversation) against docs/planning/09-roadmap.md, update its status/checkboxes and append any newly-decided items, then regenerate docs/planning/roadmap.html so it stays a faithful browsable view of the same file. Run this after finishing a chunk of work, wrapping up a planning discussion, or whenever the user wants a status check. Takes no arguments and asks no questions of the user — it's a trigger, not a command with parameters.
---

# Update Roadmap

Keeps two things in sync with reality, in this order:

1. `docs/planning/09-roadmap.md` — the source of truth. Markdown stays the internal memory for this project; never treat the HTML file as authoritative.
2. `docs/planning/roadmap.html` — a static, self-contained, offline-openable dashboard regenerated *from* the markdown, for a nicer read than raw text.

This skill is a trigger, not a form: don't ask the user for input before running it. Pull everything you need from repo state and from whatever's already been discussed in the current conversation.

## Hard rules

- **Never run `git add` / `git commit` / `git push` as part of this skill.** Leave every change as an uncommitted working-tree edit and say so in your summary. Committing is the user's call, made explicitly, separately.
- **Never guess "done."** A roadmap that overstates progress is worse than a stale one. Only check a box off when you've verified concrete evidence for it (see below). If you're not sure, leave it unchecked and say why in an evidence note rather than assuming the best case.
- **A box means merged to `main`, not "written somewhere."** Code sitting on an open, unmerged branch is real progress — note it inline (which branch, whether a PR exists) — but don't check the box until it's actually in `main`. This distinction is the entire point of tracking status separately from just reading source code.
- **Don't invent new scope.** "Adding new items" means recording something that was *actually decided* in this conversation or is *already implied* by the existing planning docs but missing from the roadmap — not brainstorming new features on this skill's own initiative.

## Step 1 — Gather evidence

Run these (adjust paths/branch names as needed):

```bash
git branch --show-current
git status --short
git log --oneline -15
git log main --oneline -5              # what's actually shipped
git log main..HEAD --oneline           # what's only on this branch, if not on main
gh pr list --state all                 # if gh is available — is there an open/merged PR?
```

Then, for whatever roadmap items are plausibly in flight, check for concrete evidence rather than trusting memory:

- Migrations/models: `ls backend/database/migrations`, `ls backend/app/Models`, `ls backend/app/Enums`
- Routes/controllers: `ls backend/routes`, `ls backend/app/Http/Controllers`, `cat backend/routes/api.php` if it exists
- Console commands (e.g. a first-admin bootstrap): `ls backend/app/Console/Commands`
- Dependencies: `grep <package> backend/composer.json`, `grep <package> frontend/package.json`
- Seeders: `ls backend/database/seeders`, read `DatabaseSeeder.php`
- Frontend build-out: `find frontend/src -maxdepth 2 -type d`, check for custom components/theme files beyond the `create-vue` scaffold defaults
- Anything else a specific roadmap line calls for — read the actual file, don't infer from a filename alone

Also read `docs/planning/09-roadmap.md` (current state) and `docs/planning/README.md`'s "Decisions locked in during planning" list.

Finally, review this conversation itself: was anything decided, built, or scoped in this session that isn't yet reflected in either doc? That's the "context from work in this chat" the roadmap update is for — it's often the most important input, more current than anything git will show you if changes haven't been committed yet.

## Step 2 — Update `docs/planning/09-roadmap.md`

Conventions already established in the file (keep using them):

- Every phase has a `**Status:**` line right under its heading: `Complete`, `In Progress`, or `Not Started`.
- Every actionable line is a GitHub-style checkbox: `- [ ]` or `- [x]`.
- A boundary/context statement that isn't really a task (e.g. "no frontend yet beyond a minimal login page") stays as italic prose, not a checkbox — don't force everything into the checklist just for consistency.
- Where a box is partially done or done-but-unmerged, keep the box unchecked and append an em-dash evidence clause explaining exactly what exists and what's missing (see the Phase 1 section for the pattern). Update these clauses as things change — don't just append more without pruning what's now stale.
- Where useful, a `*(italic note)*` line directly under a phase's `**Status:**` line carries phase-level context (e.g. which branch the work lives on, or a scope clarification) — see Phase 1 and Phase 11 for examples.
- If new work was genuinely decided in this session and belongs on an existing phase, add a bullet there, matching the terse style of the surrounding lines. If it doesn't fit any existing phase and is substantial enough to need one, add a new phase section following the same `## Phase N — Title` / `**Status:**` / checklist structure — this should be rare.
- If a locked-in decision surfaced in this session isn't yet in `docs/planning/README.md`'s decisions list (or in the relevant numbered doc), append it there too, per the "Continuing the planning docs" section of `CLAUDE.md`. Cross-link with relative markdown links the way the rest of the docs do.

## Step 3 — Regenerate `docs/planning/roadmap.html`

The file already has a full design system baked in (Passport & Postmark palette/type, matching the rest of `docs/planning/`) and doesn't need to be redesigned each run — only its *content* changes. To keep every regeneration visually identical:

- Leave the `<style>` block, the `<head>`, and the overall page skeleton (cover, stats grid, phase-nav, footer) untouched.
- Only rewrite the content inside the three marker pairs: `<!-- META:START --> ... <!-- META:END -->`, `<!-- STATS:START --> ... <!-- STATS:END -->`, and `<!-- PHASES:START --> ... <!-- PHASES:END -->`.
- `META` holds the generated date, current branch, and the source file path.
- `STATS` holds four tiles: phases complete, phases in progress, phases not started, and a `checked / total` count of every checkbox across the whole roadmap (count them from the markdown, don't estimate).
- `PHASES` holds one `<section class="phase" id="phase-N">` per phase, copying the structure already used for Phase 0–12 in the current file:
  - Add `is-complete` or `is-progress` to the section's class (omit both for Not Started).
  - `.phase-tag` = `PHASE N`, `.phase-title` = the phase name, `.phase-frac` = `checked / total` for that phase (omit if the phase has no checkboxes, like Phase 0).
  - `.phase-status` text = `Complete` / `In Progress` / `Not Started`, with the matching class.
  - An optional `.phase-note` (italic, left-border) for phase-level context, mirroring `**Status:**`'s italic note in the markdown.
  - `ul.checklist` with one `<li>` per checkbox line (add class `done` for checked items); an item's evidence clause goes in a nested `<span class="evidence">`.
  - A prose-only boundary line (not a checkbox in the markdown) becomes a `<p class="callout">`.
  - Update the `.phase-nav` chip for each phase to class `complete` / `progress` / (no class) to match.
- Keep the whole file a plain static document — no external requests, no CDN scripts, no build step. It has to open correctly by double-clicking the file with no network and no dev server running.

## Step 4 — Report

Summarize, briefly:

- What changed (checkboxes flipped, phases re-statused, new items or decisions added, or "nothing changed, roadmap already matched reality")
- Anything you left unchecked/uncertain and why
- A reminder that nothing was committed, if you touched files

Do not create a git commit or push as part of this, ever, regardless of what earlier turns in the conversation did.
