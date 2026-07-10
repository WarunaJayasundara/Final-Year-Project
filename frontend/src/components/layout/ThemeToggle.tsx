import { useEffect, useState } from 'react';
import { useTheme } from 'next-themes';
import { Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';

const ORDER = ['light', 'dark', 'system'] as const;

// A plain cycling button (light -> dark -> system -> light) rather than a
// dropdown - simpler, fewer moving parts, and a well-established pattern
// (e.g. GitHub's theme toggle) for a control with only three states.
export function ThemeToggle() {
  const { theme, setTheme } = useTheme();
  const [mounted, setMounted] = useState(false);

  useEffect(() => setMounted(true), []);

  const current = mounted ? (theme ?? 'system') : 'system';

  const cycle = () => {
    const index = ORDER.indexOf(current as (typeof ORDER)[number]);
    setTheme(ORDER[(index + 1) % ORDER.length]);
  };

  return (
    <Button variant="ghost" size="icon-sm" aria-label={`Theme: ${current}. Click to change.`} onClick={cycle}>
      {current === 'dark' ? <Moon className="h-4 w-4" /> : <Sun className="h-4 w-4" />}
    </Button>
  );
}
