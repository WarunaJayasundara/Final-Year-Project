import type { ComponentType } from 'react';
import { ErrorState } from '@/components/ui/error-state';
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
import { PageHeader } from '@/components/layout/PageHeader';
import { Progress } from '@/components/ui/progress';
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

/** Every earned badge used to render in the exact same brand-gold tint regardless of what it was for. */
const BADGE_ACCENT: Record<string, string> = {
  footprints: 'var(--chart-1)',
  flame: 'var(--streak)',
  star: 'var(--chart-5)',
  target: 'var(--chart-4)',
  trophy: 'var(--brand-gold)',
  'trending-up': 'var(--chart-2)',
  crown: 'var(--chart-3)',
  'gamepad-2': 'var(--chart-1)',
  zap: 'var(--warning)',
  'check-circle': 'var(--success)',
  'calendar-check': 'var(--chart-2)',
};

export function BadgesPage() {
  const { t, i18n } = useTranslation('gamification');
  const locale = i18n.language.startsWith('si') ? 'si' : 'en';
  const { data: badges, isLoading, isError, refetch } = useBadges();

  if (isError) {
    return <ErrorState onRetry={() => refetch()} />;
  }

  if (isLoading || !badges) {
    return <FullPageSpinner />;
  }

  const earnedCount = badges.filter((b) => b.earned_at !== null).length;
  // Earned badges first (newest first), then the locked ones still to win.
  const ordered = [...badges].sort((x, y) => {
    if ((x.earned_at === null) !== (y.earned_at === null)) return x.earned_at === null ? 1 : -1;
    return (y.earned_at ?? '').localeCompare(x.earned_at ?? '');
  });

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('badges.title')} subtitle={t('badges.subtitle')} pattern="rings" />

      <Card>
        <CardContent className="flex flex-col gap-3 p-5">
          <div className="flex items-center gap-3">
            <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-[color:var(--brand-gold)]/15 text-[color:var(--brand-gold-ink)]">
              <Trophy className="h-5 w-5" />
            </span>
            <p className="text-lg font-semibold">{t('widget.badgesEarned', { earned: earnedCount, total: badges.length })}</p>
          </div>
          <Progress value={badges.length ? (earnedCount / badges.length) * 100 : 0} />
        </CardContent>
      </Card>

      <FadeInStagger className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
        {ordered.map((badge) => {
          const Icon = ICONS[badge.icon] ?? Trophy;
          const earned = badge.earned_at !== null;
          const accent = BADGE_ACCENT[badge.icon] ?? 'var(--brand-gold)';

          return (
            <FadeInItem key={badge.code}>
              <Card
                className={`h-full ${earned ? '' : 'border-dashed'}`}
                style={earned ? { borderColor: `color-mix(in oklch, ${accent}, transparent 50%)` } : undefined}
              >
                <CardContent className="flex h-full flex-col items-center gap-2 p-4 text-center">
                  <span
                    className={`flex h-14 w-14 items-center justify-center rounded-full ${earned ? '' : 'bg-muted text-muted-foreground'}`}
                    style={earned ? { backgroundColor: `color-mix(in oklch, ${accent}, transparent 85%)`, color: accent } : undefined}
                  >
                    {earned ? <Icon className="h-7 w-7" /> : <Lock className="h-5 w-5" />}
                  </span>
                  <p className={`text-sm font-semibold leading-snug ${earned ? '' : 'text-muted-foreground'}`}>
                    {locale === 'si' ? badge.name_si : badge.name_en}
                  </p>
                  <p className="line-clamp-3 text-xs text-muted-foreground">
                    {locale === 'si' ? badge.description_si : badge.description_en}
                  </p>
                  <p className="mt-auto pt-1 text-xs text-muted-foreground">
                    {earned
                      ? t('badges.earnedOn', { date: new Date(badge.earned_at as string).toLocaleDateString() })
                      : t('badges.locked')}
                  </p>
                </CardContent>
              </Card>
            </FadeInItem>
          );
        })}
      </FadeInStagger>
    </div>
  );
}
