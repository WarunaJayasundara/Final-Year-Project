/** One consistent color per cognitive category. */
const CATEGORY_ORDER = ['memory', 'logical_reasoning', 'numerical_ability', 'attention', 'spatial_pattern'];

export function categoryColor(code: string, fallbackIndex = 0): string {
  const index = CATEGORY_ORDER.indexOf(code);
  return `var(--chart-${((index >= 0 ? index : fallbackIndex) % 5) + 1})`;
}
