---
version: alpha
name: Interstellar
website: "https://www.illumina.com"
description: A calm, dense laboratory operations system for Illumina Interstellar — cool grey canvas (#f1f3f8), Inter at light 300 for titles and regular 400 for everything else, and a single brand blue (#225de6) reserved for primary actions, links, and selected navigation. Surfaces are soft-sheeted at 18px (`{rounded.4xl}`) with one light card shadow; buttons are fully pill-shaped; tables run dense 44px rows. Seven page templates and a 53-component catalog cover every operational screen — Home, Samples, Runs, Fleet, Workflows, wizards, and authentication — with state always visible (loading, empty, error, disabled).

seo:
  title: "Interstellar Design System: Brand Blue, Inter Light Titles and Dense Lab Sheets"
  metaDescription: "Interstellar's look in one file: brand #225de6 used for actions only, Inter at 300/400/500, 18px sheet radii, 44px table rows, 7 page templates and 53 components for React and AI tools."
  tags:
    - "Life Sciences & Lab Software"
    - "Enterprise Operations"
  highlights:
    - "Single brand voltage — #225de6 for actions, links and selected nav; never decorative fills"
    - "Light titles, dense body — Inter 300 at 24–32px for headings; Inter 400 at 13–15px for controls and tables"
    - "Soft 18px page sheets on cool grey canvas — white surfaces, light card shadow, pill buttons"
    - "Seven fixed page templates — Application shell, Dashboard, Inventory, Fleet, Details, Guided task, Authentication"
    - "State always visible — loading, empty, error and disabled are required; every list needs an empty state"
  lastUpdated: "2026-10-31"
  author:
    name: "Dov Azencot"
    url: "https://x.com/dovazencot"
  opening: |
    Interstellar's design system is built around one idea: calm density. One brand blue (#225de6). One typeface (Inter) at three weights — 300 for titles, 400 for everything else, 500 for numbers that matter. Soft 18px sheets on a cool grey canvas. No decorative brand washes. No invented page skeletons.

    This page packages all of that into a single DESIGN.md file. Inside: 48 colors, 11 type styles, 12 spacing values, 11 corner radii, 10 elevation tiers, 7 page templates, and 53 components — every piece you need to build something that looks like Interstellar laboratory software.

    Download the file and feed it to Claude, Cursor, or GitHub Copilot. The AI writes React components and Tailwind classes that match Interstellar's voice — not a generic SaaS dashboard. Or just use it as a reference for your own work.
  related:
    - href: "https://github.com/google-labs-code/design.md"
      title: "The DESIGN.md specification"
      description: "Google Labs' open spec for machine-readable design system files — the format this page is built on."
    - href: "/design"
      title: "Browse all design systems"
      description: "The full directory of DESIGN.md files on shadcn.io, with live mockups for each."
    - href: "/blocks"
      title: "React blocks for shadcn/ui"
      description: "Production-ready hero, pricing, CTA, and dashboard sections built with the same Tailwind + shadcn primitives."
  questions:
    - id: "primary-color"
      title: "What is Interstellar's primary brand color?"
      answer: "Interstellar's brand color is #225de6 — a saturated lab blue. It is reserved for primary actions, links, and the selected navigation item. Filled CTAs use a slightly brighter brand-action (#426aeb) that hovers to #3558d4. Three brand washes are not interchangeable: brand-tint (#eef2ff) fills active chips and selected rows; brand-soft (8% brand) fills the selected nav item; brand-ghost (6% brand) is hover only. Brand is never used for decoration."
    - id: "dark-mode"
      title: "Does Interstellar's design system have a dark mode?"
      answer: "No — Interstellar is light-only. The application canvas is cool grey #f1f3f8, page sheets and cards are pure white #ffffff, raised dashboard sheets are #fbfcfd with a white inner ring, and primary ink is #242936. There is no dark theme in the shipped token set."
    - id: "typography"
      title: "What typography does Interstellar use?"
      answer: "One family: Inter (`'Inter', ui-sans-serif, system-ui, sans-serif`). Only three weights ship — 300 (headings only: 32px list titles, 28px detail titles, 24px sheet titles, 18px empty-state headings), 400 (everything else: 15px auth fields, 14px controls and body, 13px dense tables and chips, 11–12px metadata), and 500 (numbers that matter and status chips). Sentence case everywhere except table column headings, which are 11px uppercase with letter-spacing in muted ink (#919297). Mono is reserved for wells, identifiers, and code."
    - id: "page-templates"
      title: "Why must every screen use one of seven page templates?"
      answer: "Interstellar locks layout so agents and engineers do not invent new skeletons. The seven templates are Application shell, Dashboard grid, Inventory, Fleet inventory, Details, Guided task flow, and Authentication. Every operational screen maps onto one of them — adapt content, not structure. The authenticated shell is a fixed 48px brand header above a two-column body (80px collapsed / 220px expanded nav + scrollable viewport)."
    - id: "use-in-project"
      title: "Can I use this DESIGN.md to build my own React lab product?"
      answer: "Yes — the file is designed to be fed into Claude, Cursor, or any AI tool that reads structured design tokens. The agent will reproduce Interstellar's restraint (brand for actions only, light titles, dense 44px tables, pill buttons, soft 18px sheets) rather than a generic shadcn theme. Pair it with the portable package tokens (`tokens.css`, Tailwind v4 mapping) and `react/primitives.tsx` for ready atoms."
    - id: "known-gaps"
      title: "What's missing from this DESIGN.md spec?"
      answer: "A handful of things, documented in the Known Gaps section: the full SVG icon set (icons.tsx is referenced but not inlined here), photographic thumbnail asset paths, Radiance toast elevation details beyond the soft blue shadow token, State switcher internals (testing-only), and motion choreography beyond the four duration tokens. The spec captures the full public token surface and the 53-component catalog."

colors:
  canvas: "#f1f3f8"
  surface: "#ffffff"
  surface-raised: "#fbfcfd"
  surface-subtle: "#f9fafc"
  surface-muted: "#fafbfd"
  ink: "#242936"
  ink-secondary: "#656972"
  ink-table: "#374151"
  ink-muted: "#919297"
  ink-idle: "#4a4f59"
  placeholder: "#b0b3bc"
  border: "#e2e4e9"
  border-muted: "#e8eaef"
  border-strong: "#c8cad4"
  border-row: "#f5f6fa"
  brand: "#225de6"
  brand-action: "#426aeb"
  brand-hover: "#3558d4"
  brand-tint: "#eef2ff"
  brand-wash: "#ebf1ff"
  brand-soft: "rgba(34, 93, 230, 0.08)"
  brand-ghost: "rgba(34, 93, 230, 0.06)"
  focus: "#426aeb"
  avatar-bg: "#ebf1ff"
  avatar-fg: "#052776"
  success: "#16a34a"
  success-bright: "#22c55e"
  success-soft: "#f0fdf4"
  success-border: "#bbf7d0"
  warning: "#c34709"
  warning-soft: "#fff2eb"
  warning-fleet: "#e15c19"
  urgent: "#dc2626"
  urgent-soft: "#fef2f2"
  urgent-muted: "#656972"
  urgent-muted-soft: "#f1f3f8"
  danger: "#dc2626"
  danger-hover: "#b91c1c"
  danger-strong: "#ef4444"
  danger-soft: "#fee2e2"
  danger-surface: "#fef2f2"
  danger-tint: "#fff5f5"
  danger-border: "#fecaca"
  status-neutral: "#f8f9fc"
  sample-dna: "#ea7600"
  sample-rna: "#2563eb"
  white: "#ffffff"
  overlay: "rgba(20, 25, 38, 0.42)"
  gradient-header: "radial-gradient(ellipse at 40% 0%, #6284f8 0%, #5276eb 57%, #d2a1c5 100%)"
  gradient-spotlight: "radial-gradient(161.26% 116.78% at -7.15% -15.52%, #9c7df2 0%, rgba(156, 125, 242, 0) 100%) over #426aeb"
  gradient-header-sheen: "linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.1) 53%, transparent 100%)"

typography:
  display:
    fontFamily: "'Inter', ui-sans-serif, system-ui, sans-serif"
    fontSize: 32px
    fontWeight: 300
    lineHeight: 1.2
    letterSpacing: 0
  title-lg:
    fontFamily: "'Inter', ui-sans-serif, system-ui, sans-serif"
    fontSize: 28px
    fontWeight: 300
    lineHeight: 1.2
    letterSpacing: 0
  title:
    fontFamily: "'Inter', ui-sans-serif, system-ui, sans-serif"
    fontSize: 24px
    fontWeight: 300
    lineHeight: 1.2
    letterSpacing: 0
  title-code:
    fontFamily: "'Inter', ui-sans-serif, system-ui, sans-serif"
    fontSize: 22px
    fontWeight: 500
    lineHeight: 1.2
    letterSpacing: 0
  title-sm:
    fontFamily: "'Inter', ui-sans-serif, system-ui, sans-serif"
    fontSize: 18px
    fontWeight: 300
    lineHeight: 1.3
    letterSpacing: 0
  body-xl:
    fontFamily: "'Inter', ui-sans-serif, system-ui, sans-serif"
    fontSize: 16px
    fontWeight: 400
    lineHeight: 1.2
    letterSpacing: 0
  body-lg:
    fontFamily: "'Inter', ui-sans-serif, system-ui, sans-serif"
    fontSize: 15px
    fontWeight: 400
    lineHeight: 1.4
    letterSpacing: 0
  body:
    fontFamily: "'Inter', ui-sans-serif, system-ui, sans-serif"
    fontSize: 14px
    fontWeight: 400
    lineHeight: 1.4
    letterSpacing: 0
  body-sm:
    fontFamily: "'Inter', ui-sans-serif, system-ui, sans-serif"
    fontSize: 13px
    fontWeight: 400
    lineHeight: 1.4
    letterSpacing: 0
  label-sm:
    fontFamily: "'Inter', ui-sans-serif, system-ui, sans-serif"
    fontSize: 12px
    fontWeight: 400
    lineHeight: 1.4
    letterSpacing: 0
  caption:
    fontFamily: "'Inter', ui-sans-serif, system-ui, sans-serif"
    fontSize: 11px
    fontWeight: 400
    lineHeight: 1.4
    letterSpacing: 0.02em
    textTransform: uppercase
  mono:
    fontFamily: "ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace"
    fontSize: 13px
    fontWeight: 400
    lineHeight: 1.4
    letterSpacing: 0

rounded:
  micro: 4px
  xs: 6px
  sm: 7px
  component: 8px
  lg: 10px
  xl: 12px
  2xl: 14px
  3xl: 16px
  4xl: 18px
  5xl: 20px
  pill: 999px

spacing:
  0: 0px
  1: 4px
  2: 8px
  3: 12px
  4: 16px
  5: 20px
  6: 24px
  8: 32px
  10: 40px
  12: 48px
  16: 64px
  20: 80px

shadow:
  card: "0 1px 4px rgba(0, 0, 0, 0.05)"
  card-subtle: "0 1px 4px rgba(0, 0, 0, 0.04)"
  menu: "0 4px 24px rgba(0, 0, 0, 0.12)"
  popover: "0 4px 24px rgba(0, 0, 0, 0.14)"
  tooltip: "0 4px 24px rgba(0, 0, 0, 0.16)"
  toast: "0 4px 24px rgba(0, 0, 0, 0.13)"
  panel: "0 4px 32px rgba(0, 0, 0, 0.16)"
  dialog: "0 24px 64px rgba(0, 0, 0, 0.18)"
  drawer: "0 0 40px rgba(0, 0, 0, 0.18)"
  icon: "0 0 1.5px rgba(0, 0, 0, 0.36)"

layout:
  header-height: 48px
  page-header-height: 68px
  tab-height: 50px
  sidenav-collapsed: 80px
  sidenav-expanded: 220px
  content-max: 1440px
  table-row: 44px
  dashboard-row: 76px
  control-height: 44px
  control-height-md: 36px
  control-height-sm: 32px

motion:
  fast: 120ms
  standard: 180ms
  slow: 280ms
  toast: 320ms
  easing: "cubic-bezier(0.2, 0, 0, 1)"
  easing-linear: linear

components:
  button-primary:
    backgroundColor: "{colors.brand-action}"
    textColor: "{colors.white}"
    typography: "{typography.body}"
    rounded: "{rounded.pill}"
    padding: 0 16px
    height: 36px
  button-primary-lg:
    backgroundColor: "{colors.brand-action}"
    textColor: "{colors.white}"
    typography: "{typography.body-lg}"
    rounded: "{rounded.pill}"
    padding: 0 24px
    height: 44px
  button-primary-sm:
    backgroundColor: "{colors.brand-action}"
    textColor: "{colors.white}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.pill}"
    padding: 0 12px
    height: 32px
  button-outline:
    backgroundColor: transparent
    textColor: "{colors.brand}"
    typography: "{typography.body}"
    rounded: "{rounded.pill}"
    height: 36px
  button-ghost:
    backgroundColor: transparent
    textColor: "{colors.brand}"
    typography: "{typography.body}"
    rounded: "{rounded.pill}"
    height: 36px
  button-soft-outline:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.brand}"
    typography: "{typography.body}"
    rounded: "{rounded.pill}"
    height: 34px
  button-destructive:
    backgroundColor: transparent
    textColor: "{colors.danger}"
    typography: "{typography.body}"
    rounded: "{rounded.pill}"
  button-destructive-filled:
    backgroundColor: "{colors.danger}"
    textColor: "{colors.white}"
    typography: "{typography.body}"
    rounded: "{rounded.pill}"
  icon-button:
    backgroundColor: transparent
    textColor: "{colors.brand}"
    rounded: "{rounded.xs}"
    height: 32px
  icon-button-active:
    backgroundColor: "{colors.brand-tint}"
    textColor: "{colors.brand}"
    rounded: "{rounded.xs}"
    height: 32px
  text-field-auth:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    typography: "{typography.body-lg}"
    rounded: "{rounded.lg}"
    padding: 0 12px
    height: 44px
  text-field-form:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    typography: "{typography.body}"
    rounded: "{rounded.component}"
    height: 36px
  text-field-compact:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.sm}"
    height: 30px
  avatar-header:
    backgroundColor: "{colors.avatar-bg}"
    textColor: "{colors.avatar-fg}"
    rounded: "{rounded.pill}"
    height: 24px
  avatar-menu:
    backgroundColor: "{colors.avatar-bg}"
    textColor: "{colors.avatar-fg}"
    rounded: "{rounded.pill}"
    height: 32px
  status-badge:
    backgroundColor: "{colors.status-neutral}"
    textColor: "{colors.ink-table}"
    typography: "{typography.label-sm}"
    rounded: "{rounded.component}"
    padding: 4px 12px
  status-badge-success:
    backgroundColor: "{colors.success-soft}"
    textColor: "{colors.success}"
    typography: "{typography.label-sm}"
    rounded: "{rounded.component}"
  status-badge-progress:
    backgroundColor: "{colors.brand-tint}"
    textColor: "{colors.brand}"
    typography: "{typography.label-sm}"
    rounded: "{rounded.component}"
  status-badge-failed:
    backgroundColor: "{colors.danger-surface}"
    textColor: "{colors.danger}"
    typography: "{typography.label-sm}"
    rounded: "{rounded.component}"
  indicator-chip:
    backgroundColor: "{colors.warning-soft}"
    textColor: "{colors.warning}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.component}"
    padding: 4px 12px
  urgent-chip:
    backgroundColor: "{colors.urgent-soft}"
    textColor: "{colors.urgent}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.component}"
  urgent-chip-muted:
    backgroundColor: "{colors.urgent-muted-soft}"
    textColor: "{colors.urgent-muted}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.component}"
  global-header:
    backgroundColor: "{colors.gradient-header}"
    textColor: "{colors.white}"
    height: 48px
  side-navigation:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink-secondary}"
    typography: "{typography.body}"
  side-navigation-active:
    backgroundColor: "{colors.brand-soft}"
    textColor: "{colors.brand}"
    typography: "{typography.body}"
    rounded: "{rounded.component}"
  page-header:
    backgroundColor: transparent
    textColor: "{colors.ink}"
    typography: "{typography.display}"
    height: 68px
  page-sheet:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.4xl}"
    padding: 20px
  dashboard-sheet:
    backgroundColor: "{colors.surface-raised}"
    textColor: "{colors.ink}"
    rounded: "{rounded.4xl}"
    padding: 20px
  data-table-row:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink-table}"
    typography: "{typography.body-sm}"
    height: 44px
  data-table-row-hover:
    backgroundColor: "{colors.surface-subtle}"
  data-table-row-selected:
    backgroundColor: "{colors.brand-tint}"
  table-header:
    backgroundColor: transparent
    textColor: "{colors.ink-muted}"
    typography: "{typography.caption}"
  tab-active:
    backgroundColor: transparent
    textColor: "{colors.brand}"
    typography: "{typography.body}"
  tab-inactive:
    backgroundColor: transparent
    textColor: "{colors.ink-secondary}"
    typography: "{typography.body}"
  empty-state:
    backgroundColor: transparent
    textColor: "{colors.ink}"
    typography: "{typography.title-sm}"
    padding: 80px 32px
  toast:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    typography: "{typography.body}"
    rounded: "{rounded.xl}"
  dialog:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.5xl}"
  sidecar:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.none}"
  filter-popover:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
  context-menu:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.brand}"
    typography: "{typography.body}"
    rounded: "{rounded.xl}"
  profile-menu:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    typography: "{typography.body}"
    rounded: "{rounded.2xl}"
  selection-toolbar:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.pill}"
  wizard-shell:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
  authentication-card:
    backgroundColor: "{colors.surface-raised}"
    textColor: "{colors.ink}"
    rounded: "{rounded.4xl}"
  stat-card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    typography: "{typography.body-xl}"
    rounded: "{rounded.xl}"
  batch-card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.xl}"
  fleet-active-pill:
    backgroundColor: "{colors.brand-wash}"
    textColor: "{colors.brand}"
    typography: "{typography.label-sm}"
    rounded: "{rounded.pill}"
  fleet-offline-pill:
    backgroundColor: "{colors.warning-soft}"
    textColor: "{colors.warning}"
    typography: "{typography.label-sm}"
    rounded: "{rounded.pill}"
