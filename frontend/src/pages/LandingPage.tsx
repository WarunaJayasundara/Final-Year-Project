import { Link, Navigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { motion } from 'framer-motion';
import { Brain, Gamepad2, LineChart, ShieldCheck, Sparkles } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { FadeIn, FadeInItem, FadeInStagger } from '@/components/motion/FadeIn';
import { MotionCard } from '@/components/motion/MotionCard';
import { useCurrentUser } from '@/features/auth/useAuth';

const FEATURE_ICONS = [Brain, Sparkles, Gamepad2, LineChart];
const FEATURE_ACCENTS = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)'];
const FEATURE_KEYS = ['adaptive', 'ai', 'games', 'progress'] as const;

export function LandingPage() {
  const { t } = useTranslation();
  const { data: user } = useCurrentUser();

  if (user?.role === 'user') {
    return <Navigate to="/dashboard" replace />;
  }
  if (user?.role === 'admin' || user?.role === 'super_admin') {
    return <Navigate to="/admin/dashboard" replace />;
  }

  const features = FEATURE_KEYS.map((key, i) => ({
    key,
    icon: FEATURE_ICONS[i],
    accent: FEATURE_ACCENTS[i],
    title: t(`landing.features.${key}.title`),
    description: t(`landing.features.${key}.description`),
  }));

  return (
    <div className="flex flex-col gap-24 py-6">
      <section className="relative grid items-center gap-10 overflow-hidden md:grid-cols-2">
        {/* Floating gradient orbs for hero depth - purely decorative, aria-hidden. */}
        <motion.div
          aria-hidden
          className="gradient-orb -left-16 -top-24 h-72 w-72 bg-primary/30 dark:bg-primary/25"
          animate={{ y: [0, 18, 0], x: [0, 10, 0] }}
          transition={{ duration: 10, repeat: Infinity, ease: 'easeInOut' }}
        />
        <motion.div
          aria-hidden
          className="gradient-orb right-0 top-10 h-56 w-56 bg-[color:var(--chart-2)]/25"
          animate={{ y: [0, -14, 0], x: [0, -8, 0] }}
          transition={{ duration: 12, repeat: Infinity, ease: 'easeInOut', delay: 1 }}
        />

        <FadeIn className="relative flex flex-col items-start gap-6">
          <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary ring-1 ring-primary/20">
            <ShieldCheck className="h-3.5 w-3.5" /> {t('landing.badge')}
          </span>
          <h1 className="text-4xl font-semibold tracking-tight sm:text-5xl lg:text-6xl">
            {t('landing.heroTitle1')}
            <span className="bg-gradient-to-r from-primary via-primary to-primary/60 bg-clip-text text-transparent">
              {t('landing.heroTitle2')}
            </span>
          </h1>
          <p className="max-w-md text-lg text-muted-foreground">{t('tagline')}</p>
          <div className="flex flex-wrap gap-3">
            <Button asChild size="lg" className="shadow-lg shadow-primary/25 transition-shadow hover:shadow-primary/40">
              <Link to="/login">{t('landing.getStarted')}</Link>
            </Button>
            <Button asChild size="lg" variant="outline">
              <Link to="/admin/login">{t('nav.adminLogin')}</Link>
            </Button>
          </div>
        </FadeIn>

        <FadeIn delay={0.15} className="relative">
          <div className="absolute -inset-6 -z-10 rounded-3xl bg-gradient-to-br from-primary/25 via-primary/5 to-transparent blur-2xl" />
          <Card className="glass border-border/60 shadow-xl">
            <CardContent className="grid grid-cols-2 gap-4 p-6">
              {features.map((feature) => (
                <div
                  key={feature.key}
                  className="rounded-xl border border-border bg-muted/30 p-4 transition-colors hover:border-primary/40 hover:bg-primary/5"
                >
                  <feature.icon className="mb-2 h-5 w-5" style={{ color: feature.accent }} />
                  <p className="text-sm font-medium">{feature.title}</p>
                </div>
              ))}
            </CardContent>
          </Card>
        </FadeIn>
      </section>

      <FadeInStagger className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        {features.map((feature) => (
          <FadeInItem key={feature.key}>
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
        ))}
      </FadeInStagger>
    </div>
  );
}
