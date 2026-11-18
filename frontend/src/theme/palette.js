/**
 * The canonical palette. Every color literal in the frontend lives in this
 * file and nowhere else; Vuetify's theme config and all bespoke component
 * styles consume these values through the CSS custom properties Vuetify
 * emits. Token names and values mirror docs/planning/mockups/design-tokens.css.
 */
export const palette = {
  // Dark / cinematic surface: hero art, poster grids, nav
  bg: '#120E0A',
  bg2: '#1C1611',
  ink: '#F4EEDF',
  inkDim: '#B7AC97',
  hairDark: 'rgba(244, 238, 223, 0.16)',

  // Light / paper surface: reading sections: reviews, comments
  paper: '#F6F1E6',
  paperInk: '#1F2A44',
  paperDim: '#6B6152',
  paperHair: 'rgba(31, 42, 68, 0.14)',

  // Ink-stamp brand colors
  primary: '#B23A2E', // oxblood red, Done status, rating stamp, heart reaction
  accent: '#B8892F', // mustard gold, In Progress status, hero kicker
  teal: '#6E9491', // secondary accent, On Hold status
}

export const fonts = {
  serif: "'Bodoni Moda', 'Times New Roman', serif",
  sans: "'Archivo', -apple-system, 'Segoe UI', sans-serif",
  mono: "'IBM Plex Mono', ui-monospace, 'SF Mono', monospace",
}

/**
 * The single (dark-only) Vuetify theme. Colors become rgb-triplet custom
 * properties (--v-theme-primary etc., consumed as rgb(var(--v-theme-primary)));
 * variables pass through verbatim as --v-* custom properties, which is where
 * the non-color tokens (hairlines, font stacks) ride along.
 */
export const doneDark = {
  dark: true,
  colors: {
    background: palette.bg,
    surface: palette.bg2,
    'surface-variant': palette.bg2,
    'on-background': palette.ink,
    'on-surface': palette.ink,
    primary: palette.primary,
    'on-primary': palette.ink,
    secondary: palette.accent,
    'on-secondary': palette.bg,
    accent: palette.accent,
    teal: palette.teal,
    'ink-dim': palette.inkDim,
    paper: palette.paper,
    'on-paper': palette.paperInk,
    'paper-dim': palette.paperDim,
    error: palette.primary,
    'on-error': palette.ink,
  },
  variables: {
    'hair-dark': palette.hairDark,
    'paper-hair': palette.paperHair,
    'font-serif': fonts.serif,
    'font-sans': fonts.sans,
    'font-mono': fonts.mono,
  },
}
