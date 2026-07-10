import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { QuestionForm } from '@/features/admin/QuestionForm';
import { useCreateQuestion } from '@/features/admin/useAdmin';
import type { QuestionPayload } from '@/features/admin/api';

export function AdminQuestionNewPage() {
  const { t } = useTranslation('admin');
  const navigate = useNavigate();
  const createQuestion = useCreateQuestion();

  const handleSubmit = async (payload: QuestionPayload) => {
    const question = await createQuestion.mutateAsync(payload);
    navigate(`/admin/questions/${question.id}/edit`);
  };

  return (
    <div className="mx-auto max-w-3xl">
      <Card>
        <CardHeader>
          <CardTitle>{t('questions.createTitle')}</CardTitle>
        </CardHeader>
        <CardContent>
          <QuestionForm
            submitLabel={t('questions.createSubmit')}
            isSubmitting={createQuestion.isPending}
            onSubmit={handleSubmit}
          />
        </CardContent>
      </Card>
    </div>
  );
}
