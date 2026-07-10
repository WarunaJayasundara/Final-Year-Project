import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';

import enCommon from '@/locales/en/common.json';
import enAuth from '@/locales/en/auth.json';
import enDashboard from '@/locales/en/dashboard.json';
import enSessions from '@/locales/en/sessions.json';
import enGames from '@/locales/en/games.json';
import enAdmin from '@/locales/en/admin.json';
import enCoach from '@/locales/en/coach.json';
import enStudyPlan from '@/locales/en/studyPlan.json';
import enGamification from '@/locales/en/gamification.json';
import siCommon from '@/locales/si/common.json';
import siAuth from '@/locales/si/auth.json';
import siDashboard from '@/locales/si/dashboard.json';
import siSessions from '@/locales/si/sessions.json';
import siGames from '@/locales/si/games.json';
import siAdmin from '@/locales/si/admin.json';
import siCoach from '@/locales/si/coach.json';
import siStudyPlan from '@/locales/si/studyPlan.json';
import siGamification from '@/locales/si/gamification.json';

i18n
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    resources: {
      en: {
        common: enCommon,
        auth: enAuth,
        dashboard: enDashboard,
        sessions: enSessions,
        games: enGames,
        admin: enAdmin,
        coach: enCoach,
        studyPlan: enStudyPlan,
        gamification: enGamification,
      },
      si: {
        common: siCommon,
        auth: siAuth,
        dashboard: siDashboard,
        sessions: siSessions,
        games: siGames,
        admin: siAdmin,
        coach: siCoach,
        studyPlan: siStudyPlan,
        gamification: siGamification,
      },
    },
    fallbackLng: 'en',
    supportedLngs: ['en', 'si'],
    load: 'languageOnly',
    ns: ['common', 'auth', 'dashboard', 'sessions', 'games', 'admin', 'coach', 'studyPlan', 'gamification'],
    defaultNS: 'common',
    interpolation: { escapeValue: false },
    detection: {
      order: ['localStorage', 'navigator'],
      caches: ['localStorage'],
      lookupLocalStorage: 'mindrise_locale',
    },
  });

export default i18n;
