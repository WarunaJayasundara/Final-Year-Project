import type { TFunction } from 'i18next';

interface ApiErrorBody {
  message?: string;
  errors?: Record<string, string[]>;
}

/** Server messages that are shown to people, mapped to translated `common:errors.api.*` keys. */
const KNOWN_MESSAGES: Record<string, string> = {
  'Invalid credentials.': 'invalidCredentials',
  'This account signs in with Google. Use "Continue with Google" instead.': 'googleAccount',
  'Complete the placement test before practicing.': 'placementRequired',
  'Complete the placement test before taking a mock exam.': 'placementRequired',
  'Complete the placement test before starting a daily session.': 'placementRequired',
  'Placement test already completed.': 'placementDone',
  'Not enough questions seeded yet to run a placement test.': 'noQuestions',
  'No questions available for this category/level yet.': 'noQuestions',
  'No questions available at your level yet.': 'noQuestions',
  'Not enough questions available yet for a mock exam with these settings.': 'noQuestions',
  'No categories available for a mock exam.': 'noQuestions',
};

/** Laravel validation messages, matched by pattern because the wording is generated per field. */
function validationKey(errors: Record<string, string[]>): string {
  const flat = Object.entries(errors).flatMap(([field, messages]) => messages.map((m) => ({ field, m })));

  for (const { field, m } of flat) {
    if (/already been taken/i.test(m)) return field === 'username' ? 'usernameTaken' : 'emailTaken';
    if (/at least 8 characters/i.test(m)) return 'passwordShort';
    if (/confirmation does not match/i.test(m)) return 'passwordMismatch';
    if (/letters, numbers, and underscores/i.test(m)) return 'usernameFormat';
    if (/at least 10 years old/i.test(m)) return 'tooYoung';
    if (/valid date of birth/i.test(m)) return 'invalidBirthDate';
    if (/valid email/i.test(m)) return 'invalidEmail';
  }

  return 'validationFailed';
}

/**
 * Turns a failed API call into a message in the user's language. The backend's own text is English,
 * so it is never displayed directly: known messages and validation rules map to translated keys and
 * everything else falls back to a message chosen from the HTTP status.
 */
export function apiErrorMessage(error: unknown, t: TFunction): string {
  const response = (error as { response?: { status?: number; data?: ApiErrorBody } } | null)?.response;
  const tr = (key: string) => t(`errors.api.${key}`, { ns: 'common' });

  if (!response) return tr('offline');

  const { status, data } = response;
  const known = data?.message ? KNOWN_MESSAGES[data.message] : undefined;
  if (known) return tr(known);

  if (status === 422 && data?.errors) return tr(validationKey(data.errors));
  if (status === 401) return tr('sessionExpired');
  if (status === 403) return tr('forbidden');
  if (status === 419) return tr('sessionExpired');
  if (status === 429) return tr('tooMany');
  if (status && status >= 500) return tr('server');

  return t('errors.generic', { ns: 'common' });
}
