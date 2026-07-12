import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { QuestionWizard } from '@/features/admin/QuestionWizard';
import { useCreateQuestion, useUploadQuestionImage } from '@/features/admin/useAdmin';
import type { QuestionPayload } from '@/features/admin/api';

export function AdminQuestionNewPage() {
  const { t } = useTranslation('admin');
  const navigate = useNavigate();
  const createQuestion = useCreateQuestion();
  const uploadImage = useUploadQuestionImage();

  const handleSubmit = async (payload: QuestionPayload, imageFile: File | null) => {
    const question = await createQuestion.mutateAsync(payload);
    if (imageFile) {
      await uploadImage.mutateAsync({ id: question.id, file: imageFile });
    }
    navigate('/admin/questions');
  };

  return (
    <div className="mx-auto max-w-3xl">
      <h1 className="mb-6 text-2xl font-semibold">{t('questions.createTitle')}</h1>
      <QuestionWizard onSubmit={handleSubmit} isSubmitting={createQuestion.isPending || uploadImage.isPending} />
    </div>
  );
}
