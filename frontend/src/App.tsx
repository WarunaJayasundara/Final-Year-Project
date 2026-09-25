import { lazy, type ComponentType } from 'react';
import { Route, Routes } from 'react-router-dom';
import { MainLayout } from '@/components/layout/MainLayout';
import { AdminLayout } from '@/components/layout/AdminLayout';
import { RequireAuth } from '@/components/auth/RequireAuth';
import { RequireRole } from '@/components/auth/RequireRole';
import { RequirePlacement } from '@/components/auth/RequirePlacement';
import { LandingPage } from '@/pages/LandingPage';
import { LoginPage } from '@/pages/LoginPage';
import { RegisterPage } from '@/pages/RegisterPage';
import { ForgotPasswordPage } from '@/pages/ForgotPasswordPage';
import { NotFoundPage } from '@/pages/NotFoundPage';
import { AdminLoginPage } from '@/pages/AdminLoginPage';
import { AuthCallbackPage } from '@/pages/AuthCallbackPage';

/** Code-splitting helper: every authenticated page (student, games. */
function lazyPage<K extends string>(loader: () => Promise<Record<K, ComponentType>>, name: K) {
  return lazy(() => loader().then((m) => ({ default: m[name] })));
}

const PlacementPage = lazyPage(() => import('@/pages/PlacementPage'), 'PlacementPage');
const DashboardPage = lazyPage(() => import('@/pages/DashboardPage'), 'DashboardPage');
const StudyPlanPage = lazyPage(() => import('@/pages/StudyPlanPage'), 'StudyPlanPage');
const DailyTestPage = lazyPage(() => import('@/pages/DailyTestPage'), 'DailyTestPage');
const PracticeTestPage = lazyPage(() => import('@/pages/PracticeTestPage'), 'PracticeTestPage');
const MockExamSetupPage = lazyPage(() => import('@/pages/MockExamSetupPage'), 'MockExamSetupPage');
const SessionReportPage = lazyPage(() => import('@/pages/SessionReportPage'), 'SessionReportPage');
const GamesHubPage = lazyPage(() => import('@/pages/GamesHubPage'), 'GamesHubPage');
const MemoryMatchPage = lazyPage(() => import('@/pages/games/MemoryMatchPage'), 'MemoryMatchPage');
const SequencePuzzlePage = lazyPage(() => import('@/pages/games/SequencePuzzlePage'), 'SequencePuzzlePage');
const MathRushPage = lazyPage(() => import('@/pages/games/MathRushPage'), 'MathRushPage');
const MentalRotationPage = lazyPage(() => import('@/pages/games/MentalRotationPage'), 'MentalRotationPage');
const SelectiveAttentionPage = lazyPage(() => import('@/pages/games/SelectiveAttentionPage'), 'SelectiveAttentionPage');
const WorkingMemorySpanPage = lazyPage(() => import('@/pages/games/WorkingMemorySpanPage'), 'WorkingMemorySpanPage');
const VisualSpatialMemoryPage = lazyPage(() => import('@/pages/games/VisualSpatialMemoryPage'), 'VisualSpatialMemoryPage');
const CognitiveCommandCenterPage = lazyPage(() => import('@/pages/games/CognitiveCommandCenterPage'), 'CognitiveCommandCenterPage');
const AdminDashboardPage = lazyPage(() => import('@/pages/admin/AdminDashboardPage'), 'AdminDashboardPage');
const AdminQuestionsListPage = lazyPage(() => import('@/pages/admin/AdminQuestionsListPage'), 'AdminQuestionsListPage');
const AdminQuestionNewPage = lazyPage(() => import('@/pages/admin/AdminQuestionNewPage'), 'AdminQuestionNewPage');
const AdminQuestionEditPage = lazyPage(() => import('@/pages/admin/AdminQuestionEditPage'), 'AdminQuestionEditPage');
const AdminVisualGeneratorPage = lazyPage(() => import('@/pages/admin/AdminVisualGeneratorPage'), 'AdminVisualGeneratorPage');
const AdminCategoriesPage = lazyPage(() => import('@/pages/admin/AdminCategoriesPage'), 'AdminCategoriesPage');
const AdminUsersPage = lazyPage(() => import('@/pages/admin/AdminUsersPage'), 'AdminUsersPage');
const AdminPsychometricsPage = lazyPage(() => import('@/pages/admin/AdminPsychometricsPage'), 'AdminPsychometricsPage');
const AdminQuestionBankPage = lazyPage(() => import('@/pages/admin/AdminQuestionBankPage'), 'AdminQuestionBankPage');
const AdminMlResearchPage = lazyPage(() => import('@/pages/admin/AdminMlResearchPage'), 'AdminMlResearchPage');
const AdminAiQuestionsPage = lazyPage(() => import('@/pages/admin/AdminAiQuestionsPage'), 'AdminAiQuestionsPage');
const AdminKnowledgeLibraryPage = lazyPage(() => import('@/pages/admin/AdminKnowledgeLibraryPage'), 'AdminKnowledgeLibraryPage');
const AdminFeedbackPage = lazyPage(() => import('@/pages/admin/AdminFeedbackPage'), 'AdminFeedbackPage');
const BadgesPage = lazyPage(() => import('@/pages/BadgesPage'), 'BadgesPage');
const LeaderboardPage = lazyPage(() => import('@/pages/LeaderboardPage'), 'LeaderboardPage');
const StudyNotesPage = lazyPage(() => import('@/pages/StudyNotesPage'), 'StudyNotesPage');
const ProfilePage = lazyPage(() => import('@/pages/ProfilePage'), 'ProfilePage');

