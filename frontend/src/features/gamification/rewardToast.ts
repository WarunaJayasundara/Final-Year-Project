import { toast } from 'sonner';
import i18n from '@/lib/i18n';
import type { RewardResult } from './types';

/**
 * Shared toast trigger for any action that can award XP/coins/badges
 * (session completion, game scores, exam-profile setup, readiness
 * predictions) - called from mutation onSuccess callbacks, not component
 * render, so it reads the locale-resolved i18n instance directly rather
 * than requiring a `t` function to be threaded through every call site.
 */
export function showRewardToast(rewards: RewardResult | undefined | null): void {
  if (!rewards) {
    return;
  }

  if (rewards.xp > 0 || rewards.coins > 0) {
    toast.success(i18n.t('gamification:reward.earned', { xp: rewards.xp, coins: rewards.coins }));
  }

  const locale = i18n.language?.startsWith('si') ? 'si' : 'en';

  rewards.new_badges.forEach((badge) => {
    const name = locale === 'si' ? badge.name_si : badge.name_en;
    toast(i18n.t('gamification:reward.badgeUnlocked', { name }));
  });
}
