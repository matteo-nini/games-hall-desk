---
name: Games Palace Desk
description: Sistema di gestione cassa giornaliera per sala giochi VLT/AWP
colors:
  accent: "#3b5bdb"
  accent-weak: "#e9edfd"
  accent-ink: "#26368f"
  bg: "#eef1f7"
  surface: "#ffffff"
  surface-2: "#f6f8fc"
  surface-3: "#eef2f8"
  border: "#e4e8f0"
  border-2: "#d3d9e6"
  ink: "#1d2733"
  muted: "#69748a"
  faint: "#98a2b3"
  status-ok: "#15924f"
  status-ok-bg: "#e6f6ec"
  status-ok-border: "#bfe6cd"
  status-bad: "#cf3535"
  status-bad-bg: "#fbe9e9"
  status-bad-border: "#f3c7c7"
  status-warn: "#a96a09"
  status-warn-bg: "#fdf2dd"
typography:
  display:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif"
    fontSize: "22px"
    fontWeight: 700
    lineHeight: 1.15
    letterSpacing: "normal"
  headline:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif"
    fontSize: "17px"
    fontWeight: 700
    lineHeight: 1.2
  title:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif"
    fontSize: "15px"
    fontWeight: 600
    lineHeight: 1.35
  body:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif"
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif"
    fontSize: "12px"
    fontWeight: 700
    lineHeight: 1
    letterSpacing: "0.35px"
  data:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif"
    fontSize: "18px"
    fontWeight: 700
    lineHeight: 1.15
    fontFeature: "tnum"
rounded:
  lg: "14px"
  md: "10px"
  sm: "8px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "16px"
  lg: "24px"
  xl: "40px"
components:
  button-primary:
    backgroundColor: "{colors.accent}"
    textColor: "#ffffff"
    rounded: "{rounded.sm}"
    padding: "10px 18px"
  button-primary-hover:
    backgroundColor: "{colors.accent}"
    textColor: "#ffffff"
    rounded: "{rounded.sm}"
    padding: "10px 18px"
  button-ghost:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.sm}"
    padding: "10px 18px"
  button-ghost-hover:
    backgroundColor: "{colors.surface-2}"
    textColor: "{colors.ink}"
    rounded: "{rounded.sm}"
    padding: "10px 18px"
  button-amber:
    backgroundColor: "{colors.status-warn}"
    textColor: "#ffffff"
    rounded: "{rounded.sm}"
    padding: "10px 18px"
  input-default:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.sm}"
    padding: "8px 10px"
  input-focus:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.sm}"
    padding: "8px 10px"
---

# Design System: Games Palace Desk

## 1. Overview

**Creative North Star: "The Reliable Register"**

The visual system is built on earned trust through daily repetition. An operator opens this interface at 6 AM, again at 2 PM, and again at midnight — same layout, same color vocabulary, same status signals. The interface earns credibility by never surprising the person using it. Every session starts the same way. Every shift ends the same way. The UI disappears into the task.

This system explicitly rejects the ERP gestionale aesthetic — dense rows of same-weight text, no visual hierarchy, form controls that look and feel like 2005 software (SAP, Zucchetti, the old Agenzia delle Entrate panels). It equally refuses the SaaS dashboard trap: metric-hero widgets, heavy accent saturation, motion that exists to impress rather than inform. No pastels, no illustration, no micro-animations that slow the operator in flow.

What remains is a clean, tactile operational surface. A single cobalt accent used sparingly as a reference signal. Semantic colors (green, amber, red) that carry the entire state vocabulary without ambiguity. Surface layering that establishes hierarchy without relying on shadow drama. The system's only luxury is that every number is effortlessly readable.

**Key Characteristics:**
- Numeric clarity above all: tabular-nums everywhere money appears; clear hierarchy between operator input and system-calculated output
- State speaks before style: the status bar (ok/warn/bad) is the primary UI element — nothing competes with it visually or spatially
- Tonal layering for depth: hierarchy through surface steps (bg → surface → surface-2), not through shadow escalation
- One operational accent used only for actions, selection, and focus — never decoration
- Touch-aware density: 44px minimum targets, compact panels, maximum information per viewport

## 2. Colors: The Operational Palette

A cool-neutral background palette anchored by a single operational blue. The accent appears only where the operator needs to act or navigate; semantic colors carry the entire state vocabulary.

