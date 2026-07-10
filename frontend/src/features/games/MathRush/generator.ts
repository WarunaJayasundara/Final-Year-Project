export interface MathQuestion {
  prompt: string;
  answer: number;
  options: number[];
}

function shuffle<T>(items: T[]): T[] {
  const arr = [...items];
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [arr[i], arr[j]] = [arr[j], arr[i]];
  }
  return arr;
}

export function generateQuestion(): MathQuestion {
  const ops = ['+', '-', '×', '÷'] as const;
  const op = ops[Math.floor(Math.random() * ops.length)];

  let a: number;
  let b: number;
  let answer: number;

  switch (op) {
    case '+':
      a = Math.floor(Math.random() * 50) + 1;
      b = Math.floor(Math.random() * 50) + 1;
      answer = a + b;
      break;
    case '-':
      a = Math.floor(Math.random() * 50) + 10;
      b = Math.floor(Math.random() * a);
      answer = a - b;
      break;
    case '×':
      a = Math.floor(Math.random() * 12) + 1;
      b = Math.floor(Math.random() * 12) + 1;
      answer = a * b;
      break;
    case '÷':
    default:
      b = Math.floor(Math.random() * 11) + 2;
      answer = Math.floor(Math.random() * 11) + 1;
      a = b * answer;
      break;
  }

  const distractors = new Set<number>();
  while (distractors.size < 3) {
    const delta = Math.floor(Math.random() * 8) + 1;
    const candidate = Math.random() > 0.5 ? answer + delta : answer - delta;
    if (candidate !== answer && candidate >= 0) {
      distractors.add(candidate);
    }
  }

  return {
    prompt: `${a} ${op} ${b}`,
    answer,
    options: shuffle([answer, ...distractors]),
  };
}
