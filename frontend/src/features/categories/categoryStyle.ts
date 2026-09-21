/**
 * One consistent color per cognitive category. Using the same color for a category on every
 * screen (charts, chips, lists) lets students recognise it at a glance instead of re-reading labels.
 * Colors are the theme's chart tokens, so they follow light/dark mode automatically.
 */
const CATEGORY_ORDER = ['memory', 'logical_reasoning', 'numerical_ability', 'attention', 'spatial_pattern'];

export function categoryColor(code: string, fallbackIndex = 0): string {
  const index = CATEGORY_ORDER.indexOf(code);
  return `var(--chart-${((index >= 0 ? index : fallbackIndex) % 5) + 1})`;
}
