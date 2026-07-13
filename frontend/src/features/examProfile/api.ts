import { api } from '@/lib/api';
import type { BadgeReward } from '@/features/gamification/types';
import type { ExamCategoryOption, ExamOutcomeInput, ExamProfile, ExamProfileInput, StudyPlan } from './types';

export async function fetchExamCategories(): Promise<ExamCategoryOption[]> {
  const { data } = await api.get<{ data: ExamCategoryOption[] }>('/exam-profile/categories');
  return data.data;
}

export async function fetchExamProfile(): Promise<ExamProfile | null> {
  const { data } = await api.get<{ data: ExamProfile | null }>('/exam-profile');
  return data.data;
}

export async function saveExamProfile(input: ExamProfileInput): Promise<{ profile: ExamProfile; newBadges: BadgeReward[] }> {
  const { data } = await api.post<{ data: ExamProfile; new_badges: BadgeReward[] }>('/exam-profile', input);
  return { profile: data.data, newBadges: data.new_badges };
}

export async function fetchStudyPlan(): Promise<StudyPlan> {
  const { data } = await api.get<{ data: StudyPlan }>('/exam-profile/study-plan');
  return data.data;
}

export async function fetchExamHistory(): Promise<ExamProfile[]> {
  const { data } = await api.get<{ data: ExamProfile[] }>('/exam-profile/history');
  return data.data;
}

export async function submitExamOutcome(input: ExamOutcomeInput): Promise<ExamProfile> {
  const { data } = await api.post<{ data: ExamProfile }>('/exam-profile/outcome', input);
  return data.data;
}
