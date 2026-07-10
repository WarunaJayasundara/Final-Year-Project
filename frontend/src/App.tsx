import { Route, Routes } from 'react-router-dom';
import { MainLayout } from '@/components/layout/MainLayout';
import { RequireAuth } from '@/components/auth/RequireAuth';
import { RequireRole } from '@/components/auth/RequireRole';
import { RequirePlacement } from '@/components/auth/RequirePlacement';
import { LandingPage } from '@/pages/LandingPage';
import { LoginPage } from '@/pages/LoginPage';
import { AdminLoginPage } from '@/pages/AdminLoginPage';
import { AuthCallbackPage } from '@/pages/AuthCallbackPage';
import { PlacementPage } from '@/pages/PlacementPage';
import { DashboardPage } from '@/pages/DashboardPage';
import { StudyPlanPage } from '@/pages/StudyPlanPage';
import { DailyTestPage } from '@/pages/DailyTestPage';
import { PracticeTestPage } from '@/pages/PracticeTestPage';
import { SessionReportPage } from '@/pages/SessionReportPage';
import { GamesHubPage } from '@/pages/GamesHubPage';
import { MemoryMatchPage } from '@/pages/games/MemoryMatchPage';
import { SequencePuzzlePage } from '@/pages/games/SequencePuzzlePage';
import { MathRushPage } from '@/pages/games/MathRushPage';
import { MentalRotationPage } from '@/pages/games/MentalRotationPage';
import { SelectiveAttentionPage } from '@/pages/games/SelectiveAttentionPage';
import { AdminDashboardPage } from '@/pages/admin/AdminDashboardPage';
import { AdminQuestionsListPage } from '@/pages/admin/AdminQuestionsListPage';
import { AdminQuestionNewPage } from '@/pages/admin/AdminQuestionNewPage';
import { AdminQuestionEditPage } from '@/pages/admin/AdminQuestionEditPage';
import { AdminCategoriesPage } from '@/pages/admin/AdminCategoriesPage';
import { AdminUsersPage } from '@/pages/admin/AdminUsersPage';
import { AdminPsychometricsPage } from '@/pages/admin/AdminPsychometricsPage';
import { AdminQuestionBankPage } from '@/pages/admin/AdminQuestionBankPage';
import { AdminAiQuestionsPage } from '@/pages/admin/AdminAiQuestionsPage';
import { BadgesPage } from '@/pages/BadgesPage';
import { LeaderboardPage } from '@/pages/LeaderboardPage';

function App() {
  return (
    <Routes>
      <Route element={<MainLayout />}>
        <Route index element={<LandingPage />} />
        <Route path="login" element={<LoginPage />} />
        <Route path="admin/login" element={<AdminLoginPage />} />
        <Route path="auth/callback" element={<AuthCallbackPage />} />

        <Route element={<RequireAuth />}>
          <Route path="placement" element={<PlacementPage />} />

          <Route element={<RequirePlacement />}>
            <Route path="dashboard" element={<DashboardPage />} />
            <Route path="study-plan" element={<StudyPlanPage />} />
            <Route path="test/daily" element={<DailyTestPage />} />
            <Route path="test/practice" element={<PracticeTestPage />} />
            <Route path="session/:id/report" element={<SessionReportPage />} />
            <Route path="games" element={<GamesHubPage />} />
            <Route path="games/memory-match" element={<MemoryMatchPage />} />
            <Route path="games/sequence-puzzle" element={<SequencePuzzlePage />} />
            <Route path="games/math-rush" element={<MathRushPage />} />
            <Route path="games/mental-rotation" element={<MentalRotationPage />} />
            <Route path="games/selective-attention" element={<SelectiveAttentionPage />} />
            <Route path="badges" element={<BadgesPage />} />
            <Route path="leaderboard" element={<LeaderboardPage />} />
          </Route>

          <Route element={<RequireRole roles={['admin', 'super_admin']} />}>
            <Route path="admin/dashboard" element={<AdminDashboardPage />} />
            <Route path="admin/questions" element={<AdminQuestionsListPage />} />
            <Route path="admin/questions/new" element={<AdminQuestionNewPage />} />
            <Route path="admin/questions/:id/edit" element={<AdminQuestionEditPage />} />
            <Route path="admin/categories" element={<AdminCategoriesPage />} />
            <Route path="admin/users" element={<AdminUsersPage />} />
            <Route path="admin/psychometrics" element={<AdminPsychometricsPage />} />
            <Route path="admin/question-bank" element={<AdminQuestionBankPage />} />
            <Route path="admin/ai-questions" element={<AdminAiQuestionsPage />} />
          </Route>
        </Route>
      </Route>
    </Routes>
  );
}

export default App;
