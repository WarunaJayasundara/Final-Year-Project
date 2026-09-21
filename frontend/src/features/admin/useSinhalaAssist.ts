import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { apiErrorMessage } from '@/lib/apiError';
import { useCheckSinhala, useTranslateToSinhala } from './useAdmin';
import type { SinhalaIssues } from './api';
import type { QuestionOptionInput } from './types';

interface SinhalaAssistInput {
  textEn: string;
  textSi: string;
  setTextSi: (value: string) => void;
  explanationEn: string;
  explanationSi: string;
  setExplanationSi: (value: string) => void;
  options: QuestionOptionInput[];
  setOptions: (updater: (prev: QuestionOptionInput[]) => QuestionOptionInput[]) => void;
}

/**
 * Sinhala tooling shared by the "new question" wizard and the "edit question" form:
 *  - a debounced integrity check of whatever Sinhala is typed (red = corrupted, blocks saving),
 *  - a "draft Sinhala from English" action that fills only EMPTY Sinhala fields, so it can
 *    never overwrite text an admin has written or corrected,
 *  - a review gate: a machine draft must be ticked as reviewed before the question can be saved.
 */
export function useSinhalaAssist(input: SinhalaAssistInput) {
  const { t } = useTranslation('admin');
  const { textEn, textSi, setTextSi, explanationEn, explanationSi, setExplanationSi, options, setOptions } = input;

  const [issues, setIssues] = useState<SinhalaIssues>({});
  const [autoTranslated, setAutoTranslated] = useState(false);
  const [reviewed, setReviewed] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const translate = useTranslateToSinhala();
  const check = useCheckSinhala();

  useEffect(() => {
    const fields: Record<string, { si: string; en?: string; is_option?: boolean }> = {};
    if (textSi.trim()) fields.question = { si: textSi, en: textEn };
    if (explanationSi.trim()) fields.explanation = { si: explanationSi, en: explanationEn };
    options.forEach((o) => {
      if (o.text_si.trim()) fields[`option_${o.key}`] = { si: o.text_si, en: o.text_en, is_option: true };
    });
    if (Object.keys(fields).length === 0) {
      setIssues({});
      return;
    }
    let cancelled = false;
    const timer = setTimeout(async () => {
      try {
        const result = await check.mutateAsync(fields);
        if (!cancelled) setIssues(result);
      } catch {
        // Advisory in the UI; the server still rejects corrupted text on save.
      }
    }, 700);
    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [textSi, textEn, explanationSi, explanationEn, options]);

  const hasBlockingIssue = Object.values(issues).some((list) => list.some((i) => i.severity === 'error'));

  const draftFromEnglish = async () => {
    setMessage(null);
    if (!textEn.trim()) {
      setMessage(t('form.translateNeedsEnglish'));
      return;
    }

    // Only fields that have English but no Sinhala yet.
    const fields: Record<string, string> = {};
    if (!textSi.trim()) fields.question = textEn;
    if (explanationEn.trim() && !explanationSi.trim()) fields.explanation = explanationEn;
    options.forEach((o) => {
      if (o.text_en.trim() && !o.text_si.trim()) fields[`option_${o.key}`] = o.text_en;
    });
    if (Object.keys(fields).length === 0) {
      setMessage(t('form.translateNothing'));
      return;
    }

    try {
      const result = await translate.mutateAsync(fields);
      const { translations } = result;
      if (translations.question) setTextSi(translations.question);
      if (translations.explanation) setExplanationSi(translations.explanation);
      setOptions((prev) =>
        prev.map((o) => (translations[`option_${o.key}`] && !o.text_si.trim() ? { ...o, text_si: translations[`option_${o.key}`] } : o)),
      );
      setAutoTranslated(true);
      setReviewed(false);
    } catch (e) {
      // The server's own text is English; show a translated message instead.
      setMessage(`${t('form.translateFailed')} ${apiErrorMessage(e, t)}`);
    }
  };

  return {
    issues,
    hasBlockingIssue,
    autoTranslated,
    reviewed,
    setReviewed,
    message,
    isTranslating: translate.isPending,
    draftFromEnglish,
  };
}

export type SinhalaAssist = ReturnType<typeof useSinhalaAssist>;
