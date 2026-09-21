/**
 * DEV-ONLY design preview. Lets a developer look at signed-in pages without a real
 * login by answering the app's API calls with made-up fixture data in the browser.
 * Nothing here touches the server, and this file is only imported under
 * `import.meta.env.DEV`, so it is not part of the production bundle.
 *
 * Use from the browser console:  await __preview.student();  __preview.go('/dashboard')
 *                                await __preview.admin();    __preview.go('/admin/dashboard')
 */
import { api } from '@/lib/api';
import { queryClient } from '@/lib/queryClient';

type Fixtures = Record<string, unknown | ((config: { url?: string; data?: string }) => unknown)>;

const day = (n: number) => new Date(Date.now() - n * 864e5).toISOString();
const ymd = (n: number) => day(n).slice(0, 10);
const inDays = (n: number) => new Date(Date.now() + n * 864e5).toISOString().slice(0, 10);

const CATEGORIES = [
  { id: 1, code: 'memory', name_en: 'Memory', name_si: 'Memory', description_en: 'Hold and recall information.', description_si: '', icon: null },
  { id: 2, code: 'logical_reasoning', name_en: 'Logical Reasoning', name_si: 'Logical Reasoning', description_en: 'Find rules and draw conclusions.', description_si: '', icon: null },
  { id: 3, code: 'numerical_ability', name_en: 'Numerical Ability', name_si: 'Numerical Ability', description_en: 'Work with numbers quickly.', description_si: '', icon: null },
  { id: 4, code: 'attention', name_en: 'Attention', name_si: 'Attention', description_en: 'Stay focused on what matters.', description_si: '', icon: null },
  { id: 5, code: 'spatial_pattern', name_en: 'Spatial Pattern', name_si: 'Spatial Pattern', description_en: 'See shapes and patterns.', description_si: '', icon: null },
];

const note = (id: number, cat: number, sub: string, title: string) => ({
  id, category_id: cat, subcategory: sub, title_en: title, title_si: title,
  learning_objective_en: 'Solve this type of question reliably in under a minute.', learning_objective_si: '',
  content_en: 'A short introduction to the idea, in plain words, with the one thing to remember.', content_si: '',
  worked_example_en: 'A can finish a job in 12 days and B in 6 days. Together: 1/12 + 1/6 = 1/4, so 4 days.', worked_example_si: '',
  key_technique_en: 'Add the work done per day, then invert.', key_technique_si: '',
  common_mistakes_en: 'Adding the days instead of the daily rates.', common_mistakes_si: '',
  key_concepts: ['rate', 'inverse'], generation_method: 'mock', created_at: day(3),
  category: CATEGORIES.find((c) => c.id === cat),
});

