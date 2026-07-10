import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useExamCategories, useExamProfile, useSaveExamProfile } from './useExamProfile';

export function ExamProfileDialog({ trigger }: { trigger: React.ReactNode }) {
  const { t } = useTranslation('dashboard');
  const [open, setOpen] = useState(false);
  const { data: categories } = useExamCategories();
  const { data: profile } = useExamProfile();

  const [examCategory, setExamCategory] = useState('');
  const [examName, setExamName] = useState('');
  const [examDate, setExamDate] = useState('');
  const [dailyHours, setDailyHours] = useState('1.5');
  const [targetScore, setTargetScore] = useState('');

  useEffect(() => {
    if (profile) {
      setExamCategory(profile.exam_category);
      setExamName(profile.exam_name ?? '');
      setExamDate(profile.exam_date ? profile.exam_date.slice(0, 10) : '');
      setDailyHours(String(profile.daily_study_hours_target));
      setTargetScore(profile.target_score !== null ? String(profile.target_score) : '');
    }
  }, [profile]);

  const save = useSaveExamProfile({
    onSuccess: () => {
      toast.success(t('examProfile.saved'));
      setOpen(false);
    },
  });

  const handleSubmit = () => {
    save.mutate({
      exam_category: examCategory,
      exam_name: examName.trim() || null,
      exam_date: examDate || null,
      daily_study_hours_target: Number(dailyHours),
      target_score: targetScore ? Number(targetScore) : null,
    });
  };

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>{trigger}</DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('examProfile.title')}</DialogTitle>
        </DialogHeader>
        <div className="flex flex-col gap-4">
          <div className="flex flex-col gap-1.5">
            <Label>{t('examProfile.examCategory')}</Label>
            <Select value={examCategory} onValueChange={setExamCategory}>
              <SelectTrigger className="w-full">
                <SelectValue placeholder={t('examProfile.selectExam')} />
              </SelectTrigger>
              <SelectContent>
                {categories?.map((c) => (
                  <SelectItem key={c.code} value={c.code}>
                    {c.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="flex flex-col gap-1.5">
            <Label htmlFor="exam-name">{t('examProfile.examName')}</Label>
            <Input
              id="exam-name"
              value={examName}
              onChange={(e) => setExamName(e.target.value)}
              placeholder={t('examProfile.examNamePlaceholder')}
            />
          </div>

          <div className="flex flex-col gap-1.5">
            <Label htmlFor="exam-date">{t('examProfile.examDate')}</Label>
            <Input id="exam-date" type="date" value={examDate} onChange={(e) => setExamDate(e.target.value)} />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="daily-hours">{t('examProfile.dailyHours')}</Label>
              <Input
                id="daily-hours"
                type="number"
                min={0.5}
                max={16}
                step={0.5}
                value={dailyHours}
                onChange={(e) => setDailyHours(e.target.value)}
              />
            </div>
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="target-score">{t('examProfile.targetScore')}</Label>
              <Input
                id="target-score"
                type="number"
                min={0}
                max={100}
                value={targetScore}
                onChange={(e) => setTargetScore(e.target.value)}
                placeholder="80"
              />
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button onClick={handleSubmit} disabled={!examCategory || save.isPending}>
            {save.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : t('examProfile.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