function App() {
  return (
    <Routes>
      <Route element={<MainLayout />}>
        <Route index element={<LandingPage />} />
        <Route path="login" element={<LoginPage />} />
        <Route path="register" element={<RegisterPage />} />
        <Route path="forgot-password" element={<ForgotPasswordPage />} />
        <Route path="admin/login" element={<AdminLoginPage />} />
        <Route path="*" element={<NotFoundPage />} />
        <Route path="auth/callback" element={<AuthCallbackPage />} />

        <Route element={<RequireAuth />}>
          <Route path="placement" element={<PlacementPage />} />

          <Route element={<RequirePlacement />}>
            <Route path="dashboard" element={<DashboardPage />} />
            <Route path="study-plan" element={<StudyPlanPage />} />
            <Route path="test/daily" element={<DailyTestPage />} />
            <Route path="test/practice" element={<PracticeTestPage />} />
            <Route path="test/mock" element={<MockExamSetupPage />} />
            <Route path="session/:id/report" element={<SessionReportPage />} />
            <Route path="games" element={<GamesHubPage />} />
            <Route path="games/memory-match" element={<MemoryMatchPage />} />
            <Route path="games/sequence-puzzle" element={<SequencePuzzlePage />} />
            <Route path="games/math-rush" element={<MathRushPage />} />
            <Route path="games/mental-rotation" element={<MentalRotationPage />} />
            <Route path="games/selective-attention" element={<SelectiveAttentionPage />} />
            <Route path="games/working-memory-span" element={<WorkingMemorySpanPage />} />
            <Route path="games/visual-spatial-memory" element={<VisualSpatialMemoryPage />} />
            <Route path="games/cognitive-command-center" element={<CognitiveCommandCenterPage />} />
            <Route path="badges" element={<BadgesPage />} />
            <Route path="leaderboard" element={<LeaderboardPage />} />
            <Route path="study-notes" element={<StudyNotesPage />} />
            <Route path="profile" element={<ProfilePage />} />
          </Route>
        </Route>
      </Route>

      {/* Admin gets its own persistent-sidebar shell (AdminLayout), separate
          from the public/student top-nav MainLayout - see AdminLayout.tsx. */}
      <Route element={<RequireAuth />}>
        <Route element={<RequireRole roles={['admin', 'super_admin']} />}>
          <Route element={<AdminLayout />}>
            <Route path="admin/dashboard" element={<AdminDashboardPage />} />
            <Route path="admin/questions" element={<AdminQuestionsListPage />} />
            <Route path="admin/questions/new" element={<AdminQuestionNewPage />} />
            <Route path="admin/questions/visual-generator" element={<AdminVisualGeneratorPage />} />
            <Route path="admin/questions/:id/edit" element={<AdminQuestionEditPage />} />
            <Route path="admin/categories" element={<AdminCategoriesPage />} />
            <Route path="admin/users" element={<AdminUsersPage />} />
            <Route path="admin/psychometrics" element={<AdminPsychometricsPage />} />
            <Route path="admin/question-bank" element={<AdminQuestionBankPage />} />
            <Route path="admin/ml-research" element={<AdminMlResearchPage />} />
            <Route path="admin/ai-questions" element={<AdminAiQuestionsPage />} />
            <Route path="admin/knowledge-library" element={<AdminKnowledgeLibraryPage />} />
            <Route path="admin/feedback" element={<AdminFeedbackPage />} />
          </Route>
        </Route>
      </Route>
    </Routes>
  );
}

export default App;
