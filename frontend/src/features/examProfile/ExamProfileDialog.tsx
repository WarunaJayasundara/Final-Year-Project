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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useExamProfile, useSaveExamProfile } from './useExamProfile';

const HOUR_OPTIONS = Array.from({ length: 17 }, (_, i) => i); // 0-16 hours
const MINUTE_OPTIONS = [0, 30]; // matches the daily_study_hours_target decimal(4,1) column's precision

/**
 * Exam-profile setup: no fixed exam-category picker (removed - the platform
 * covers far more exams than any fixed list could enumerate). The student
 * just names their exam, sets the date, states the marks they need out of
 * 100, and says how long per day they can realistically use the system -
 * asked once here, not re-asked on every check-in (see ReadinessCard.tsx's
 * check-in dialog, which only asks same-day motivation/attendance now).
 * StudyPlanService and the ML feature pipeline both read
 * daily_study_hours_target from this profile.
 */
export function ExamProfileDialog({ trigger }: { trigger: React.ReactNode }) {
  const { t } = useTranslation('dashboard');
  const [open, setOpen] = useState(false);
  const { data: profile } = useExamProfile();

  const [examName, setExamName] = useState('');
  const [examDate, setExamDate] = useState('');
  const [targetScore, setTargetScore] = useState('');
  // Shown to the student as separate hour/minute pickers (brief: no raw
  // decimals like "1.5 hours") but combined back into the single decimal
  // daily_study_hours_target field the backend already stores.
  const [dailyHoursPart, setDailyHoursPart] = useState(1);
  const [dailyMinutesPart, setDailyMinutesPart] = useState(30);
  // Optional real-exam structure (brief §6: "student must be able to skip
  // these questions if they are not preparing for a specific examination") -
  // used only to derive a pace target (seconds/question) and size mock exams.
  const [totalQuestions, setTotalQuestions] = useState('');
  const [durationMinutes, setDurationMinutes] = useState('');
  const [passMark, setPassMark] = useState('');
  const [negativeMarking, setNegativeMarking] = useState(false);

  useEffect(() => {
    if (profile) {
      setExamName(profile.exam_name ?? '');
      setExamDate(profile.exam_date ? profile.exam_date.slice(0, 10) : '');
      setTargetScore(profile.target_score !== null ? String(profile.target_score) : '');
      const totalHours = Number(profile.daily_study_hours_target);
      setDailyHoursPart(Math.floor(totalHours));
      setDailyMinutesPart(Math.round((totalHours - Math.floor(totalHours)) * 60) >= 30 ? 30 : 0);
      setTotalQuestions(profile.exam_total_questions !== null ? String(profile.exam_total_questions) : '');
      setDurationMinutes(profile.exam_duration_minutes !== null ? String(profile.exam_duration_minutes) : '');
      setPassMark(profile.pass_mark !== null ? String(profile.pass_mark) : '');
      setNegativeMarking(profile.negative_marking ?? false);
    }
  }, [profile]);

  const save = useSaveExamProfile({
    onSuccess: () => {
      toast.success(t('examProfile.saved'));
      setOpen(false);
    },
  });

  const canSubmit = examName.trim() !== '' && examDate !== '';

  const handleSubmit = () => {
    if (!canSubmit) return;
    save.mutate({
      exam_name: examName.trim(),
      exam_date: examDate,
      daily_study_hours_target: dailyHoursPart + dailyMinutesPart / 60,
      target_score: targetScore ? Number(targetScore) : null,
      exam_total_questions: totalQuestions ? Number(totalQuestions) : null,
      exam_duration_minutes: durationMinutes ? Number(durationMinutes) : null,
      pass_mark: passMark ? Number(passMark) : null,
      negative_marking: negativeMarking,
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

          <div className="flex flex-col gap-1.5">
            <Label>{t('examProfile.dailyHours')}</Label>
            <div className="flex gap-2">
              <Select value={String(dailyHoursPart)} onValueChange={(v) => setDailyHoursPart(Number(v))}>
                <SelectTrigger className="flex-1">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {HOUR_OPTIONS.map((h) => (
                    <SelectItem key={h} value={String(h)}>
                      {t('examProfile.hoursCount', { count: h })}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Select value={String(dailyMinutesPart)} onValueChange={(v) => setDailyMinutesPart(Number(v))}>
                <SelectTrigger className="flex-1">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {MINUTE_OPTIONS.map((m) => (
                    <SelectItem key={m} value={String(m)}>
                      {t('examProfile.minutesCount', { count: m })}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <p className="text-xs text-muted-foreground">{t('examProfile.dailyHoursHint')}</p>
          </div>

          <div className="flex flex-col gap-3 rounded-lg border border-dashed border-border p-3">
            <div>
              <p className="text-sm font-medium">{t('examProfile.realExamDetailsTitle')}</p>
              <p className="text-xs text-muted-foreground">{t('examProfile.realExamDetailsHint')}</p>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="exam-total-questions">{t('examProfile.examTotalQuestions')}</Label>
                <Input
                  id="exam-total-questions"
                  type="number"
                  min={1}
                  max={500}
                  value={totalQuestions}
                  onChange={(e) => setTotalQuestions(e.target.value)}
                  placeholder="100"
                />
              </div>
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="exam-duration-minutes">{t('examProfile.examDurationMinutes')}</Label>
                <Input
                  id="exam-duration-minutes"
                  type="number"
                  min={1}
                  max={600}
                  value={durationMinutes}
                  onChange={(e) => setDurationMinutes(e.target.value)}
                  placeholder="120"
                />
              </div>
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="pass-mark">{t('examProfile.passMark')}</Label>
                <Input
                  id="pass-mark"
                  type="number"
                  min={0}
                  max={100}
                  value={passMark}
                  onChange={(e) => setPassMark(e.target.value)}
                  placeholder="50"
                />
              </div>
              <div className="flex items-center gap-2 pt-6">
                <Checkbox
                  id="negative-marking"
                  checked={negativeMarking}
                  onCheckedChange={(checked) => setNegativeMarking(checked === true)}
                />
                <Label htmlFor="negative-marking" className="font-normal">
                  {t('examProfile.negativeMarking')}
                </Label>
              </div>
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button onClick={handleSubmit} disabled={!canSubmit || save.isPending}>
            {save.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : t('examProfile.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
