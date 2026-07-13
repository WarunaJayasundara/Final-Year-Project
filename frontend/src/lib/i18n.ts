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
import enProfile from '@/locales/en/profile.json';
import siCommon from '@/locales/si/common.json';
import siAuth from '@/locales/si/auth.json';
import siDashboard from '@/locales/si/dashboard.json';
import siSessions from '@/locales/si/sessions.json';
import siGames from '@/locales/si/games.json';
import siAdmin from '@/locales/si/admin.json';
import siCoach from '@/locales/si/coach.json';
import siStudyPlan from '@/locales/si/studyPlan.json';
import siGamification from '@/locales/si/gamification.json';
import siProfile from '@/locales/si/profile.json';

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
        profile: enProfile,
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
        profile: siProfile,
      },
    },
    fallbackLng: 'en',
    supportedLngs: ['en', 'si'],
    load: 'languageOnly',
    ns: ['common', 'auth', 'dashboard', 'sessions', 'games', 'admin', 'coach', 'studyPlan', 'gamification', 'profile'],
    defaultNS: 'common',
    interpolation: { escapeValue: false },
    detection: {
      order: ['localStorage', 'navigator'],
      caches: ['localStorage'],
      lookupLocalStorage: 'mindrise_locale',
    },
  });

// Keeps <html lang> in sync with the active i18next language - the
// :lang(si) CSS rule that switches in the Sinhala font depends on this
// attribute, which index.html only ever sets once (to "en") and nothing
// else was updating it.
const syncHtmlLang = (lng: string) => {
  document.documentElement.lang = lng.startsWith('si') ? 'si' : 'en';
};
syncHtmlLang(i18n.language);
i18n.on('languageChanged', syncHtmlLang);

export default i18n;