---

## Overview

Interstellar is Illumina's laboratory operations product design language — calm, dense, and explicit about state. The base canvas is **cool grey** (`{colors.canvas}` — #f1f3f8) with white page sheets (`{colors.surface}` — #ffffff) and a single voltage of **brand blue** (`{colors.brand}` — #225de6) carrying primary actions, links, and the selected navigation item. Filled CTAs step one shade brighter (`{colors.brand-action}` — #426aeb) and hover to `{colors.brand-hover}` (#3558d4). Brand is never decorative — washes exist only for selected rows, active chips, nav soft-fill, and hover ghosts.

Type runs **Inter** only, via `{typography.display}` through `{typography.caption}`. Three weights ship: **300** for headings (32 / 28 / 24 / 18px), **400** for everything else (15 / 14 / 13 / 12 / 11px), and **500** for numbers that matter and status chips. Sentence case everywhere except table column headings — 11px uppercase with letter-spacing in `{colors.ink-muted}`.

The shape language is **soft sheets + pill controls**. Page surfaces use 18px radius (`{rounded.4xl}`) with `{shadow.card}`; wizard sections use 16px (`{rounded.3xl}`); status chips and form fields use 8px (`{rounded.component}`); buttons and avatars are fully pill (`{rounded.pill}`). Tables are dense — 44px rows, 10×12px cell padding, `{colors.border-row}` rules, hover `{colors.surface-subtle}`, select `{colors.brand-tint}`.

