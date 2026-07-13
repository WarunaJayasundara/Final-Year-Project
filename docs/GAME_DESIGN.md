# Game Design

8 cognitive-training mini-games, each targeting a specific cognitive
skill, reusing a shared `GameController`/`games`/`game_scores` backend
(no per-game database tables — `game_scores.metadata` is schemaless JSON,
so each game can record its own detail without a schema migration).

## 1. The 8 games

| Game | Code | Skill targeted | Adaptivity |
|---|---|---|---|
| Memory Match | `memory_match` | Visual short-term memory | Static 8-pair — kept as an easy-tier warm-up, deliberately not adaptive |
| Sequence Puzzle | `sequence_puzzle` | Pattern/sequence reasoning | — |
| Math Rush | `math_rush` | Numerical fluency under time pressure | — |
| Mental Rotation Challenge | `mental_rotation` | Spatial reasoning | — |
| Selective Attention | `selective_attention` | Focus / interference control | — |
| Working Memory Span | `working_memory_span` | Working memory | Real staircase adaptive difficulty |
| Visual-Spatial Memory | `visual_spatial_memory` | Spatial memory / recall | Real staircase adaptive difficulty |
| Cognitive Command Center | `cognitive_command_center` | Multi-domain / task-switching | Rule-switching + real-time metric |

## 2. Memory testing redesign

The original memory testing was a single static Memory Match game with no
adaptation — flagged during a supervisor review as reading too
primary-school-level. Two new games were added rather than making Memory
Match itself adaptive, since Memory Match's simple pairs-matching format
doesn't naturally support a difficulty staircase:

- **Working Memory Span** — forward/backward digit span, a 2-back updating
  task, and one interference round (distractor arithmetic inserted between
  encoding and recall, specifically to test whether working memory holds
  up under competing cognitive load).
- **Visual-Spatial Memory** — scene recall (count/position/missing-icon
  questions) and Corsi-block-style spatial path recall.

Both use a real staircase: span/item-count grows after 2 consecutive
correct answers, shrinks after 1 wrong answer — genuine adaptive
difficulty, not a fixed progression.

## 3. Cognitive Command Center — the newest, most complex game

Rapid-fire rounds cycling through 5 distinct cognitive demands in
sequence: pattern analysis, 2-rounds-ago recall (an n-back variant),
rule-switch sorting (the sorting rule itself changes mid-game — largest →
smallest-odd → largest-even — genuinely testing cognitive flexibility, not
just the individual skill), go/no-go inhibitory control, and a dual-task
round (hold a number in mind while solving an unrelated arithmetic
problem). It computes a real, non-trivial derived metric: **cognitive
switching cost** — the reaction-time delta immediately after a sort-rule
change versus steady-state performance on the same rule, a genuine
research-relevant measure of executive-function cost, not a cosmetic
number.

## 4. Shared UI infrastructure

Every game routes through a shared `GameStartScreen` component (added
during the HelaIQ redesign — none of the 8 games had *any* pre-game screen
before, dropping the student straight into gameplay) and a shared
`GameResultCard`. A shared `gameStyles.ts`
(`GAME_ICONS`/`GAME_ROUTES`/`gameAccent()`) cycles each game through the
app's own 5-color chart palette rather than 8 separately hand-picked
colors, so the games hub reads as one coherent visual system. Internal
game engines/state machines (the actual gameplay logic, including the
adaptive staircases) were deliberately left untouched during this UI
wrapper work — lower risk than touching genuinely complex logic for a
purely presentational change.

## 5. Layout

The games hub (`GamesHubPage`) uses the shared `BalancedGrid` primitive
(4+4 for 8 games) rather than a fixed `grid-cols-3`, which previously left
an unbalanced 3+3+2 split.