const STUDENT: Fixtures = {
  '/auth/me': { user: { id: 999, name: 'Demo Student', username: 'demo', email: 'demo@example.test', avatar_url: null, auth_provider: 'google', role: 'user', locale: 'en', current_level_id: 3, placement_completed_at: day(30) } },
  '/categories': { data: CATEGORIES },
  '/dashboard/summary': { data: {
    current_level: { id: 3, level_number: 3, name_en: 'Level 3 - Proficient', name_si: 'x' }, placement_completed_at: day(30), streak_days: 6,
    category_strengths: CATEGORIES.map((c, i) => ({ category_id: c.id, code: c.code, name_en: c.name_en, name_si: c.name_si, accuracy_percent: [72, 81, 58, 66, 49][i] })),
    recent_sessions: [{ id: 1, session_type: 'daily', category_name: 'Numerical Ability', score_percent: 73, completed_at: day(1) }, { id: 2, session_type: 'practice', category_name: 'Logical Reasoning', score_percent: 86, completed_at: day(2) }],
    game_scores: [{ game_code: 'memory_match', game_name: 'Memory Match', best_score: 940, plays: 5 }, { game_code: 'math_rush', game_name: 'Mental Math Rush', best_score: 610, plays: 3 }],
    iq_estimate: { iq_score: 112, classification: 'above_average', method: 'irt_theta', theta: 0.8, theta_se: 0.31 } } },
  '/dashboard/progress-history': { data: { level_history: [{ date: ymd(28), level_number: 2 }, { date: ymd(12), level_number: 3 }, { date: ymd(4), level_number: 3 }], accuracy_history: [] } },
  '/gamification/summary': { data: { xp: 1280, coins: 96, level: 4, level_title: 'Thinker', xp_into_level: 180, xp_for_next_level: 400, progress_percent: 45, streak_days: 6, badges_earned: 5, badges_total: 14 } },
  '/gamification/missions': { data: [
    { code: 'daily_practice', type: 'daily', period_key: 'd', progress: 1, target: 1, completed: true, claimed: false, xp_reward: 20, coin_reward: 5 },
    { code: 'daily_questions', type: 'daily', period_key: 'd', progress: 9, target: 15, completed: false, claimed: false, xp_reward: 25, coin_reward: 5 },
    { code: 'weekly_sessions', type: 'weekly', period_key: 'w', progress: 3, target: 5, completed: false, claimed: false, xp_reward: 60, coin_reward: 15 }] },
  '/gamification/badges': { data: ['first_steps', 'streak_3', 'streak_7', 'games_5', 'readiness_ready', 'top_ten', 'perfect_session', 'night_owl'].map((code, i) => ({ code, name_en: code.replace(/_/g, ' '), name_si: code, description_en: 'Earned by steady practice.', description_si: '', icon: ['footprints', 'flame', 'flame', 'gamepad-2', 'target', 'trophy', 'star', 'zap'][i], xp_reward: 50, coin_reward: 10, earned_at: i < 4 ? day(i + 1) : null })) },
  '/gamification/leaderboard': { data: { top: [{ rank: 1, user_id: 1, name: 'Nimali P.', xp: 3120, level: 8, is_you: false }, { rank: 2, user_id: 2, name: 'Kasun R.', xp: 2740, level: 7, is_you: false }, { rank: 3, user_id: 999, name: 'Demo Student', xp: 1280, level: 4, is_you: true }, { rank: 4, user_id: 4, name: 'Ishara W.', xp: 990, level: 3, is_you: false }], your_rank: 3 } },
  '/exam-profile': { data: { status: 'active', exam_category: 'other', exam_category_label: 'Other', exam_name: 'Management Assistant Exam', exam_date: inDays(42), daily_study_hours_target: 2, target_score: 70, exam_total_questions: null, exam_duration_minutes: null, pass_mark: null, negative_marking: null, exam_sections: null, target_seconds_per_question: null, days_remaining: 42, prep_progress_percent: 38, prep_day_number: 26, prep_total_days: 68, is_past_due: false, needs_outcome: false, outcome_attended: null, outcome_passed: null, outcome_score: null, outcome_recorded_at: null } },
  '/exam-profile/study-plan': { data: {
    phase: 'practice', exam_category: 'other', exam_name: 'Management Assistant Exam', days_remaining: 42, weeks_remaining: 6, prep_day_number: 26, prep_total_days: 68, prep_progress_percent: 38,
    weak_categories: [{ id: 5, code: 'spatial_pattern', name_en: 'Spatial Pattern', name_si: 'x' }, { id: 3, code: 'numerical_ability', name_en: 'Numerical Ability', name_si: 'x' }],
    strongest_category: { id: 2, code: 'logical_reasoning', name_en: 'Logical Reasoning', name_si: 'x' },
    recommended_daily_questions: 20, recommended_weekly_mock_tests: 1,
    daily_plan: [{ activity: 'weak_category_practice', category: { id: 5, code: 'spatial_pattern', name_en: 'Spatial Pattern', name_si: 'x' }, minutes: 30 }, { activity: 'weak_category_practice', category: { id: 3, code: 'numerical_ability', name_en: 'Numerical Ability', name_si: 'x' }, minutes: 25 }, { activity: 'confidence_review', category: null, minutes: 10 }],
    weekly_schedule: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'].map((d, i) => ({ day: d, focus: ['weak_1', 'weak_2', 'mixed', 'weak_1', 'mixed', 'mock', 'rest'][i], category: i < 2 ? { id: 5, code: 'spatial_pattern', name_en: 'Spatial Pattern', name_si: 'x' } : null })),
    phase_timeline: [{ phase: 'foundation', label_en: 'Foundation', label_si: 'x', from_days_remaining: 90, to_days_remaining: 60, is_current: false }, { phase: 'practice', label_en: 'Practice', label_si: 'x', from_days_remaining: 60, to_days_remaining: 30, is_current: true }, { phase: 'intensive', label_en: 'Intensive', label_si: 'x', from_days_remaining: 30, to_days_remaining: 7, is_current: false }, { phase: 'final_revision', label_en: 'Final revision', label_si: 'x', from_days_remaining: 7, to_days_remaining: 1, is_current: false }, { phase: 'exam_day', label_en: 'Exam day', label_si: 'x', from_days_remaining: 1, to_days_remaining: 0, is_current: false }],
    readiness_gap: { current_readiness_percent: 64, target_readiness_percent: 75, readiness_gap_points: 11, current_pace_seconds: null, target_pace_seconds: null, pace_gap_seconds: null, warning: { severity: 'medium', recommended_daily_minutes: 75, message_en: 'At the current pace you may not reach your target. Add about 15 minutes a day.', message_si: 'x' } } } },
  '/readiness/latest': { data: { readiness_percent: 64, readiness_label: 'needs_improvement', readiness_type: 'general', reasons: [{ feature: 'practice_streak', message: 'Your 6-day streak is helping.', direction: 'positive', impact: 0.3 }, { feature: 'numerical_score', message: 'Numerical accuracy is below your other areas.', direction: 'negative', impact: 0.2 }], model_version: '20260711054426', predicted_at: day(0), plain_english_explanation: 'You are on track, and numerical practice is the fastest way to improve.', risk_of_dropping_practice: { probability: 0.18, at_risk: false }, predicted_next_assessment_score: 68, predicted_score_change: 3 } },
  '/readiness/history': { data: [{ predicted_at: day(20), readiness_percent: 51, readiness_label: 'needs_improvement' }, { predicted_at: day(10), readiness_percent: 58, readiness_label: 'needs_improvement' }, { predicted_at: day(0), readiness_percent: 64, readiness_label: 'needs_improvement' }] },
  '/checkins/today': { data: null },
  '/games': { data: [['memory_match', 'Memory Match'], ['sequence_puzzle', 'Sequence Puzzle'], ['math_rush', 'Mental Math Rush'], ['mental_rotation', 'Mental Rotation Challenge'], ['selective_attention', 'Selective Attention Challenge'], ['working_memory_span', 'Working Memory Challenge'], ['visual_spatial_memory', 'Visual & Spatial Memory'], ['cognitive_command_center', 'Cognitive Command Center']].map(([code, n], i) => ({ id: i + 1, code, name_en: n, name_si: n, description_en: 'A quick round that trains one skill.', description_si: 'x' })) },
  '/study-notes': { data: [note(1, 3, 'work_time', 'Work and time'), note(2, 2, 'blood_relations', 'Blood relations'), note(3, 5, 'paper_folding', 'Paper folding'), note(4, 1, 'sequence_recall', 'Sequence recall'), note(5, 4, 'target_counting', 'Target counting')], current_page: 1, last_page: 1, total: 5 },
  '/study-notes/due-today': { data: [] },
  '/study-notes/recommendation': { data: { subcategory: 'work_time', accuracy: 42, study_note: note(1, 3, 'work_time', 'Work and time') } },
  '/feedback/mine': { data: [] },
};

