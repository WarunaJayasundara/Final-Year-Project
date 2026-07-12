import { Link, Navigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { motion } from 'framer-motion';
import {
  BarChart3,
  BookOpenCheck,
  CalendarClock,
  ClipboardCheck,
  Flame,
  Gamepad2,
  Languages,
  ListChecks,
  MessageSquareText,
  Target,
  TrendingUp,
  ShieldCheck,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { FadeIn, FadeInItem, FadeInStagger } from '@/components/motion/FadeIn';
import { MotionCard } from '@/components/motion/MotionCard';
import { BalancedGrid } from '@/components/ui/balanced-grid';
import { HelaIQMark } from '@/components/brand/HelaIQMark';
import { useCurrentUser } from '@/features/auth/useAuth';

const HOW_IT_WORKS_ICONS = [Target, CalendarClock, TrendingUp];
const HOW_IT_WORKS_KEYS = ['assess', 'practice', 'track'] as const;

const SKILL_AREA_KEYS = ['memory', 'logical_reasoning', 'numerical_ability', 'attention', 'spatial_pattern'] as const;

const FEATURE_ICONS = [Target, ClipboardCheck, BarChart3, Gamepad2, MessageSquareText, Languages];
const FEATURE_ACCENTS = [
  'var(--chart-1)',
  'var(--chart-2)',
  'var(--chart-3)',
  'var(--chart-4)',
  'var(--chart-5)',
  'var(--chart-1)',
];
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
    accent: FEATURE_ACCENTS[i],
    title: t(`landing.features.${key}.title`),
    description: t(`landing.features.${key}.description`),
  }));

  return (
    <div className="flex flex-col gap-20 py-6">
      {/* Hero */}
      <section className="relative grid items-center gap-10 overflow-hidden md:grid-cols-2">
        <motion.div
          aria-hidden
          className="gradient-orb -left-16 -top-24 h-72 w-72 bg-primary/25 dark:bg-primary/20"
          initial={{ opacity: 0, y: -10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 1.2, ease: 'easeOut' }}
        />
        <motion.div
          aria-hidden
          className="gradient-orb right-0 top-10 h-56 w-56 bg-[color:var(--chart-2)]/20"
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 1.2, ease: 'easeOut', delay: 0.2 }}
        />

        <FadeIn className="relative flex flex-col items-start gap-6">
          <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary ring-1 ring-primary/20">
            <ShieldCheck className="h-3.5 w-3.5" /> {t('landing.badge')}
          </span>
          <h1 className="text-4xl font-semibold tracking-tight sm:text-5xl">{t('landing.heroTagline')}</h1>
          <p className="max-w-md text-lg text-muted-foreground">{t('tagline')}</p>
          <div className="flex flex-wrap gap-3">
            <Button asChild size="lg" className="shadow-lg shadow-primary/25 transition-shadow hover:shadow-primary/40">
              <Link to="/login">{t('landing.getStarted')}</Link>
            </Button>
          </div>
        </FadeIn>

        {/* Product preview - a real composition of the design system's own components, not a stock image. */}
        <FadeIn delay={0.15} className="relative">
          <div className="absolute -inset-6 -z-10 rounded-3xl bg-gradient-to-br from-primary/20 via-primary/5 to-transparent blur-2xl" />
          <Card className="border-border shadow-xl">
            <CardContent className="flex flex-col gap-4 p-6">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <HelaIQMark variant="compact" />
                  <span className="text-sm font-medium text-muted-foreground">{t('landing.preview.readinessLabel')}</span>
                </div>
                <Badge variant="success">82%</Badge>
              </div>
              <Progress value={82} />
              <div className="grid grid-cols-2 gap-3 pt-2">
                <div className="rounded-lg border border-border bg-muted/30 p-3">
                  <p className="text-xs text-muted-foreground">{t('landing.preview.levelLabel')}</p>
                  <p className="mt-1 text-lg font-semibold">{t('levels.4')}</p>
                </div>
                <div className="rounded-lg border border-border bg-muted/30 p-3">
                  <p className="text-xs text-muted-foreground">{t('landing.preview.streakLabel')}</p>
                  <p className="mt-1 flex items-center gap-1 text-lg font-semibold">
                    <Flame className="h-4 w-4 text-[color:var(--chart-4)]" /> 12
                  </p>
                </div>
              </div>
              <div className="flex items-end gap-1.5 pt-1">
                {[38, 52, 46, 61, 58, 70, 82].map((v, i) => (
                  <div
                    key={i}
                    className="flex-1 rounded-t bg-primary/70"
                    style={{ height: `${v * 0.5}px` }}
                    aria-hidden
                  />
                ))}
              </div>
            </CardContent>
          </Card>
        </FadeIn>
      </section>

      {/* How it works */}
      <section className="flex flex-col gap-8">
        <FadeIn className="text-center">
          <h2 className="text-2xl font-semibold tracking-tight">{t('landing.howItWorks.title')}</h2>
        </FadeIn>
        <BalancedGrid
          items={steps}
          columns={{ base: 1, sm: 3, lg: 3 }}
          itemWidth="18rem"
          renderItem={(step, i) => (
            <Card className="h-full">
              <CardContent className="flex h-full flex-col gap-3 p-6">
                <div className="flex items-center gap-3">
                  <span className="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
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
          <h2 className="text-2xl font-semibold tracking-tight">{t('landing.skillAreas.title')}</h2>
          <p className="mx-auto mt-2 max-w-xl text-muted-foreground">{t('landing.skillAreas.description')}</p>
        </FadeIn>
        <BalancedGrid
          items={SKILL_AREA_KEYS}
          columns={{ base: 1, sm: 2, lg: 4 }}
          itemWidth="14rem"
          renderItem={(key) => (
            <div className="flex h-full items-center rounded-xl border border-border bg-muted/30 px-4 py-4">
              <span className="mr-3 h-8 w-1 shrink-0 rounded-full bg-primary/60" aria-hidden />
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
                    <span
                      className="flex h-10 w-10 items-center justify-center rounded-lg"
                      style={{ backgroundColor: `color-mix(in oklch, ${feature.accent}, transparent 85%)`, color: feature.accent }}
                    >
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
      <FadeIn className="flex flex-col items-center gap-4 rounded-2xl border border-border bg-muted/30 px-6 py-12 text-center">
        <ListChecks className="h-6 w-6 text-primary" />
        <h2 className="text-2xl font-semibold tracking-tight">{t('landing.heroTagline')}</h2>
        <Button asChild size="lg">
          <Link to="/login">
            <BookOpenCheck className="h-4 w-4" /> {t('landing.getStarted')}
          </Link>
        </Button>
      </FadeIn>
    </div>
  );
}
