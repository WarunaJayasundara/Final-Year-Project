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
import { AdminLoginPage } from '@/pages/AdminLoginPage';
import { AuthCallbackPage } from '@/pages/AuthCallbackPage';
import { PlacementPage } from '@/pages/PlacementPage';
import { DashboardPage } from '@/pages/DashboardPage';
import { StudyPlanPage } from '@/pages/StudyPlanPage';
import { DailyTestPage } from '@/pages/DailyTestPage';
import { PracticeTestPage } from '@/pages/PracticeTestPage';
import { MockExamSetupPage } from '@/pages/MockExamSetupPage';
import { SessionReportPage } from '@/pages/SessionReportPage';
import { GamesHubPage } from '@/pages/GamesHubPage';
import { MemoryMatchPage } from '@/pages/games/MemoryMatchPage';
import { SequencePuzzlePage } from '@/pages/games/SequencePuzzlePage';
import { MathRushPage } from '@/pages/games/MathRushPage';
import { MentalRotationPage } from '@/pages/games/MentalRotationPage';
import { SelectiveAttentionPage } from '@/pages/games/SelectiveAttentionPage';
import { WorkingMemorySpanPage } from '@/pages/games/WorkingMemorySpanPage';
import { VisualSpatialMemoryPage } from '@/pages/games/VisualSpatialMemoryPage';
import { CognitiveCommandCenterPage } from '@/pages/games/CognitiveCommandCenterPage';
import { AdminDashboardPage } from '@/pages/admin/AdminDashboardPage';
import { AdminQuestionsListPage } from '@/pages/admin/AdminQuestionsListPage';
import { AdminQuestionNewPage } from '@/pages/admin/AdminQuestionNewPage';
import { AdminQuestionEditPage } from '@/pages/admin/AdminQuestionEditPage';
import { AdminVisualGeneratorPage } from '@/pages/admin/AdminVisualGeneratorPage';
import { AdminCategoriesPage } from '@/pages/admin/AdminCategoriesPage';
import { AdminUsersPage } from '@/pages/admin/AdminUsersPage';
import { AdminPsychometricsPage } from '@/pages/admin/AdminPsychometricsPage';
import { AdminQuestionBankPage } from '@/pages/admin/AdminQuestionBankPage';
import { AdminMlResearchPage } from '@/pages/admin/AdminMlResearchPage';
import { AdminAiQuestionsPage } from '@/pages/admin/AdminAiQuestionsPage';
import { AdminKnowledgeLibraryPage } from '@/pages/admin/AdminKnowledgeLibraryPage';
import { AdminFeedbackPage } from '@/pages/admin/AdminFeedbackPage';
import { BadgesPage } from '@/pages/BadgesPage';
import { LeaderboardPage } from '@/pages/LeaderboardPage';
import { StudyNotesPage } from '@/pages/StudyNotesPage';
import { ProfilePage } from '@/pages/ProfilePage';

function App() {
  return (
    <Routes>
      <Route element={<MainLayout />}>
        <Route index element={<LandingPage />} />
        <Route path="login" element={<LoginPage />} />
        <Route path="register" element={<RegisterPage />} />
        <Route path="forgot-password" element={<ForgotPasswordPage />} />
        <Route path="admin/login" element={<AdminLoginPage />} />
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
