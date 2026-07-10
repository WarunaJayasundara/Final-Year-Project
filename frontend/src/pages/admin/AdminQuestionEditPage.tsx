import { useRef } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ImagePlus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FullPageSpinner } from '@/components/auth/RequireAuth';
import { QuestionForm } from '@/features/admin/QuestionForm';
import { useAdminQuestion, useUpdateQuestion, useUploadQuestionImage } from '@/features/admin/useAdmin';
import type { QuestionPayload } from '@/features/admin/api';

export function AdminQuestionEditPage() {
  const { t } = useTranslation('admin');
  const { id } = useParams();
  const questionId = Number(id);
  const navigate = useNavigate();
  const { data: question, isLoading } = useAdminQuestion(questionId);
  const updateQuestion = useUpdateQuestion();
  const uploadImage = useUploadQuestionImage();
  const fileInputRef = useRef<HTMLInputElement>(null);

  if (isLoading || !question) {
    return <FullPageSpinner />;
  }

  const handleSubmit = async (payload: QuestionPayload) => {
    await updateQuestion.mutateAsync({ id: questionId, payload });
    navigate('/admin/questions');
  };

  const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      await uploadImage.mutateAsync({ id: questionId, file });
    }
  };

  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-6">
      {question.question_type === 'mcq_image' && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t('questions.pattern.title')}</CardTitle>
          </CardHeader>
          <CardContent className="flex items-center gap-4">
            {question.image_path && (
              <img
                src={`/storage/${question.image_path}`}
                alt=""
                className="h-24 w-24 rounded-lg border border-border object-contain"
              />
            )}
            <input ref={fileInputRef} type="file" accept="image/*" className="hidden" onChange={handleFileChange} />
            <Button type="button" variant="outline" onClick={() => fileInputRef.current?.click()} disabled={uploadImage.isPending}>
              <ImagePlus className="h-4 w-4" />
              {uploadImage.isPending
                ? t('questions.pattern.uploading')
                : question.image_path
                  ? t('questions.pattern.replace')
                  : t('questions.pattern.upload')}
            </Button>
          </CardContent>
        </Card>
      )}

      <Card>
        <CardHeader>
          <CardTitle>{t('questions.editTitle')}</CardTitle>
        </CardHeader>
        <CardContent>
          <QuestionForm
            initialValues={question}
            submitLabel={t('questions.saveSubmit')}
            isSubmitting={updateQuestion.isPending}
            onSubmit={handleSubmit}
          />
        </CardContent>
      </Card>
    </div>
  );
}
