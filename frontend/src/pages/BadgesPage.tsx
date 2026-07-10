import type { ComponentType } from 'react';
import { useTranslation } from 'react-i18next';
import {
  CalendarCheck,
  CheckCircle,
  Crown,
  Flame,
  Footprints,
  Gamepad2,
  Lock,
  Star,
  Target,
  TrendingUp,
  Trophy,
  Zap,
} from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { FullPageSpinner } from '@/components/auth/RequireAuth';
import { FadeInItem, FadeInStagger } from '@/components/motion/FadeIn';
import { useBadges } from '@/features/gamification/useGamification';

const ICONS: Record<string, ComponentType<{ className?: string }>> = {
  footprints: Footprints,
  flame: Flame,
  star: Star,
  target: Target,
  trophy: Trophy,
  'trending-up': TrendingUp,
  crown: Crown,
  'gamepad-2': Gamepad2,
  zap: Zap,
  'check-circle': CheckCircle,
  'calendar-check': CalendarCheck,
};

export function BadgesPage() {
  const { t, i18n } = useTranslation('gamification');
  const locale = i18n.language.startsWith('si') ? 'si' : 'en';
  const { data: badges, isLoading } = useBadges();

  if (isLoading || !badges) {
    return <FullPageSpinner />;
  }

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t('badges.title')}</h1>
        <p className="text-muted-foreground">{t('badges.subtitle')}</p>
      </div>

      <FadeInStagger className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {badges.map((badge) => {
          const Icon = ICONS[badge.icon] ?? Trophy;
          const earned = badge.earned_at !== null;

          return (
            <FadeInItem key={badge.code}>
              <Card className={earned ? 'border-primary/30' : 'opacity-60'}>
                <CardContent className="flex items-start gap-4 p-5">
                  <span
                    className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl ${
                      earned ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'
                    }`}
                  >
                    {earned ? <Icon className="h-6 w-6" /> : <Lock className="h-5 w-5" />}
                  </span>
                  <div className="min-w-0 flex-1">
                    <p className="font-semibold">{locale === 'si' ? badge.name_si : badge.name_en}</p>
                    <p className="text-sm text-muted-foreground">
                      {locale === 'si' ? badge.description_si : badge.description_en}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                      {earned
                        ? t('badges.earnedOn', { date: new Date(badge.earned_at as string).toLocaleDateString() })
                        : t('badges.locked')}
                    </p>
                  </div>
                </CardContent>
              </Card>
            </FadeInItem>
          );
        })}
      </FadeInStagger>
    </div>
  );
}
