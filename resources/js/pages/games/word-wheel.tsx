import { Head, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    BookOpen,
    CircleCheck,
    Coins,
    Disc3,
    Eye,
    Lightbulb,
    RotateCw,
    Sparkles,
    Trophy,
    Volume2,
    VolumeX,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { SpinWheel } from '@/components/games/spin-wheel';
import type { StudioPhase } from '@/components/games/studio-lights';
import { StudioLights } from '@/components/games/studio-lights';
import { useWheelSound } from '@/hooks/use-wheel-sound';
import { exit } from '@/routes/child-mode';
import { finish, guess, solve, spin, start } from '@/routes/word-wheel/play';
import './word-wheel.css';

type Segment = {
    kind: 'points' | 'bankrupt' | 'lose-turn';
    points: number | null;
};

type Attempt = {
    id: number;
    revision: number;
    phase: 'spin' | 'guess' | 'solved' | 'finished';
    score: number;
    board: (string | null)[][];
    clue: string;
    guessedLetters: string[];
    spinIndex: number | null;
    spins: number;
    feedback: {
        kind:
            | 'spin'
            | 'bankrupt'
            | 'lose-turn'
            | 'hit'
            | 'miss'
            | 'incorrect'
            | 'solved'
            | 'finished';
        matches?: number;
        points?: number;
    } | null;
    solution: string | null;
};

type Props = {
    teamSlug: string;
    childName: string;
    available: boolean;
    poolAvailable: boolean;
    segments: Segment[];
    attempt: Attempt | null;
    progress: { solved: number; bestScore: number };
};

function studioPhase(attempt: Attempt | null, spinning: boolean): StudioPhase {
    if (spinning) {
        return 'lock';
    }

    if (!attempt) {
        return 'welcome';
    }

    switch (attempt.feedback?.kind) {
        case 'spin':
            return 'checkpoint';
        case 'hit':
            return 'correct';
        case 'bankrupt':
        case 'miss':
        case 'incorrect':
            return 'incorrect';
        case 'lose-turn':
        case 'finished':
            return 'walked';
        case 'solved':
            return 'won';
        default:
            return 'question';
    }
}

const alphabet = Array.from('אבגדהוזחטיכלמנסעפצקרשת');
const baseLetters = { ך: 'כ', ם: 'מ', ן: 'נ', ף: 'פ', ץ: 'צ' };
const numbers = new Intl.NumberFormat('he-IL');
const previewBoard = [
    [null, 'ג', null, null],
    [null, null, 'ל', null, null],
];

function baseLetter(letter: string) {
    return baseLetters[letter as keyof typeof baseLetters] ?? letter;
}

function segmentLabel(segment: Segment) {
    switch (segment.kind) {
        case 'points':
            return numbers.format(segment.points ?? 0);
        case 'bankrupt':
            return 'איפוס';
        case 'lose-turn':
            return 'דילוג';
    }
}

function wheelStatus(attempt: Attempt | null, segment: Segment | undefined) {
    const feedback = attempt?.feedback;

    switch (feedback?.kind) {
        case 'spin':
            if (segment?.kind === 'points') {
                return (
                    'כל אות שתמצאו שווה ' + segmentLabel(segment) + ' נקודות.'
                );
            }

            break;
        case 'bankrupt':
            return 'הנקודות התאפסו. אפשר לסובב שוב.';
        case 'lose-turn':
            return 'דילוג על התור. אפשר לסובב שוב.';
        case 'hit':
            return feedback.matches === 1
                ? 'מצאתם אות אחת! אפשר לסובב שוב.'
                : `מצאתם ${numbers.format(feedback.matches ?? 0)} אותיות! אפשר לסובב שוב.`;
        case 'miss':
            return 'האות לא מופיעה בביטוי. נסו שוב.';
        case 'incorrect':
            return 'זה עוד לא הפתרון. אפשר לנסות שוב.';
        case 'solved':
            return 'פתרתם את הביטוי!';
        case 'finished':
            return 'הביטוי נחשף.';
    }

    return attempt?.phase === 'guess'
        ? 'בחרו אות מהלוח.'
        : 'הגלגל מוכן לסיבוב.';
}

function PuzzleBoard({
    board,
    preview = false,
}: {
    board: Attempt['board'];
    preview?: boolean;
}) {
    return (
        <div className="word-wheel-board" data-test="wheel-board">
            {!preview && (
                <p className="sr-only">
                    {board
                        .map(
                            (word, index) =>
                                `מילה ${index + 1}: ${word.map((letter) => letter ?? 'חסר').join(' ')}`,
                        )
                        .join('. ')}
                </p>
            )}
            <div className="word-wheel-tiles" aria-hidden="true">
                {board.map((word, index) => (
                    <div className="word-wheel-word" key={index}>
                        {word.map((letter, offset) => (
                            <span
                                key={offset}
                                className="word-wheel-tile"
                                data-revealed={letter !== null || undefined}
                            >
                                {letter ?? <i />}
                            </span>
                        ))}
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function WordWheel({
    teamSlug,
    childName,
    available,
    poolAvailable,
    segments,
    attempt,
    progress,
}: Props) {
    const errors = usePage().props.errors;
    const [requestError, setRequestError] = useState('');
    const [processing, setProcessing] = useState(false);
    const [spinning, setSpinning] = useState(false);
    const [openPanel, setOpenPanel] = useState<'solve' | 'finish' | null>(null);
    const [solution, setSolution] = useState('');
    const { land, solved, soundEnabled, tick, toggleSound } = useWheelSound();
    const pending = useRef(false);
    const solutionInput = useRef<HTMLInputElement>(null);
    const cancelFinish = useRef<HTMLButtonElement>(null);
    const solveButton = useRef<HTMLButtonElement>(null);
    const finishButton = useRef<HTMLButtonElement>(null);
    const puzzleTitle = useRef<HTMLHeadingElement>(null);

    useEffect(() => {
        if (openPanel === 'solve') {
            solutionInput.current?.focus();
        } else if (openPanel === 'finish') {
            cancelFinish.current?.focus();
        }
    }, [openPanel]);

    const stage = attempt?.phase ?? 'welcome';
    const lighting = studioPhase(attempt, spinning);
    const isComplete = stage === 'solved' || stage === 'finished';
    const isPlaying = stage === 'spin' || stage === 'guess';
    const previousStage = useRef(stage);

    useEffect(() => {
        const previous = previousStage.current;
        previousStage.current = stage;

        if (
            previous !== stage &&
            (isComplete ||
                previous === 'welcome' ||
                previous === 'solved' ||
                previous === 'finished')
        ) {
            puzzleTitle.current?.focus();
        }
    }, [isComplete, stage]);

    const canStart = available && poolAvailable;
    const locked = processing || spinning;
    const currentSegment =
        attempt?.spinIndex == null ? undefined : segments[attempt.spinIndex];
    const outcome =
        attempt?.spinIndex == null
            ? null
            : {
                  id: `${attempt.id}:${attempt.spins}`,
                  segmentIndex: attempt.spinIndex,
              };
    const guessed = new Set(attempt?.guessedLetters.map(baseLetter));
    const tiles = attempt?.board.flat() ?? [];
    const visibleTiles = tiles.filter((letter) => letter !== null);
    const found = new Set(visibleTiles.map(baseLetter));
    const availabilityMessage = available
        ? 'עוד אין ביטויים מאושרים שמתאימים לפרופיל הזה.'
        : 'המשחק אינו זמין כרגע.';
    const error =
        requestError ||
        errors.game ||
        errors.pool ||
        errors.round ||
        errors.letter ||
        errors.solution;

    function submit(
        route: ReturnType<typeof start>,
        data: { revision?: number; letter?: string; solution?: string } = {},
        isSpin = false,
    ) {
        if (pending.current || spinning) {
            return;
        }

        pending.current = true;
        let wheelWillAnimate = false;
        setRequestError('');
        setProcessing(true);

        if (isSpin) {
            setSpinning(true);
        }

        router.post(route.url, data, {
            preserveScroll: true,
            onSuccess: (page) => {
                const next = page.props.attempt as Props['attempt'];

                wheelWillAnimate = Boolean(
                    isSpin &&
                    next &&
                    next.id === attempt?.id &&
                    next.spins > attempt.spins,
                );

                if (next?.phase === 'solved' && attempt?.phase !== 'solved') {
                    solved();
                }

                if (
                    next?.id !== attempt?.id ||
                    next?.phase === 'solved' ||
                    next?.phase === 'finished'
                ) {
                    setOpenPanel(null);
                    setSolution('');
                }
            },
            onHttpException: (response) => {
                if (response.status === 404) {
                    router.reload();

                    return false;
                }

                setRequestError('לא הצלחנו להשלים את הפעולה. אפשר לנסות שוב.');

                return false;
            },
            onNetworkError: () => {
                setRequestError('החיבור התנתק. אפשר לנסות שוב.');

                return false;
            },
            onFinish: () => {
                pending.current = false;
                setProcessing(false);

                if (!wheelWillAnimate) {
                    setSpinning(false);
                }
            },
        });
    }

    function playTurn(
        action: typeof spin | typeof guess | typeof solve | typeof finish,
        data: { letter?: string; solution?: string } = {},
    ) {
        if (attempt) {
            submit(
                action({ current_team: teamSlug, attempt: attempt.id }),
                { revision: attempt.revision, ...data },
                action === spin,
            );
        }
    }

    function hidePanel() {
        if (openPanel === 'solve') {
            solveButton.current?.focus();
        } else {
            finishButton.current?.focus();
        }

        setOpenPanel(null);
    }

    function submitSolution(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (solution.trim()) {
            playTurn(solve, { solution: solution.trim() });
        }
    }

    return (
        <main
            className="word-wheel"
            dir="rtl"
            lang="he"
            data-test="word-wheel"
            data-stage={stage}
            data-lighting={lighting}
            data-spins={attempt?.spins ?? 0}
            data-spinning={spinning || undefined}
        >
            <Head title="גלגל המילים" />
            <img
                className="word-wheel-backdrop"
                src="/images/games/word-wheel-studio.webp"
                alt=""
                fetchPriority="high"
            />
            <StudioLights
                theme="word-wheel"
                phase={lighting}
                transitionKey={`${attempt?.id}:${attempt?.revision}:${lighting}`}
                tension={spinning ? 1 : 0}
            />
            <div className="word-wheel-shell">
                <header className="word-wheel-header">
                    <div className="word-wheel-brand">
                        <span
                            className="word-wheel-brand-icon"
                            aria-hidden="true"
                        >
                            <Disc3 />
                        </span>
                        <h1>גלגל המילים</h1>
                    </div>
                    <div className="word-wheel-header-actions">
                        <button
                            className="word-wheel-icon-button"
                            type="button"
                            data-test="wheel-sound"
                            aria-label="צלילי הגלגל"
                            aria-pressed={soundEnabled}
                            title="צלילי הגלגל"
                            onClick={toggleSound}
                        >
                            {soundEnabled ? (
                                <Volume2 aria-hidden="true" />
                            ) : (
                                <VolumeX aria-hidden="true" />
                            )}
                        </button>
                        <a
                            href={exit.url()}
                            className="word-wheel-exit"
                            data-test="wheel-exit"
                        >
                            <ArrowRight aria-hidden="true" />
                            חזרה להורים
                        </a>
                    </div>
                </header>

                {isPlaying && (
                    <div className="word-wheel-hud">
                        <span className="word-wheel-player">{childName}</span>
                        <dl className="word-wheel-stats">
                            <div>
                                <Coins aria-hidden="true" />
                                <dt>נקודות</dt>
                                <dd dir="ltr" data-test="wheel-score">
                                    {numbers.format(attempt?.score ?? 0)}
                                </dd>
                            </div>
                            <div>
                                <BookOpen aria-hidden="true" />
                                <dt>אותיות</dt>
                                <dd dir="ltr">
                                    {visibleTiles.length}/{tiles.length}
                                </dd>
                            </div>
                        </dl>
                    </div>
                )}

                <div className="word-wheel-stage">
                    <section
                        className="word-wheel-panel word-wheel-puzzle"
                        aria-labelledby="wheel-puzzle-title"
                    >
                        {isPlaying && attempt ? (
                            <>
                                <h2
                                    id="wheel-puzzle-title"
                                    ref={puzzleTitle}
                                    tabIndex={-1}
                                >
                                    מה הביטוי?
                                </h2>
                                <p
                                    className="word-wheel-clue"
                                    data-test="wheel-clue"
                                >
                                    <Lightbulb aria-hidden="true" />
                                    <span>{attempt.clue}</span>
                                </p>
                                <PuzzleBoard board={attempt.board} />
                            </>
                        ) : isComplete && attempt ? (
                            <div
                                className="word-wheel-result"
                                data-test="wheel-result"
                            >
                                <span
                                    className="word-wheel-medal"
                                    aria-hidden="true"
                                >
                                    {stage === 'solved' ? <Trophy /> : <Eye />}
                                </span>
                                <h2
                                    id="wheel-puzzle-title"
                                    ref={puzzleTitle}
                                    tabIndex={-1}
                                >
                                    {stage === 'solved'
                                        ? 'פתרתם את הביטוי!'
                                        : 'הביטוי היה…'}
                                </h2>
                                {attempt.solution && (
                                    <p
                                        className="word-wheel-result-solution"
                                        data-test="wheel-answer"
                                    >
                                        {attempt.solution}
                                    </p>
                                )}
                                <p className="word-wheel-result-score">
                                    <Coins aria-hidden="true" />
                                    <bdi dir="ltr">
                                        {numbers.format(attempt.score)}
                                    </bdi>{' '}
                                    נקודות
                                </p>
                                {progress.solved > 0 && (
                                    <dl
                                        className="word-wheel-stats"
                                        data-test="wheel-progress"
                                    >
                                        <div>
                                            <dt>פתרונות</dt>
                                            <dd dir="ltr">
                                                {numbers.format(
                                                    progress.solved,
                                                )}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt>שיא נקודות</dt>
                                            <dd dir="ltr">
                                                {numbers.format(
                                                    progress.bestScore,
                                                )}
                                            </dd>
                                        </div>
                                    </dl>
                                )}
                                {canStart ? (
                                    <button
                                        className="word-wheel-primary"
                                        data-test="wheel-replay"
                                        type="button"
                                        disabled={locked}
                                        onClick={() => submit(start(teamSlug))}
                                    >
                                        ביטוי חדש
                                        <ArrowLeft aria-hidden="true" />
                                    </button>
                                ) : (
                                    <p className="word-wheel-muted">
                                        {availabilityMessage}
                                    </p>
                                )}
                            </div>
                        ) : (
                            <>
                                <h2
                                    id="wheel-puzzle-title"
                                    ref={puzzleTitle}
                                    tabIndex={-1}
                                >
                                    מגלים את הביטוי
                                </h2>
                                <p className="word-wheel-intro">
                                    מסובבים את הגלגל, בוחרים אות ומרוויחים
                                    נקודות אם היא מופיעה בביטוי.
                                </p>
                                <PuzzleBoard board={previewBoard} preview />
                                {canStart ? (
                                    <button
                                        className="word-wheel-primary"
                                        type="button"
                                        data-test="wheel-start"
                                        disabled={locked}
                                        onClick={() => submit(start(teamSlug))}
                                    >
                                        מתחילים לשחק
                                        <ArrowLeft aria-hidden="true" />
                                    </button>
                                ) : (
                                    <p className="word-wheel-muted">
                                        {availabilityMessage}
                                    </p>
                                )}
                            </>
                        )}
                    </section>

                    <section
                        className="word-wheel-panel word-wheel-spinner"
                        aria-labelledby="wheel-spinner-title"
                    >
                        <div className="word-wheel-spinner-heading">
                            <span
                                className="word-wheel-divider"
                                aria-hidden="true"
                            />
                            <h2 id="wheel-spinner-title">הגלגל</h2>
                            <span
                                className="word-wheel-divider"
                                aria-hidden="true"
                            />
                        </div>
                        <div className="word-wheel-art">
                            <SpinWheel
                                segments={segments.map((segment) => ({
                                    label: segmentLabel(segment),
                                    kind: segment.kind,
                                }))}
                                outcome={outcome}
                                onTick={tick}
                                onSettled={() => {
                                    setSpinning(false);
                                    land();
                                }}
                            />
                        </div>
                        <div className="word-wheel-spinner-controls">
                            {isPlaying ? (
                                <>
                                    <div
                                        className="word-wheel-status"
                                        data-test="wheel-status"
                                        data-feedback={
                                            spinning
                                                ? undefined
                                                : attempt?.feedback?.kind
                                        }
                                        role="status"
                                    >
                                        <strong>
                                            {spinning
                                                ? 'בסיבוב…'
                                                : currentSegment
                                                  ? segmentLabel(currentSegment)
                                                  : 'מוכן'}
                                        </strong>
                                        <span>
                                            {spinning
                                                ? 'הגלגל מסתובב…'
                                                : wheelStatus(
                                                      attempt,
                                                      currentSegment,
                                                  )}
                                        </span>
                                    </div>
                                    <button
                                        className="word-wheel-primary word-wheel-spin"
                                        type="button"
                                        disabled={locked || stage !== 'spin'}
                                        data-test="wheel-spin"
                                        onClick={() => playTurn(spin)}
                                    >
                                        <RotateCw aria-hidden="true" />
                                        סובבו את הגלגל
                                    </button>
                                </>
                            ) : (
                                <p className="word-wheel-legend">
                                    <Coins aria-hidden="true" />
                                    כל אות שווה נקודות
                                </p>
                            )}
                        </div>
                    </section>

                    {isPlaying && attempt && (
                        <section
                            className="word-wheel-panel word-wheel-letters"
                            aria-labelledby="wheel-letters-title"
                        >
                            <div className="word-wheel-letters-heading">
                                <div>
                                    <h2 id="wheel-letters-title">
                                        לוח האותיות
                                    </h2>
                                    <p>
                                        {stage === 'guess' && !spinning
                                            ? 'בחרו אות שעדיין לא ניחשתם.'
                                            : 'הבחירה נפתחת אחרי הסיבוב.'}
                                    </p>
                                </div>
                                <button
                                    className="word-wheel-outline"
                                    type="button"
                                    data-test="wheel-open-solve"
                                    ref={solveButton}
                                    disabled={locked}
                                    aria-expanded={openPanel === 'solve'}
                                    onClick={() =>
                                        openPanel === 'solve'
                                            ? hidePanel()
                                            : setOpenPanel('solve')
                                    }
                                >
                                    <Sparkles aria-hidden="true" />
                                    פתרון
                                </button>
                            </div>
                            <div
                                className="word-wheel-alphabet"
                                aria-label="אותיות"
                                role="group"
                            >
                                {alphabet.map((letter) => {
                                    const used = guessed.has(letter);
                                    const hit = used && found.has(letter);

                                    return (
                                        <button
                                            className="word-wheel-letter"
                                            key={letter}
                                            type="button"
                                            data-test={`wheel-letter-${letter}`}
                                            data-letter={letter}
                                            data-used={used || undefined}
                                            data-hit={hit || undefined}
                                            aria-label={
                                                used
                                                    ? `${letter}, ${hit ? 'נמצאה' : 'לא נמצאה'}`
                                                    : `האות ${letter}`
                                            }
                                            disabled={
                                                locked ||
                                                stage !== 'guess' ||
                                                used
                                            }
                                            onClick={() =>
                                                playTurn(guess, { letter })
                                            }
                                        >
                                            {letter}
                                        </button>
                                    );
                                })}
                            </div>
                            {openPanel === 'solve' && (
                                <form
                                    className="word-wheel-action-panel"
                                    onSubmit={submitSolution}
                                    onKeyDown={(event) => {
                                        if (event.key === 'Escape') {
                                            hidePanel();
                                        }
                                    }}
                                >
                                    <label htmlFor="wheel-solution">
                                        מה הביטוי המלא?
                                    </label>
                                    <div className="word-wheel-action-row">
                                        <input
                                            id="wheel-solution"
                                            data-test="wheel-solution"
                                            ref={solutionInput}
                                            value={solution}
                                            maxLength={120}
                                            autoComplete="off"
                                            required
                                            onChange={(event) =>
                                                setSolution(event.target.value)
                                            }
                                        />
                                        <button
                                            className="word-wheel-primary"
                                            data-test="wheel-solve"
                                            disabled={
                                                locked || !solution.trim()
                                            }
                                            type="submit"
                                        >
                                            <CircleCheck aria-hidden="true" />
                                            בדקו
                                        </button>
                                    </div>
                                    {attempt.feedback?.kind === 'incorrect' && (
                                        <p role="status">
                                            זה עוד לא הפתרון. נסו שוב.
                                        </p>
                                    )}
                                </form>
                            )}
                            <button
                                type="button"
                                className="word-wheel-text-button"
                                data-test="wheel-finish"
                                ref={finishButton}
                                disabled={locked}
                                aria-expanded={openPanel === 'finish'}
                                onClick={() =>
                                    openPanel === 'finish'
                                        ? hidePanel()
                                        : setOpenPanel('finish')
                                }
                            >
                                <Eye aria-hidden="true" />
                                לוותר ולגלות את הביטוי
                            </button>
                            {openPanel === 'finish' && (
                                <div
                                    className="word-wheel-action-panel"
                                    onKeyDown={(event) => {
                                        if (event.key === 'Escape') {
                                            hidePanel();
                                        }
                                    }}
                                >
                                    <p>לגלות את הביטוי ולסיים את הסיבוב?</p>
                                    <div className="word-wheel-action-row">
                                        <button
                                            type="button"
                                            className="word-wheel-outline"
                                            data-test="wheel-cancel-finish"
                                            ref={cancelFinish}
                                            onClick={hidePanel}
                                        >
                                            ממשיכים לשחק
                                        </button>
                                        <button
                                            type="button"
                                            className="word-wheel-outline"
                                            data-test="wheel-confirm-finish"
                                            disabled={locked}
                                            onClick={() => playTurn(finish)}
                                        >
                                            גלו את הביטוי
                                        </button>
                                    </div>
                                </div>
                            )}
                        </section>
                    )}
                </div>
                {error && (
                    <p className="word-wheel-error" role="alert">
                        {error}
                    </p>
                )}
            </div>
        </main>
    );
}