const LEVELS = [1, 2, 3, 4, 5].map((n) => ({ id: n, level_number: n, name_en: ['Beginner', 'Developing', 'Proficient', 'Advanced', 'Expert'][n - 1], name_si: 'x' }));

const adminQuestion = (id: number, cat: number, text: string) => ({
  id, category_id: cat, level_id: 3, question_type: 'mcq_text', subcategory: 'work_time', question_text_en: text, question_text_si: 'x', image_path: null,
  options: [{ key: 'A', text_en: '3 days', text_si: '3' }, { key: 'B', text_en: '4 days', text_si: '4' }], correct_option_key: 'B', explanation_en: null, explanation_si: null,
  difficulty_weight: 3, is_active: id % 5 !== 0, category: CATEGORIES.find((c) => c.id === cat),
});

const ADMIN: Fixtures = {
  '/auth/me': { user: { id: 1, name: 'Site Admin', username: null, email: 'admin@example.test', avatar_url: null, auth_provider: 'password', role: 'super_admin', locale: 'en', current_level_id: null, placement_completed_at: null } },
  '/admin/categories': { data: CATEGORIES },
  '/admin/levels': { data: LEVELS },
  '/admin/sinhala/check': { data: { issues: {} } },
  '/admin/sinhala/translate': (config: { data?: string }) => {
    const fields = (JSON.parse(config.data ?? '{}').fields ?? {}) as Record<string, string>;
    const draft: Record<string, string> = {};
    for (const key of Object.keys(fields)) draft[key] = key === 'question' ? 'දුම්රියක් පැය 4 කින් කිලෝමීටර් 240 ක් ගමන් කරයි. කිලෝමීටර් 420 ක් ගමන් කිරීමට පැය කීයක් ගතවේද?' : fields[key];
    return { data: { translations: draft, issues: {} } };
  },
  '/admin/analytics/overview': { data: { total_students: 23, placement_completed: 19, sessions_completed: 212, average_score_percent: 68.4, level_distribution: LEVELS.map((l, i) => ({ level_number: l.level_number, total: [3, 5, 7, 3, 1][i] })), category_accuracy: CATEGORIES.map((c, i) => ({ category_code: c.code, category_name: c.name_en, accuracy_percent: String([71.2, 74.8, 61.5, 66.0, 55.3][i]), answers_count: 400 + i * 37 })) } },
  '/admin/questions': { data: [adminQuestion(1, 3, 'Carpenter A can finish a piece of furniture alone in 12 days and B in 6 days. Together?'), adminQuestion(2, 2, 'If A is the brother of B and B is the sister of C, what is A to C?'), adminQuestion(3, 5, 'Which figure completes the matrix?'), adminQuestion(4, 1, 'Recall the sequence of digits shown earlier.'), adminQuestion(5, 4, 'How many times does the letter E appear?'), adminQuestion(6, 3, 'A train travels 240 km in 4 hours. How long for 420 km?')], current_page: 1, last_page: 30, total: 6759 },
  '/admin/users': { data: [{ id: 1, name: 'Site Admin', email: 'admin@example.test', role: 'super_admin', auth_provider: 'password', current_level_id: null, current_level: null, placement_completed_at: null, daily_sessions_completed_count: 0, created_at: day(60) }, { id: 2, name: 'Nimali Perera', email: 'nimali@example.test', role: 'user', auth_provider: 'google', current_level_id: 4, current_level: { level_number: 4, name_en: 'Advanced', name_si: 'x' }, placement_completed_at: day(20), daily_sessions_completed_count: 14, created_at: day(40) }, { id: 3, name: 'Kasun Ranasinghe', email: 'kasun@example.test', role: 'user', auth_provider: 'google', current_level_id: 3, current_level: { level_number: 3, name_en: 'Proficient', name_si: 'x' }, placement_completed_at: day(12), daily_sessions_completed_count: 6, created_at: day(25) }], current_page: 1, last_page: 3, total: 23 },
};

function install(fixtures: Fixtures) {
  api.defaults.adapter = async (config) => {
    const url = (config.url ?? '').split('?')[0];
    const hit = fixtures[url];
    if (hit === undefined) return Promise.reject({ isAxiosError: true, response: { status: 404, data: {} }, config });
    const data = typeof hit === 'function' ? hit(config as { url?: string; data?: string }) : hit;
    return { data, status: 200, statusText: 'OK', headers: {}, config };
  };
  queryClient.clear();
}

const preview = {
  /** Fake signed-in student with plausible data. */
  student: async () => install(STUDENT),
  /** Fake signed-in admin (questions, users, analytics). */
  admin: async () => install(ADMIN),
  /** Extend or override fixtures on top of the current student set. */
  extend: (extra: Fixtures) => install({ ...STUDENT, ...extra }),
  go: (path: string) => {
    history.pushState({}, '', path);
    window.dispatchEvent(new PopStateEvent('popstate'));
  },
  fixtures: STUDENT,
};

(window as unknown as { __preview: typeof preview }).__preview = preview;
