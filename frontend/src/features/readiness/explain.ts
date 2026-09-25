import type { TFunction } from 'i18next';
import type { ReadinessReason } from './types';

const NS = 'dashboard';

/** The reason text in the user's language; the server's English text is only a last-resort fallback. */
export function reasonText(reason: ReadinessReason, t: TFunction): string {
  return t(`readiness.reasonText.${reason.feature}.${reason.direction === 'positive' ? 'pos' : 'neg'}`, {
    ns: NS,
    defaultValue: reason.message,
  });
}

/** The "why did my estimate change" sentence, built from the structured reasons so it can be shown in either language. */
export function explanationText(reasons: ReadinessReason[], t: TFunction): string {
  const clauses = reasons.slice(0, 3).map((reason) => {
    const label = t(`readiness.featureLabel.${reason.feature}`, { ns: NS, defaultValue: reason.feature.replace(/_/g, ' ') });
    const change = reason.pct_change_since_last;

    if (change != null && change <= -10) return t('readiness.explain.dropped', { ns: NS, label, pct: Math.round(Math.abs(change)) });
    if (change != null && change >= 10) return t('readiness.explain.increased', { ns: NS, label, pct: Math.round(change) });

    return t(reason.direction === 'positive' ? 'readiness.explain.strength' : 'readiness.explain.weak', { ns: NS, label });
  });

  if (clauses.length === 0) return t('readiness.explain.none', { ns: NS });
  if (clauses.length === 1) return t('readiness.explain.one', { ns: NS, clause: clauses[0] });

  return t('readiness.explain.many', { ns: NS, clauses: clauses.slice(0, -1).join(', '), last: clauses[clauses.length - 1] });
}
