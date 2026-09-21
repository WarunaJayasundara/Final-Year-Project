// Property tests for every game generator: answers are correct, options are distinct, exactly one option is right.
// Runs about a million generated rounds in a few seconds. Usage: npm run check:games
// (Node 22.6+ strips the TypeScript types itself, so there is no build step.)
const base = new URL('../src/features/games/', import.meta.url).href;
const math = await import(base + 'MathRush/generator.ts');
const seq = await import(base + 'SequencePuzzle/generator.ts');
const att = await import(base + 'SelectiveAttention/generator.ts');
const rot = await import(base + 'MentalRotation/generator.ts');
const wm = await import(base + 'WorkingMemorySpan/generator.ts');
const vs = await import(base + 'VisualSpatialMemory/generator.ts');
const cc = await import(base + 'CognitiveCommandCenter/generator.ts');

const N = 20000;
const problems: string[] = [];
const check = (cond: boolean, msg: string) => {
  if (!cond && problems.length < 40) problems.push(msg);
};
const uniq = (a: unknown[]) => new Set(a).size === a.length;

// ---- Math Rush: the answer is actually right, and options are distinct and non-negative
for (let i = 0; i < N; i++) {
  const q = math.generateQuestion();
  const [a, op, b] = q.prompt.split(' ');
  const x = Number(a), y = Number(b);
  const real = op === '+' ? x + y : op === '-' ? x - y : op === '×' ? x * y : x / y;
  check(real === q.answer, `math wrong answer ${q.prompt} = ${q.answer}`);
  check(Number.isInteger(q.answer), `math non-integer ${q.prompt}`);
  check(q.options.length === 4 && uniq(q.options), `math options ${q.prompt} ${q.options}`);
  check(q.options.includes(q.answer), `math missing answer ${q.prompt}`);
  check(q.options.every((o: number) => o >= 0), `math negative option ${q.prompt}`);
}

// ---- Sequence puzzle: rule holds for the answer, options distinct, exactly one correct
for (let r = 1; r <= 12; r++) {
  for (let i = 0; i < 2000; i++) {
    const g = seq.generateRound(r);
    const s = g.sequence;
    const d = s[1] - s[0];
    const arithmetic = s.every((v: number, k: number) => k === 0 || v - s[k - 1] === d);
    const ratio = s[1] / s[0];
    const geometric = s.every((v: number, k: number) => k === 0 || v === s[k - 1] * ratio);
    const next = arithmetic ? s[3] + d : geometric ? s[3] * ratio : NaN;
    check(next === g.answer, `sequence answer ${s} -> ${g.answer}`);
    check(g.options.length === 4 && uniq(g.options) && g.options.includes(g.answer), `sequence options ${g.options}`);
    check(g.options.every((o: number) => o > 0), `sequence non-positive option ${g.options}`);
  }
}

// ---- Selective attention: target inside the grid, differs from base by a real rotation
for (let r = 1; r <= 10; r++) {
  for (let i = 0; i < 3000; i++) {
    const g = att.generateRound(r);
    check(g.targetIndex >= 0 && g.targetIndex < g.gridSize ** 2, `attention target out of grid`);
    check(g.targetRotationDeg !== g.baseRotationDeg, `attention target equals base`);
  }
}

// ---- Mental rotation: exactly one option is a rotation of the target shape, three are mirrors
for (let i = 0; i < N; i++) {
  const g = rot.generateRound();
  const key = (cells: number[][]) => cells.map((c) => c.join(',')).sort().join(';');
  const rotations = (cells: number[][]) => {
    const out: string[] = [];
    let cur = cells;
    for (let t = 0; t < 4; t++) {
      out.push(key(cur));
      cur = cur.map(([r, c]) => [c, rot.GRID_SIZE - 1 - r]);
    }
    return out;
  };
  const targetRots = rotations(g.target);
  const correct = g.options.filter((o: { isCorrect: boolean }) => o.isCorrect);
  check(g.options.length === 4 && correct.length === 1, `rotation option count`);
  const trueRotations = g.options.filter((o: { cells: number[][] }) => targetRots.includes(key(o.cells)));
  check(trueRotations.length === 1 && trueRotations[0].isCorrect, `rotation ambiguity: ${trueRotations.length} options are true rotations`);
  check(uniq(g.options.map((o: { cells: number[][] }) => key(o.cells))), `rotation duplicate options`);
  check(uniq(g.options.map((o: { id: number }) => o.id)), `rotation ids`);
}

// ---- Working memory span
for (const type of ['forward', 'backward', 'nback', 'interference'] as const) {
  for (let span = wm.MIN_SPAN; span <= wm.MAX_SPAN; span++) {
    for (let seed = 1; seed <= 400; seed++) {
      const t = wm.generateTrial(type, span, seed * 7919 + span);
      check(t.sequence.every((d: number) => d >= 0 && d <= 9), `wm digit range`);
      if (type === 'nback') {
        check(t.sequence.length === span + 4, `nback length`);
        const ok = t.sequence.every((v: number, i: number) => t.nbackTargets[i] === (i >= 2 && v === t.sequence[i - 2]));
        check(ok, `nback targets misaligned`);
      } else {
        check(t.sequence.length === span, `wm length ${type} ${span}`);
      }
      if (type === 'interference') {
        const [a, , b] = t.distractor.question.split(' ');
        const correct = Number(a) + Number(b);
        check(t.distractor.options[t.distractor.answerIndex] === correct, `interference answer index`);
        check(uniq(t.distractor.options), `interference options`);
      }
    }
  }
}
// adaptive staircase stays in bounds and reacts in the right direction
for (let span = wm.MIN_SPAN; span <= wm.MAX_SPAN; span++) {
  check(wm.nextSpan(span, false, 0) >= wm.MIN_SPAN, 'span floor');
  check(wm.nextSpan(span, true, 5) <= wm.MAX_SPAN, 'span ceiling');
  check(wm.nextSpan(span, true, 0) === span, 'span holds after one correct');
}

