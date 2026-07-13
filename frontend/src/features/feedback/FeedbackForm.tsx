import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { MessageSquareHeart } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { StarRating } from './StarRating';
import { useSubmitFeedback } from './useFeedback';

export function FeedbackForm() {
  const { t } = useTranslation('profile');
  const [overall, setOverall] = useState(0);
  const [ui, setUi] = useState(0);
  const [questionQuality, setQuestionQuality] = useState(0);
  const [sinhalaQuality, setSinhalaQuality] = useState(0);
  const [usefulness, setUsefulness] = useState(0);
  const [comment, setComment] = useState('');
  const [suggestion, setSuggestion] = useState('');

  const submit = useSubmitFeedback({
    onSuccess: () => {
      toast.success(t('feedback.submitted'));
      setOverall(0);
      setUi(0);
      setQuestionQuality(0);
      setSinhalaQuality(0);
      setUsefulness(0);
      setComment('');
      setSuggestion('');
    },
    onError: () => toast.error(t('feedback.submitFailed')),
  });

  const handleSubmit = () => {
    if (overall === 0) return;
    submit.mutate({
      overall_rating: overall,
      ui_rating: ui || null,
      question_quality_rating: questionQuality || null,
      sinhala_quality_rating: sinhalaQuality || null,
      usefulness_rating: usefulness || null,
      comment: comment.trim() || null,
      suggestion: suggestion.trim() || null,
    });
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <MessageSquareHeart className="h-4 w-4" /> {t('feedback.title')}
        </CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-5">
        <div className="flex flex-col gap-1.5">
          <Label>{t('feedback.overall')}</Label>
          <StarRating value={overall} onChange={setOverall} />
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <div className="flex flex-col gap-1.5">
            <Label>{t('feedback.ui')}</Label>
            <StarRating value={ui} onChange={setUi} size="sm" />
          </div>
          <div className="flex flex-col gap-1.5">
            <Label>{t('feedback.questionQuality')}</Label>
            <StarRating value={questionQuality} onChange={setQuestionQuality} size="sm" />
          </div>
          <div className="flex flex-col gap-1.5">
            <Label>{t('feedback.sinhalaQuality')}</Label>
            <StarRating value={sinhalaQuality} onChange={setSinhalaQuality} size="sm" />
          </div>
          <div className="flex flex-col gap-1.5">
            <Label>{t('feedback.usefulness')}</Label>
            <StarRating value={usefulness} onChange={setUsefulness} size="sm" />
          </div>
        </div>

        <div className="flex flex-col gap-1.5">
          <Label htmlFor="feedback-comment">{t('feedback.comment')}</Label>
          <Textarea id="feedback-comment" value={comment} onChange={(e) => setComment(e.target.value)} rows={3} />
        </div>

        <div className="flex flex-col gap-1.5">
          <Label htmlFor="feedback-suggestion">{t('feedback.suggestion')}</Label>
          <Textarea id="feedback-suggestion" value={suggestion} onChange={(e) => setSuggestion(e.target.value)} rows={3} />
        </div>

        <Button onClick={handleSubmit} disabled={overall === 0 || submit.isPending} className="self-start">
          {submit.isPending ? t('feedback.submitting') : t('feedback.submit')}
        </Button>
      </CardContent>
    </Card>
  );
}