Seven page templates lock every screen: Application shell, Dashboard grid, Inventory, Fleet inventory, Details, Guided task flow, Authentication. Fifty-three components sit in three levels (15 atoms, 22 compounds, 16 organisms). Loading, empty, error, and disabled states are required — not optional.

**Key Characteristics:**
- Single accent color: `{colors.brand}` (#225de6) for actions, links, and selected nav. Filled actions use `{colors.brand-action}`; three washes (`tint` / `soft` / `ghost`) are not interchangeable.
- One typeface, three weights: Inter 300 / 400 / 500. Titles are light, body is regular, status numbers are medium.
- Authenticated shell: 48px brand-gradient header above 80px/220px side nav + scrollable page viewport. Only the viewport scrolls.
- Soft page sheets: `{colors.surface}` + `{rounded.4xl}` + `{shadow.card}` for list pages; `{colors.surface-raised}` + white inner ring (no shadow) for dashboard regions.
- Dense tables: 44px rows; Group / Columns / Urgent·Alerts·Archived toolbar chips only — sort and filter live in column headers, never as global icon buttons.
- Pill buttons at 36px (page headers/wizards), 44px (authentication), 32px (panels/popovers). Soft-outline 34px for empty-state CTAs.
- Sample identity is fixed: DNA `{colors.sample-dna}` (#ea7600), RNA `{colors.sample-rna}` (#2563eb). Avatars use `{colors.avatar-bg}` / `{colors.avatar-fg}` only — never multi-color persona palettes.
- Urgent indicators stay danger-red until Analysis is completed, then mute to grey; prefer `UrgentChip` so tone follows sample/run state.

## Colors

### Brand & Accent
- **Brand** (`{colors.brand}` — #225de6): Primary actions (outline/ghost), links, and the selected navigation item. Never used for decoration.
- **Brand Action** (`{colors.brand-action}` — #426aeb): Filled CTAs, active tabs, form focus, and checkbox accent. One step brighter than brand.
- **Brand Hover** (`{colors.brand-hover}` — #3558d4): Primary filled-action hover.
- **Brand Tint** (`{colors.brand-tint}` — #eef2ff): Active filter chips, step tags, icon-button hover, selected table rows.
- **Brand Wash** (`{colors.brand-wash}` — #ebf1ff): Fleet Active badge fill and avatar wash.
- **Brand Soft** (`{colors.brand-soft}` — rgba(34, 93, 230, 0.08)): Selected navigation background.
- **Brand Ghost** (`{colors.brand-ghost}` — rgba(34, 93, 230, 0.06)): Ghost and outline action hover only.
- **Focus** (`{colors.focus}` — #426aeb): Focus rings, checkboxes, and radios (same hex as brand-action).

### Surface
- **Canvas** (`{colors.canvas}` — #f1f3f8): Application floor and side navigation. Interstellar does not ship a dark mode.
- **Surface** (`{colors.surface}` — #ffffff): Cards, sheets, popovers, and dialogs.
- **Surface Raised** (`{colors.surface-raised}` — #fbfcfd): Authentication cards and elevated dashboard sheets (paired with a white inner ring instead of a shadow).
- **Surface Subtle** (`{colors.surface-subtle}` — #f9fafc): Hover rows and secondary surfaces.
- **Surface Muted** (`{colors.surface-muted}` — #fafbfd): Control rows and empty-state regions.

### Hairlines & Borders
- **Border** (`{colors.border}` — #e2e4e9): Default card and control border.
- **Border Muted** (`{colors.border-muted}` — #e8eaef): Table, popover, and page-shell border.
- **Border Strong** (`{colors.border-strong}` — #c8cad4): Input and emphasized control border (authentication fields).
- **Border Row** (`{colors.border-row}` — #f5f6fa): Table row rule and neutral menu hover.

### Text
- **Ink** (`{colors.ink}` — #242936): Primary text and headings — the most-used ink in product.
- **Ink Secondary** (`{colors.ink-secondary}` — #656972): Labels, navigation, and supporting text.
- **Ink Table** (`{colors.ink-table}` — #374151): Dense table and operational content.
- **Ink Muted** (`{colors.ink-muted}` — #919297): Metadata, empty-state copy, placeholders, uppercase table headers.
- **Ink Idle** (`{colors.ink-idle}` — #4a4f59): Fleet Idle status text (plain text, not a chip).
- **Placeholder** (`{colors.placeholder}` — #b0b3bc): Input placeholder text inside filter popovers.
- **On Brand / White** (`{colors.white}` — #ffffff): Text and icons over brand surfaces and the header gradient.

### Semantic Status
- **Success** (`{colors.success}` — #16a34a) with soft `#f0fdf4` and border `#bbf7d0`: Completed status and confirmation messaging. **Success Bright** (`{colors.success-bright}` — #22c55e) for live availability.
- **Warning** (`{colors.warning}` — #c34709) with soft `#fff2eb`: Warning chips and Offline fleet pills. **Warning Fleet** (`{colors.warning-fleet}` — #e15c19) for Offline icons.
- **Urgent** (`{colors.urgent}` — #dc2626) with soft `#fef2f2`: Process-now while Draft or Planned. After Analysis is completed (including Reporting), switch to **Urgent Muted** (`{colors.urgent-muted}` — #656972) on `{colors.urgent-muted-soft}`.
- **Danger** (`{colors.danger}` — #dc2626) with hover `#b91c1c`, strong invalid border `#ef4444`, soft `#fee2e2`, surface `#fef2f2`, tint `#fff5f5`, border `#fecaca`: Errors, alerts, QC failure, destructive actions.
- **Status Neutral** (`{colors.status-neutral}` — #f8f9fc): Ready / Draft / Cancelled status chip fill.

### Domain Identity
- **Sample DNA** (`{colors.sample-dna}` — #ea7600): DNA samples and plate wells.
- **Sample RNA** (`{colors.sample-rna}` — #2563eb): RNA samples and plate wells.
- **Avatar BG / FG** (`{colors.avatar-bg}` / `{colors.avatar-fg}` — #ebf1ff / #052776): Profile initials only — never a multi-color persona palette.

### Gradients & Scrim
- **Header Gradient** (`{colors.gradient-header}`): Radial blue→lavender sheen for the 48px global and authentication headers (`#6284f8 → #5276eb → #d2a1c5`).
- **Header Sheen** (`{colors.gradient-header-sheen}`): Subtle 10% white horizontal highlight across the brand header.
- **Spotlight** (`{colors.gradient-spotlight}`): Purple radial highlight over brand-action for Home summary cards.
- **Overlay** (`{colors.overlay}` — rgba(20, 25, 38, 0.42)): Modal backdrop. Alert dialogs may use a blurred blue scrim variant; standard dialogs use this ink scrim.

## Typography

### Font Family
The system runs **Inter** for everything — display, body, navigation, captions, chips. Fallbacks walk `ui-sans-serif, system-ui, sans-serif`. **Mono** (`ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace`) is reserved for wells, identifiers, code, and token values.

There is no separate display family. Weight does the hierarchy work: light for titles, regular for UI, medium for consequential numbers.

### Hierarchy

| Token | Size | Weight | Line Height | Use |
|---|---|---|---|---|
| `{typography.display}` | 32px | 300 | 1.2 | List page titles and welcome messages |
| `{typography.title-lg}` | 28px | 300 | 1.2 | Detail page and task-flow titles |
| `{typography.title}` | 24px | 300 | 1.2 | Sheet titles and wizard headings |
| `{typography.title-code}` | 22px | 500 | 1.2 | One-time passcode entry |
| `{typography.title-sm}` | 18px | 300 | 1.3 | Empty-state headings |
| `{typography.body-xl}` | 16px | 400 | 1.2 | Dashboard run names and drawer titles |
| `{typography.body-lg}` | 15px | 400 | 1.4 | Authentication fields and actions |
| `{typography.body}` | 14px | 400 | 1.4 | Default interface and control text |
| `{typography.body-sm}` | 13px | 400 | 1.4 | Dense tables, chips, and helper text |
| `{typography.label-sm}` | 12px | 400 | 1.4 | Status pills and secondary metadata (medium weight when tone is status) |
| `{typography.caption}` | 11px | 400 | 1.4 | Uppercase table headers and micro metadata |
| `{typography.mono}` | 13px | 400 | 1.4 | Wells, plate IDs, identifiers |

### Principles
Headings stay light. A 32px / 300 list title would feel underweight on a marketing site; here density and operational chrome carry hierarchy, so type can stay quiet. Medium (500) is scarce — reserved for OTP digits, status chip labels, and numbers that matter on stat cards.

Sentence case everywhere. The only uppercase treatment is table column headings at `{typography.caption}` in `{colors.ink-muted}` with letter-spacing.

### Note on Font Substitutes
Inter is open-source and should be loaded as the primary face. If Inter is unavailable, the system stack (`ui-sans-serif, system-ui, sans-serif`) is an acceptable temporary fallback — keep the same size/weight scale; do not substitute a display serif or a heavier geometric sans.

## Layout

### Spacing System
- **Base unit:** 4px.
- **Tokens:** `{spacing.0}` 0 · `{spacing.1}` 4px · `{spacing.2}` 8px · `{spacing.3}` 12px · `{spacing.4}` 16px · `{spacing.5}` 20px · `{spacing.6}` 24px · `{spacing.8}` 32px · `{spacing.10}` 40px · `{spacing.12}` 48px · `{spacing.16}` 64px · `{spacing.20}` 80px.
- **Sheets:** 20px padding (`{spacing.5}`); cards 16–20px; page gutters 40px on the right (`{spacing.10}`).
- **Dashboard gap:** 24px (`{spacing.6}`) between Home sheets.
- **Inventory gap:** 16–24px between title, tabs, toolbar, and table.
- **Guided task gap:** 24–32px between header, work area, and sticky footer.

### Shell & Regions
- **Global header:** `{layout.header-height}` 48px — brand gradient, Illumina wordmark (opens State switcher when authenticated), Workgroup + waffle, search/bell/help, avatar menu.
- **Side navigation:** `{layout.sidenav-collapsed}` 80px / `{layout.sidenav-expanded}` 220px. Active item: brand text + `{colors.brand-soft}` fill + 3px left brand bar. Nav items: Home, Samples, Runs, Fleet, Workflows + Settings tree (Lab info, Instruments & servers, Workflow templates, Folder locations).
- **Page header:** `{layout.page-header-height}` 68px — 32px light title with actions on the right.
- **Tab strip:** `{layout.tab-height}` 50px — 14px text with 3.5 padding; active tab gets a 2px brand underline with rounded top.
- **Content max:** `{layout.content-max}` 1440px recommended documentation width.
- **Controls:** auth fields `{layout.control-height}` 44px; page-header/wizard actions `{layout.control-height-md}` 36px; toolbar icon buttons `{layout.control-height-sm}` 32px. Nothing in product is smaller than 32px.
- **Table row:** `{layout.table-row}` 44px. **Dashboard activity row:** `{layout.dashboard-row}` 76px.

### Seven Page Templates

| Template | Used by | Structure |
|---|---|---|
| **Application shell** | Home, Runs, Samples, Workflows, Fleet, details | 48px header + (80/220 nav ‖ scrollable viewport) |
| **Dashboard grid** | Home | 2×2 sheets: Summary / Runs / Fleet / Samples, 24px gap |
| **Inventory** | Runs, Samples, Workflows | Title → tabs → toolbar chips → 44px data table |
| **Fleet inventory** | Fleet | Title → instrument sheet → DRAGEN sheets (cards, not table) |
| **Details** | Run / Sample / Fleet details | Breadcrumb + status → pipeline → tabs → panel; sidecars overlay right |
| **Guided task flow** | Add samples, Plan run, Setup, Edit sample | Full-height; side nav hidden; sticky footer actions |
| **Authentication** | Login, recovery, first-time setup | 48px brand header + centered 320–440px card stack |

### Whitespace Philosophy
The system is dense by design. Table rows sit at 44px with 10×12 cell padding; dashboard activity lists show at most three rows before internal scroll so summary counters stay in view. Breathing room lives at the sheet edge (20px) and between Home cards (24px), not inside operational rows. Task flows take the full screen and pin actions to a sticky footer so the work column can stay focused.

## Elevation

Interstellar uses a progressive shadow scale rather than a single float.

- **Flat:** Canvas, side nav, most body regions.
- **Card** (`{shadow.card}` — `0 1px 4px rgba(0,0,0,0.05)`): Page surfaces behind tables.
- **Card Subtle** (`{shadow.card-subtle}`): In-progress run cards.
- **Menu / Popover / Tooltip / Toast** (`{shadow.menu}` → `{shadow.tooltip}`): Floating chrome at 4×24 with opacity stepping 0.12 → 0.16; toast at 0.13.
- **Panel** (`{shadow.panel}` — `0 4px 32px / 0.16`): Column visibility panel.
- **Dialog** (`{shadow.dialog}` — `0 24px 64px / 0.18`): Modal dialogs.
- **Drawer** (`{shadow.drawer}` — `0 0 40px / 0.18`): Right-hand sidecars.
- **Icon** (`{shadow.icon}`): Icons sitting on the header gradient.
- **Dashboard sheets** skip the card shadow and use a white inner ring on `{colors.surface-raised}` instead.
- **Modal scrim:** `{colors.overlay}` at rest; alert dialogs may blur a blue scrim.

### Motion
- Fast `{motion.fast}` 120ms — hover/pressed (sidecar panel slide uses this).
- Standard `{motion.standard}` 180ms — control and disclosure transitions.
- Slow `{motion.slow}` 280ms — panels and modal transitions.
- Toast `{motion.toast}` 320ms — top slide in/out with linear easing for travel; productive UI uses `{motion.easing}` `cubic-bezier(0.2, 0, 0, 1)`.
- Sidecar scrim: 40ms; panel: 120ms. Escape + backdrop dismiss.

### Z-Index
`{z sticky 10}` · `{dropdown 20}` · `{overlay 50}` · `{debug 9999}` · `{tooltip 99999}`.

## Components

### Buttons & Controls

**`button-primary`** — Brand-action fill, white text, pill radius, 36px height (page headers / wizards). Hover → `{colors.brand-hover}`. Disabled at 40% opacity. Sizes: `sm` 32px panels, `md` 36px, `lg` 44px authentication.

**`button-outline` / `button-ghost`** — Brand text; outline carries a 1px brand border. Hover → `{colors.brand-ghost}` wash.

**`button-soft-outline`** — 34px empty-state CTA; softer border treatment than outline.

**`button-destructive` / `button-destructive-filled`** — Danger text or filled danger with white label; hover tint / `{colors.danger-hover}`.

**`icon-button`** — Shared 32×32 control: brand blue, 6px radius (`{rounded.xs}`), hover `{colors.brand-tint}`. Used for table toolbar Group / Columns and more-actions menus — not Filter or Sort.

### Forms

**`text-field-auth`** — 44px, `{rounded.lg}` 10px, `{colors.border-strong}` (#c8cad4). Label above in `{typography.body}` secondary ink; required marker is part of the label text. Focus border → `{colors.focus}`. Error border → `{colors.danger-strong}`.

**`text-field-form`** — 36px in-form, 8px radius.

**`text-field-compact`** — ~30px inside filter popovers; placeholder `{colors.placeholder}`.

**Checkbox / Radio** — Native controls with `{colors.focus}` accent. Radio rows: the whole row is the hit target; selected row uses a 4% brand border wash.

### Status & Identity

**`status-badge`** — Component 8px radius tags. Completed green, In progress brand+spinner, Failed red, Ready/Draft/Cancelled neutral. Tables use plain 13px text, not chips. Fleet exceptions: Active and Offline are rounded-full pills; Idle is plain `{colors.ink-idle}` text.

**`indicator-chip` / `urgent-chip`** — Warning (orange), Alert (red triangle), Urgent (lightning). Labeled 13px with 8px radius, or 22px icon-only. **`urgent-chip-muted`** greys urgent after Analysis is completed. Home Samples sheet hides muted urgent entirely (counts active only).

**`qc-status-chip`** — Pass / Failed; summary variant adds chevron for the QC sidecar.

**`avatar-header` / `avatar-menu`** — Circular initials at 24px / 32px using avatar wash tokens only.

**`thumbnail`** — Unframed photographic assets (tubes, flow cell, instrument). Workflow tiles get 6px radius. Fleet uses fleet-instruments / fleet-dragen assets.

### Chrome Organisms

**`global-header`** — Fixed 48px brand-gradient band: Lab software label, centered Illumina wordmark, Workgroup + waffle, local white search/bell/help SVGs, avatar menu. Wordmark opens the State switcher when authenticated.

**`side-navigation`** — Collapsible 80 / 220. Active: brand text + soft fill + 3px left bar. Icons at 16px from the product SVG set (Home, Samples, Runs, Instrument, Workflows, Settings + MenuCollapsed/Expanded).

**`page-header`** — 68px title row at `{typography.display}` (32px / 300) with optional breadcrumb, status, and primary pill actions on the right.

**`page-sheet` / `dashboard-sheet`** — White 18px sheet with light card shadow vs raised `#fbfcfd` sheet with white inner ring.

### Tables & Filters

**`data-table-row`** — Dense 44px rows, sticky actions, grouping, selection, empty states. Hover subtle / select brand-tint. Sort and filter live on **`table-header`** (11px uppercase) — not toolbar icons.

**`table-toolbar`** — IconButton Group + Columns, then idle grey-fill chips (`#f1f3f8`): Urgent / Alerts / Archived (Samples); Alerts / Failed / Cancelled / Completed / Archived (Runs). No global Filter or Sort icon buttons.

**`filter-popover`** — Stem (12px caret) centers on the trigger control — never the panel center. When right-aligned under a 32px icon button, stem is `right: 10px`. Search, multi-select, clear/apply.

**`column-panel`** — Show/hide columns with restore defaults; 14px radius, panel shadow.

**`selection-toolbar`** — Floating bar when rows are selected (bulk / destructive).

**`sortable-table-header`** — Unsorted / Ascending / Descending / Filtered states.

### Overlays & Feedback

**`toast`** — 12px radius, soft blue shadow (`{shadow.toast}`), centered 40px from top. Themes: info, success, warning, error, ai. Close always present; auto-hide pauses on hover.

**`dialog` / `alert-dialog`** — Centered modal; alert themes info / warning / error over blurred scrim. Warning uses two medium-emphasis outline buttons (e.g. Cancel / Leave).

**`sidecar`** — Right-edge overlay: title, close, scroll body, sticky footer. Panel 120ms; scrim 40ms. Escape + backdrop dismiss. Variants: Comments, History, QC status, Sequencing metrics.

**`context-menu`** — 12px radius, brand-colored items; destructive item variant.

**`profile-menu`** — Avatar popover: name, email, Sign out. 14px radius, min 210px, 6px below avatar.

**`tooltip` / `alert-tooltip`** — Hover/focus disclosure; alert tooltips break down alert reasons (single / group / muted urgent).

### Domain Compounds

**`empty-state`** — Illustration + light 18px heading + optional CTA. Home Runs: "…as soon as they are executed." Home Samples: "…as soon as they are processed."

**`stat-card`** — Dashboard metric: label + large number + optional Urgent/Alert chips + drill-down. Draft/Planned are counters only on the Runs sheet. Zero state has no CTA.

**`batch-card`** — Expandable batch summary with mini-plates, workflow, rolled-up UrgentChip/warning. Body click selects when checkbox present; otherwise expands. `urgentMuted` greys urgent after Analysis is completed.

**`plate-visualization` / `mini-plate-chip`** — 96-well plate map with DNA/RNA colors; compact chip with hover plate ID.

**`step-indicator`** — Plan-run steps and pipeline stages: complete / current / upcoming.

**`workflow-thumbnail`** — Identity image or gradient fallback at 24–40px.

**`tab-bar`** — Underline (default inventory) or pill section switcher with optional live counts.

**`card` / `authentication-card` / `password-requirements`** — Page sheet, wizard section (16px), interactive (title hovers to brand), auth raised surface, live password-rule checklist.

### Larger Organisms

**`dashboard-sheet`** — Home operational card. Runs: Draft/Planned counters; activity list is In progress / Completed / Failed only. Samples: Unbatched/Batched/In progress with active urgent only. Fleet: Library prep / Sequencer side A–B (Idle or ETA) + DRAGEN.

**`fleet-inventory`** — Instrument + DRAGEN asset sheets with Active/Idle/Offline and alert chips — card layout, not a data table.

**`wizard-shell`** — Full-height task: header, scrollable work, sticky footer. Hides side nav. Multi-step plan run, single-step add samples, read-only review.

**`authentication-layout`** — Minimal brand header + centered title/card stack for Login, Recovery, Setup.

**`state-switcher`** — Testing drawer for run/sample/fleet overrides. Session data persists across refresh and sign-out; Reset data is the only clear.

**`onboarding-dialog`** — First-use welcome with tube illustration and Add samples CTA.

**`brand-wordmark` / `header-backdrop`** — Illumina wordmark shared by header and auth; gradient + sheen for the 48px band.

**`divider`** — 1px `{colors.border}` or `{colors.border-muted}` horizontal/vertical.

## Responsive Behavior

| Name | Width | Key Changes |
|---|---|---|
| Compact / tablet ops | < 1024px | Side nav stays collapsed at 80px; inventory tables keep horizontal scroll inside the sheet; dashboard grid may stack to one column; sidecars still overlay the right edge. |
| Standard desktop | 1024–1440px | Full shell with expandable 220px nav; Home 2×2 dashboard; inventory tables at full density; detail pages with sticky header actions. |
| Wide | > 1440px | Content respects `{layout.content-max}` 1440px; canvas absorbs outer gutters; nav can remain expanded. |

### Touch / Hit Targets
- Primary actions at minimum 32×32px; authentication CTAs and fields at 44px.
- Icon buttons are 32×32 with 6px radius — the densest interactive chrome in product.
- Table rows are 44px tall — the whole row is selectable when selection is enabled.
- Radio rows make the entire row the hit target; the native input is `pointer-events-none`.

### Collapsing Strategy
- Side nav collapses to 80px icon rail; labels return at 220px.
- Guided task flows hide the side nav entirely and pin actions to a sticky footer.
- Dashboard activity lists cap visible rows at three, then scroll internally so counters stay pinned.
- Fleet stays card-based at all widths — it never collapses into the Inventory table template.
- Authentication stays a centered 320–440px column under the 48px brand header.

## Agent Rules (binding)

These rules ship with the portable design-system package (`AGENTS.md`) and apply to any UI written against this file:

1. **Never hard-code** a raw colour, radius, shadow, or font size — use tokens. If a value is missing, add a token.
2. **Reuse a page template** — every screen maps onto one of the seven layouts. Do not invent a new skeleton.
3. **Reuse a catalogued component** before writing a new one (53 today; promote a pattern when it appears a third time).
4. **State is always visible** — loading, empty, error, and disabled are required. Every list needs an empty state.
5. **Interactive elements are real controls** — `button`, `a`, and `input` with accessible names.

## Known Gaps

- **Full SVG icon set:** Product icons live in `icons.tsx` (navigation, toolbar, directional, timeline, status). GlobalHeader search/bell/help/waffle are local white SVGs, not that set — paths are not inlined in this DESIGN.md.
- **Photographic assets:** Tube / flow-cell / instrument / fleet-dragen thumbnails are referenced by role, not bundled here.
- **Radiance toast internals:** Toast themes (info / success / warning / error / ai) and elevation-4 treatment are summarized; the full Radiance toast primitive API is out of scope.
- **State switcher:** Testing-only drawer for session overrides — documented as an organism, internals intentionally shallow.
- **Hover color matrices:** Product uses tokenized washes (`brand-ghost`, `surface-subtle`, `danger-tint`); exhaustive per-component `:hover` tables are not duplicated beyond component descriptions.
- **Dark mode:** Not shipped. Do not invent one from these tokens.
- **Motion choreography beyond durations:** Four duration tokens + two easings are specified; complex sequenced transitions (e.g. multi-step wizard) are implementation details.
