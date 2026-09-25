import { BookOpenCheck, Gamepad2, ListChecks, Moon, ShieldCheck, Target } from 'lucide-react';

export const DAY_ORDER = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

export const ACTIVITY_LINK: Record<string, string> = {
  weak_category_practice: '/test/practice',
  timed_mock_practice: '/test/mock',
  cognitive_game_warmup: '/games',
  strong_category_maintenance: '/test/practice',
  confidence_review: '/test/practice',
};

export const ACTIVITY_ICON: Record<string, typeof Target> = {
  weak_category_practice: Target,
  timed_mock_practice: ListChecks,
  cognitive_game_warmup: Gamepad2,
  strong_category_maintenance: ShieldCheck,
  confidence_review: BookOpenCheck,
  rest: Moon,
};
