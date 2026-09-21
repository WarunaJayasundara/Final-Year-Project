import { useTranslation } from 'react-i18next';
import { Languages } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import type { SinhalaIssue } from './api';
import type { SinhalaAssist } from './useSinhalaAssist';

/** Inline problems for one Sinhala field: red = corrupted (blocks saving), amber = worth a second look. */
export function SinhalaIssueList({ issues }: { issues?: SinhalaIssue[] }) {
  if (!issues?.length) return null;
  return (
    <ul className="flex flex-col gap-0.5">
      {issues.map((issue) => (
        <li
          key={issue.code}
          className={`text-xs ${issue.severity === 'error' ? 'text-destructive' : 'text-warning-foreground dark:text-warning'}`}
        >
          {issue.message}
        </li>
      ))}
    </ul>
  );
}

/** The "draft Sinhala" button, its message, and the review gate for machine drafts. */
export function SinhalaAssistPanel({ assist }: { assist: SinhalaAssist }) {
  const { t } = useTranslation('admin');

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-col gap-3 rounded-lg border border-border bg-muted/40 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex flex-col gap-0.5">
          <p className="text-sm font-medium">{t('form.translateTitle')}</p>
          <p className="text-xs text-muted-foreground">{t('form.translateHint')}</p>
        </div>
        <Button type="button" variant="outline" onClick={assist.draftFromEnglish} disabled={assist.isTranslating} className="shrink-0">
          <Languages className="h-4 w-4" /> {assist.isTranslating ? t('form.translating') : t('form.translateButton')}
        </Button>
      </div>
      {assist.message && <p className="text-sm text-destructive">{assist.message}</p>}
      {assist.autoTranslated && (
        <div className="flex flex-col gap-2 rounded-lg border border-warning/40 bg-warning/10 p-3">
          <p className="text-sm">{t('form.machineDraftNotice')}</p>
          <label className="flex items-center gap-2 text-sm font-medium">
            <Checkbox checked={assist.reviewed} onCheckedChange={(v) => assist.setReviewed(!!v)} />
            {t('form.reviewLabel')}
          </label>
        </div>
      )}
    </div>
  );
}