### Primary
- **Blu Operativo** (#3b5bdb): The reference color. Used exclusively for interactive affordances: primary buttons, active nav underlines, focus rings, turn-tab active text, inline links. Its rarity is its power — when blue appears, there is something to do or somewhere to go. It never decorates.

### Neutral
- **Ink** (#1d2733): Primary text. Near-black with a cool undertone that reads naturally against the blue-tinted page background without the harshness of pure black.
- **Muted** (#69748a): Labels, field names, secondary data, nav links at rest, table column headers. Quiet but confirmed legible (≥4.5:1 on white).
- **Faint** (#98a2b3): Chevrons, timestamps, non-essential metadata. Used only on confirmed-legible backgrounds (--surface or --bg). Never for primary or secondary text.
- **Page Background** (#eef1f7): The canvas. Cool-tinted neutral — never warm. Provides visual separation between the page and elevated panels.
- **Surface** (#ffffff): Panel and card backgrounds. Maximum contrast for ink text. The primary workspace surface.
- **Surface-2** (#f6f8fc): Calculated metric cards (.mini), table headers, disabled fields. One step above surface — marks read-only computed output.
- **Surface-3** (#eef2f8): Tab container backgrounds, selected-area highlighting. Two steps above surface.
- **Border** (#e4e8f0): Primary dividers. Panel perimeters, table row separators, section lines.
- **Border-2** (#d3d9e6): Input field strokes at rest, stronger separators. More visible than Border but not aggressive.

### Semantic (State)
- **Status Ok** (#15924f): Positive reconciliation, open-day status, loan-repayment badge. Always paired with --status-ok-bg (#e6f6ec) fill and --status-ok-border (#bfe6cd) perimeter.
- **Status Warn** (#a96a09): Scostamento 4–5€ range; the close-shift button color. Tolerable deviation — act soon but not urgent. Paired with --status-warn-bg (#fdf2dd).
- **Status Bad** (#cf3535): Scostamento >5€, error messages, outstanding-loan badges. Paired with --status-bad-bg (#fbe9e9) and --status-bad-border (#f3c7c7).

### Named Rules
**The State Vocabulary Rule.** Green, amber, and red are reserved for reconciliation state, status indicators, and feedback — never decoration. Any new component needing emphasis reaches for --accent-weak or --surface-2 instead. Each semantic color is always deployed as a trio: foreground + `*-bg` + `*-border`. Never apply a semantic color in isolation.

**The One Voice Rule.** Blu Operativo (#3b5bdb) covers ≤10% of any given screen surface. When the accent appears everywhere, it disappears. Reserve it strictly for: primary action buttons, active navigation underlines, focus rings, active tab labels, and text links.

## 3. Typography

**UI Font:** System stack — `-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif`
**Data Display:** Same family with `font-variant-numeric: tabular-nums` applied to all monetary, numeric, and metric values.

**Character:** A single family across all roles. System fonts match native device rendering — no flash of unstyled text at 6 AM, no web font load on a slow connection at the cash desk. The scale is deliberately compressed (1.15–1.2 between steps): typographic drama at this density level competes with the numbers and always loses.

### Hierarchy
- **Display** (700, 22px, 1.15): Page identity — the sala name on the dashboard, the app title on the login screen. Appears once per screen, never repeated.
- **Headline** (700, 17px, 1.2): Turn-level headings (Mattino / Sera), panel-level group names. One or two per section at most.
- **Title** (600, 15px, 1.35): Panel section headers (h3), form-group names within panels. The internal label for a workspace.
- **Body** (400, 14px, 1.5): All prose, field values at rest, notes textarea content, list item text. Cap at 65ch for onboarding and guide text.
- **Label** (700, 11–12px, uppercase, letter-spacing 0.35–0.5px): Section dividers (.subhead, .seg), table column headers, status tag text. Used within panels as organizational markers only — prohibited as page-level eyebrow headings.
- **Data** (700, 14–22px, tabular-nums): Monetary values, subtotals, KPI numbers (.mini .v, .statusbar .big, .metric .v). The numbers operators look for first — always the heaviest weight, always tabular.

### Named Rules
**The Tabular Numbers Rule.** Every monetary value, count, percentage, or quantity uses `font-variant-numeric: tabular-nums` without exception. Columns of live-updating numbers must not shift horizontally between values.

**The Scale Ceiling Rule.** No heading on this interface exceeds 26px. This is an operational tool with dense numeric data — typographic scale is a function of information hierarchy, not visual impact.

## 4. Elevation

Hybrid elevation: one ambient shadow token carries structural hierarchy; surface color steps reinforce depth within panels. The shadow vocabulary has exactly one entry. There is no hover-lift, no focus-lift, no modal-glow escalation.

Depth by layer:
1. **Page canvas** (--bg, #eef1f7) — the floor; never interactive, never receives shadow
2. **Panel workspace** (--surface, #ffffff + `--sh`) — elevated data-entry and display surfaces; the primary work layer
3. **Sub-panel metric** (--surface-2, #f6f8fc) — nested inside panels; marks computed output rather than operator input
4. **Floating overlay** (fixed position, z-index 60) — toast notifications only; floats above everything

### Shadow Vocabulary
- **Ambient panel** (`0 1px 2px rgba(20,30,55,.05), 0 2px 8px rgba(20,30,55,.05)`): The only shadow. Applied to `.panel`, `.riepilogo`, `.statusbar`, `.ticket-card`, `.recent-list`, `.login-box`. Dual-layer, very low opacity — defines presence without announcing it.

### Named Rules
**The Flat-By-Default Rule.** Elements at the same depth level differentiate themselves through surface color steps, not shadow variations. Shadow exclusively signals a depth-level change (panel above canvas, overlay above panel).

**The One Shadow Rule.** There is one shadow token. It does not vary by component type, state, or context. The system conveys depth through surface color and spatial separation, not shadow escalation.

## 5. Components

### Buttons
Tactile and legible: padding gives comfortable tablet targets, radius is small enough to feel professional rather than playful.

- **Shape:** Gently rounded (8px radius)
- **Primary:** Blu Operativo fill (#3b5bdb), white text, 10px × 18px padding, 600 weight. `filter: brightness(.95)` on hover — no color change, no lift.
- **Focus:** 3px ring in --accent-weak (#e9edfd) outside the button border. Visible on keyboard navigation; absent on pointer interaction.
- **Ghost:** White (#ffffff) fill, ink text (#1d2733), --border-2 stroke (#d3d9e6). For secondary and contextual actions (date navigation, page topbar).
- **Amber (Close):** --status-warn fill (#a96a09), white text. Signals an irreversible-ish shift-close action.
- **Muted (Reopen):** --muted fill (#69748a), white text. Admin recovery actions only.
- **Disabled:** 50% opacity, `cursor: not-allowed`. No color change.

### Inputs / Fields
Forms feel fillable: full border perimeter (not underline-only), white background, readable size.

- **Style:** White background, --border-2 stroke (1px, #d3d9e6), 8px radius, 8px × 10px padding, 14px text. Numeric inputs right-aligned; text and select inputs left-aligned.
- **Focus:** --accent border (#3b5bdb, 1px), 3px outer glow in --accent-weak (#e9edfd). Instant switch — no animation.
- **Disabled:** --surface-3 background (#eef2f8), --muted text (#69748a). Clearly non-interactive.
- **Width default:** 110px for numeric currency fields. Override per context.

### Cards / Containers (Panel)
The primary organizational unit. All data entry and display lives inside a panel. Panels are workspaces, not interactive elements.

- **Corner Style:** Gently curved (14px radius)
- **Background:** White (#ffffff)
- **Shadow:** Ambient panel shadow (`--sh`) always applied. Never removed, never increased.
- **Border:** 1px --border (#e4e8f0), full perimeter
- **Internal Padding:** 14–18px vertical, 16–18px horizontal

### Mini Metric Cards (.mini)
Compact read-only calculated outputs — Cassetto, Versamento, Totale Cassa, Scostamento.

- **Background:** --surface-2 (#f6f8fc) — one step above the parent panel; signals computed, not entered
- **Radius:** 10px
- **Padding:** 10px × 12px
- **Label:** 12px body, --muted text
- **Value:** 18px data, 700, tabular-nums
- **Error state:** --status-bad-bg fill (#fbe9e9), --status-bad text (#cf3535) when scostamento exceeds tolerance

### Status Bar
The primary operator communication channel. One of three states at all times; sticky below the navigation bar.

- **Ok:** --status-ok-bg (#e6f6ec) fill, --status-ok-border stroke, green icon circle (#15924f), dark green value text (#0f6b3a)
- **Warn:** --status-warn-bg (#fdf2dd) fill, #f0d59a stroke, amber icon circle (#a96a09), dark amber text (#8a5708)
- **Bad:** --status-bad-bg (#fbe9e9) fill, --status-bad-border stroke, red icon circle (#cf3535), dark red text (#9c2222)
- Positioned `sticky, top: 55px`; always visible while scrolling data entry.

### Navigation
- **Style:** White surface, 1px --border bottom, 55px height. `position: sticky, top: 0, z-index: 20`.
- **Brand mark:** 700 weight ink text, blue accent dot separator
- **Links at rest:** --muted (#69748a), 500 weight, 14px
- **Links on hover:** --ink (#1d2733)
- **Active link:** --accent text (#3b5bdb), 2px --accent bottom border. No fill, no background change.

### Turn Tabs (.tab)
Mattino / Sera context switcher. Color differentiates active turn without reading the label.

- **Container:** --surface-3 fill, --border stroke, 12px radius, 5px inner gap
- **Inactive:** Transparent fill, --muted text
- **Active Mattino:** White fill, ambient shadow, --status-warn text and label (#a96a09)
- **Active Sera:** White fill, ambient shadow, --accent text and label (#3b5bdb)

### Toast
Save-success feedback. The only choreographed motion in the system.

- **Style:** --status-ok fill (#15924f), white text, 12px radius, 600 weight, circular icon left
- **Position:** Fixed, top-right (18px from each edge), z-index 60
- **Motion:** Slide in from top + fade in (350ms ease-out). Auto-dismiss after ~3s with reverse. Instant crossfade under `prefers-reduced-motion`.

### Signature Component: Statusbar Equation Display
The statusbar shows two values and an equals sign when reconciled — the visual statement that the shift is square.

- **Layout:** Icon (46px circle) + big value + label on left; comparison cluster (totale = fondo, with ≈ or ✓ symbol) on right, auto-margin separated
- **Eq symbol:** 26px, 700, colored per state (green/amber/red)
- **Values:** 22px, 700, tabular-nums
- **Sub-labels:** 11px, uppercase, 0.4px tracking, --muted

## 6. Do's and Don'ts

### Do:
- **Do** use `font-variant-numeric: tabular-nums` on every monetary value, count, or metric. No exceptions.
- **Do** deploy semantic colors as a trio: foreground + `*-bg` + `*-border` (e.g. --status-ok + --status-ok-bg + --status-ok-border). Never apply a state color in isolation on a neutral surface.
- **Do** keep Blu Operativo (#3b5bdb) off inactive states, section headers, and decorative elements. It appears only on: primary action buttons, active navigation underlines, focus rings, active turn-tab text, and text links.
- **Do** use 44px minimum height for all interactive elements — buttons, tab items, nav links — to ensure reliable tablet usability at the cash desk.
- **Do** maintain the surface stack: page (--bg) → panel (--surface + shadow) → sub-panel (--surface-2). Do not skip levels or add a fourth.
- **Do** keep the statusbar sticky — operators need reconciliation state visible while scrolling the data entry panels below it.
- **Do** apply `text-wrap: balance` on h2–h3 headings to prevent awkward single-word orphan lines on tablet widths.

### Don't:
- **Don't** reproduce the ERP/gestionale aesthetic: no equal-weight text at every level, no zero-whitespace density, no form controls that look and feel like pre-2010 software. Zucchetti and SAP are the floor, not the bar.
- **Don't** add decorative motion. No entrance animations for panels or cards, no hover-lift on .panel, no loading sequences. Motion in this system is reserved for state transitions: toast appear/dismiss and the details-chevron rotation.
- **Don't** use `border-left` or `border-right` greater than 1px as a colored accent stripe on cards, callouts, list items, or alert tips. Rewrite with a full border + tinted background (the semantic trio pattern).
- **Don't** apply a semantic color (status-ok, status-warn, status-bad) to any component that does not carry reconciliation state, open/closed status, or user feedback. New components with emphasis needs reach for --accent-weak or --surface-2 instead.
- **Don't** introduce a second accent hue. The system has one: Blu Operativo. Emphasis comes from weight, size, and surface contrast — not from adding colors.
- **Don't** build metric/KPI hero layouts: a big number + small label + gradient accent is the SaaS dashboard cliché the system explicitly rejects. Metric values live inside compact .mini cards or the statusbar, not as full-screen hero elements.
- **Don't** display monetary values without tabular-nums, even on a static display. Columns shift during live JS recalculation and break the operator's horizontal reading line.
- **Don't** put text in --muted (#69748a) or --faint (#98a2b3) against a tinted semantic background (--status-ok-bg, --status-bad-bg, --status-warn-bg). Use the darker foreground variant of that hue (--status-ok, --status-bad, --status-warn) instead.
