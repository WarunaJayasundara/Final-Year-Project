import type { LucideIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

interface GameStartScreenProps {
  icon: LucideIcon;
  accent: string;
  title: string;
  instructions: string;
  onStart: () => void;
}

/**
 * A shared pre-game screen (title, instructions, a per-game accent color/icon,
 * one Start button) so every game gets a professional entry point instead of
 * dropping the student straight into gameplay - previously none of the 8
 * games had any start screen at all.
 */
export function GameStartScreen({ icon: Icon, accent, title, instructions, onStart }: GameStartScreenProps) {
  const { t } = useTranslation('games');

  return (
    <Card className="mx-auto max-w-md">
      <CardContent className="flex flex-col items-center gap-4 p-8 text-center">
        <span
          className="flex h-14 w-14 items-center justify-center rounded-2xl"
          style={{ backgroundColor: `color-mix(in oklch, ${accent}, transparent 85%)`, color: accent }}
        >
          <Icon className="h-7 w-7" />
        </span>
        <div>
          <h1 className="text-xl font-semibold">{title}</h1>
          <p className="mt-1 text-sm text-muted-foreground">{instructions}</p>
        </div>
        <Button onClick={onStart} size="lg">
          {t('start.button')}
        </Button>
      </CardContent>
    </Card>
  );
}