// ---- Visual spatial memory
for (let items = vs.MIN_ITEMS; items <= vs.MAX_ITEMS; items++) {
  for (let seed = 1; seed <= 1500; seed++) {
    const s = vs.generateSceneRound(items, seed * 104729 + items);
    check(s.cells.length === items && uniq(s.cells) && s.cells.every((c: number) => c >= 0 && c < 16), `scene cells`);
    check(uniq(s.icons) && s.icons.length === items, `scene icons`);
    if (s.question === 'positionIcon') check(s.icons[s.cells.indexOf(s.targetCell)] === s.answer, `scene positionIcon answer`);
    if (s.question === 'missingIcon') {
      check(s.optionIcons.length === 4 && uniq(s.optionIcons), `scene missing options`);
      check(s.optionIcons.includes(s.answer) && !s.icons.includes(s.answer), `scene missing answer`);
      check(s.optionIcons.filter((o: string) => !s.icons.includes(o)).length === 1, `scene missing ambiguity`);
    }
    if (s.question === 'count') check(s.answer === items, `scene count`);
  }
}
for (let span = vs.MIN_PATH_SPAN; span <= vs.MAX_PATH_SPAN; span++) {
  for (let seed = 1; seed <= 1000; seed++) {
    const p = vs.generatePathRound(span, seed * 31 + span);
    check(p.sequence.length === span, 'path length');
    check(p.sequence.every((c: number, i: number) => c >= 0 && c < 16 && (i === 0 || c !== p.sequence[i - 1])), 'path repeat/range');
  }
}

// ---- Cognitive command center
for (let seed = 1; seed <= 4000; seed++) {
  for (const difficulty of [1, 2, 3, 4, 5, 6]) {
    const p = cc.generatePatternRound(seed, difficulty);
    const d = p.sequence[1] - p.sequence[0];
    check(p.sequence.every((v: number, k: number) => k === 0 || v - p.sequence[k - 1] === d), 'cc pattern not arithmetic');
    check(p.answer === p.sequence[3] + d, 'cc pattern answer');
    check(p.options.length === 4 && uniq(p.options) && p.options.includes(p.answer), `cc pattern options ${p.options}`);
    for (const rule of cc.SORT_RULE_SCHEDULE) {
      const s = cc.generateSortRound(seed, rule, difficulty);
      const evens = s.numbers.filter((n: number) => n % 2 === 0);
      const odds = s.numbers.filter((n: number) => n % 2 !== 0);
      const expect = rule === 'largest' ? Math.max(...s.numbers) : rule === 'largestEven' ? (evens.length ? Math.max(...evens) : Math.max(...s.numbers)) : odds.length ? Math.min(...odds) : Math.min(...s.numbers);
      check(s.answer === expect && s.numbers.includes(s.answer), `cc sort ${rule}`);
    }
  }
  const dual = cc.generateDualRound(seed);
  const [a, , b] = dual.mathQuestion.split(' ');
  check(dual.mathOptions[dual.mathAnswerIndex] === Number(a) + Number(b), 'cc dual math');
  check(uniq(dual.mathOptions), 'cc dual math options');
  check(dual.recallOptions.includes(dual.holdValue) && uniq(dual.recallOptions), `cc dual recall ${dual.recallOptions} hold ${dual.holdValue}`);
  const rc = cc.generateRecallRound(6 + (seed % 40), seed);
  check(rc.options.includes(rc.targetValue) && uniq(rc.options), 'cc recall options');
  const inh = cc.generateInhibitionRound(seed);
  check(typeof inh.isGo === 'boolean', 'cc inhibition');
}
check(cc.TASK_ROTATION.length === 12, 'cc rotation length');
// every recall round asks about a number shown exactly two rounds earlier, by a pattern or dual round
cc.TASK_ROTATION.forEach((type: string, i: number) => {
  if (type === 'recall') {
    check(i >= 2 && ['pattern', 'dual'].includes(cc.TASK_ROTATION[i - 2]), `cc recall at round ${i} has no number-bearing round two earlier`);
  }
});
check(new Set(cc.TASK_ROTATION).size === 5, 'cc rotation must cover all five task types');
// the number a pattern round asks the player to remember is one they actually saw
for (let seed = 1; seed <= 500; seed++) {
  const p = cc.generatePatternRound(seed, 3);
  check(p.displayNumber === p.sequence[p.sequence.length - 1], 'cc pattern displayNumber must be the last visible number');
}

console.log(problems.length ? 'PROBLEMS:\n' + problems.join('\n') : 'ALL GAME GENERATOR INVARIANTS HOLD');
