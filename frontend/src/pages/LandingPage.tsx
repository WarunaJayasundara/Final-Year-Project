import { Link, Navigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  BarChart3,
  CalendarClock,
  ClipboardCheck,
  Flame,
  Gamepad2,
  Languages,
  MessageSquareText,
  Target,
  TrendingUp,
  Trophy,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { FadeIn, FadeInItem, FadeInStagger } from '@/components/motion/FadeIn';
import { MotionCard } from '@/components/motion/MotionCard';
import { CountUp } from '@/components/motion/CountUp';
import { BalancedGrid } from '@/components/ui/balanced-grid';
import { HelaIQMark } from '@/components/brand/HelaIQMark';
import { useCurrentUser } from '@/features/auth/useAuth';

const HOW_IT_WORKS_ICONS = [Target, CalendarClock, TrendingUp];
const HOW_IT_WORKS_KEYS = ['assess', 'practice', 'track'] as const;

const SKILL_AREA_KEYS = ['memory', 'logical_reasoning', 'numerical_ability', 'attention', 'spatial_pattern'] as const;

const FEATURE_ICONS = [Target, ClipboardCheck, BarChart3, Gamepad2, MessageSquareText, Languages];
const FEATURE_KEYS = ['adaptive', 'examPrep', 'progress', 'games', 'explanations', 'bilingual'] as const;

export function LandingPage() {
  const { t } = useTranslation();
  const { data: user } = useCurrentUser();

  if (user?.role === 'user') {
    return <Navigate to="/dashboard" replace />;
  }
  if (user?.role === 'admin' || user?.role === 'super_admin') {
    return <Navigate to="/admin/dashboard" replace />;
  }

  const steps = HOW_IT_WORKS_KEYS.map((key, i) => ({
    key,
    icon: HOW_IT_WORKS_ICONS[i],
    title: t(`landing.howItWorks.steps.${key}.title`),
    description: t(`landing.howItWorks.steps.${key}.description`),
  }));

  const features = FEATURE_KEYS.map((key, i) => ({
    key,
    icon: FEATURE_ICONS[i],
    title: t(`landing.features.${key}.title`),
    description: t(`landing.features.${key}.description`),
  }));

  return (
    <div className="flex flex-col gap-24 py-2">
      {/* Hero */}
      <section className="grid items-center gap-12 py-4 md:grid-cols-2 md:py-10">
        <FadeIn className="flex flex-col items-start gap-6">
          <p className="border-l-2 border-[color:var(--brand-gold)] pl-3 text-xs font-medium uppercase tracking-[0.14em] text-muted-foreground">
            {t('landing.badge')}
          </p>
          <h1 className="text-4xl font-semibold leading-[1.1] sm:text-5xl">{t('landing.heroTagline')}</h1>
          <p className="max-w-md text-lg leading-relaxed text-muted-foreground">{t('tagline')}</p>
          <div className="flex flex-wrap items-center gap-3">
            <Button asChild size="lg">
              <Link to="/login">{t('landing.getStarted')}</Link>
            </Button>
            <Button asChild size="lg" variant="outline">
              <a href="#how-it-works">{t('landing.howItWorks.title')}</a>
            </Button>
          </div>
        </FadeIn>

        {/* Product preview - a real composition of the design system's own components, not a stock image. */}
        <FadeIn delay={0.15} className="relative">
          <div className="float-y">
          <Card className="shadow-md">
            <CardContent className="flex flex-col gap-4 p-6">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <HelaIQMark variant="compact" />
                  <span className="text-sm font-medium text-muted-foreground">{t('landing.preview.readinessLabel')}</span>
                </div>
                <Badge variant="success">
                  <CountUp value="82%" />
                </Badge>
              </div>
              <Progress value={82} />
              <div className="grid grid-cols-2 gap-3 pt-2">
                <div className="rounded-lg border border-border bg-muted/50 p-3">
                  <p className="text-xs text-muted-foreground">{t('landing.preview.levelLabel')}</p>
                  <p className="mt-1 text-lg font-semibold">{t('levels.4')}</p>
                </div>
                <div className="rounded-lg border border-border bg-muted/50 p-3">
                  <p className="text-xs text-muted-foreground">{t('landing.preview.streakLabel')}</p>
                  <p className="mt-1 flex items-center gap-1 text-lg font-semibold">
                    <Flame className="flame-flicker h-4 w-4 text-[color:var(--brand-gold-ink)]" /> 12
                  </p>
                </div>
              </div>
              <div className="flex items-end gap-1.5 pt-1">
                {[38, 52, 46, 61, 58, 70, 82].map((v, i) => (
                  <div
                    key={i}
                    className={`bar-grow flex-1 rounded-t-sm ${i === 6 ? 'bg-primary' : 'bg-primary/25'}`}
                    style={{ height: `${v * 0.5}px`, ['--i' as string]: i }}
                    aria-hidden
                  />
                ))}
              </div>
            </CardContent>
          </Card>
          <span
            aria-hidden
            className="float-y-alt absolute -right-2 -top-4 flex items-center gap-1.5 rounded-full border border-border bg-card px-3 py-1.5 text-xs font-semibold shadow-md sm:-right-5"
          >
            <Trophy className="h-3.5 w-3.5 text-[color:var(--brand-gold-ink)]" /> +120 XP
          </span>
          </div>
        </FadeIn>
      </section>

      {/* How it works */}
      <section id="how-it-works" className="flex scroll-mt-24 flex-col gap-8">
        <FadeIn className="text-center">
          <h2 className="text-3xl font-semibold tracking-tight">{t('landing.howItWorks.title')}</h2>
        </FadeIn>
        <BalancedGrid
          items={steps}
          columns={{ base: 1, sm: 3, lg: 3 }}
          itemWidth="18rem"
          renderItem={(step, i) => (
            <Card className="h-full">
              <CardContent className="flex h-full flex-col gap-3 p-6">
                <div className="flex items-center gap-3">
                  <span className="flex h-9 w-9 items-center justify-center rounded-md bg-primary text-sm font-semibold text-primary-foreground">
                    {i + 1}
                  </span>
                  <step.icon className="h-5 w-5 text-muted-foreground" />
                </div>
                <p className="font-semibold">{step.title}</p>
                <p className="text-sm text-muted-foreground">{step.description}</p>
              </CardContent>
            </Card>
          )}
        />
      </section>

      {/* Cognitive skill areas */}
      <section className="flex flex-col gap-8">
        <FadeIn className="text-center">
          <h2 className="text-3xl font-semibold tracking-tight">{t('landing.skillAreas.title')}</h2>
          <p className="mx-auto mt-2 max-w-xl text-muted-foreground">{t('landing.skillAreas.description')}</p>
        </FadeIn>
        <BalancedGrid
          items={[...SKILL_AREA_KEYS]}
          columns={{ base: 1, sm: 2, lg: 4 }}
          itemWidth="14rem"
          renderItem={(key) => (
            <div className="flex h-full items-center rounded-lg border border-border bg-card px-4 py-4">
              <span className="mr-3 h-8 w-1 shrink-0 rounded-full bg-[color:var(--brand-gold)]" aria-hidden />
              <p className="text-sm font-medium">{t(`categories.${key}`)}</p>
            </div>
          )}
        />
      </section>

      {/* Feature highlights */}
      <section className="flex flex-col gap-8">
        <FadeInStagger>
          <BalancedGrid
            items={features}
            columns={{ base: 1, sm: 2, lg: 3 }}
            itemWidth="18rem"
            renderItem={(feature) => (
              <FadeInItem>
                <MotionCard>
                  <CardContent className="flex h-full flex-col gap-3 p-6">
                    <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                      <feature.icon className="h-5 w-5" />
                    </span>
                    <p className="font-semibold">{feature.title}</p>
                    <p className="text-sm text-muted-foreground">{feature.description}</p>
                  </CardContent>
                </MotionCard>
              </FadeInItem>
            )}
          />
        </FadeInStagger>
      </section>

      {/* Bottom CTA */}
      <FadeIn className="flex flex-col items-center gap-5 rounded-xl bg-primary px-6 py-14 text-center text-primary-foreground">
        <h2 className="max-w-2xl text-3xl font-semibold leading-tight">{t('landing.heroTagline')}</h2>
        <Button asChild size="lg" variant="secondary">
          <Link to="/login">{t('landing.getStarted')}</Link>
        </Button>
      </FadeIn>
    </div>
  );
}
