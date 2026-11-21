import { Head, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    Lightbulb,
    Lock,
    Play,
    RotateCcw,
    ShieldCheck,
    Star,
    Trophy,
    UsersRound,
    Volume2,
    VolumeX,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { QuizField } from '@/components/games/question-field';
import {
    BidiText,
    isMedia,
    QuestionField,
} from '@/components/games/question-field';
import type { StudioPhase } from '@/components/games/studio-lights';
import { StudioLights } from '@/components/games/studio-lights';
import { useQuizSound } from '@/hooks/use-quiz-sound';
import { exit } from '@/routes/child-mode';
import {
    advance,
    answer,
    hint,
    lifeline,
    start,
    walk,
} from '@/routes/quiz/play';
import './hebrew-quiz.css';

type Feedback = {
    answer: string;
    correct: boolean;
    correctIndex: number;
    explanation: string;
    selected: number;
};

type QuizSubmit = (
    url: string,
    data?: {
        question?: number;
        choice?: number;
        kind?: 'fifty' | 'audience' | 'advisor';
    },
    cue?: 'answer' | 'complete' | 'exit',
) => void;

type Props = {
    team: string;
    player: string;
    available: boolean;
    progress: {
        highestLevel: number;
        stars: number;
    };
    run: {
        id: number;
        position: number;
        total: number;
        completed: boolean;
        cancelled: boolean;
        level: number;
        unlockedLevel: number | null;
        stars: number;
        prizes: number[];
        safetyPrizes: number[];
        payout: number;
        outcome: 'won' | 'lost' | 'walked' | null;
    } | null;
    question: {
        id: number;
        prompt: QuizField;
        choices: QuizField[];
        hintAvailable: boolean;
        hint: string | null;
        lifelines: {
            fiftyUsed: boolean;
            audienceUsed: boolean;
            advisorUsed: boolean;
            advisorChoice: number | null;
            eliminated: number[];
            votes: number[] | null;
        };
        feedback: Feedback | null;
    } | null;
};

const score = new Intl.NumberFormat('he-IL');
const choiceLabels = ['א', 'ב', 'ג', 'ד'];

function PrizeLadder({ run }: { run: NonNullable<Props['run']> }) {
    const guaranteed =
        run.safetyPrizes.filter((prize) => prize <= run.payout).at(-1) ?? 0;

    return (
        <aside className="spark-ladder" aria-labelledby="prize-ladder-title">
            <h2 id="prize-ladder-title" className="spark-ladder-heading">
                סולם הניצוצות
            </h2>
            <ol className="spark-ladder-steps" reversed>
                {[...run.prizes].reverse().map((prize, index) => {
                    const position = run.total - index;
                    const safe = run.safetyPrizes.includes(prize);
                    const current = position === run.position && !run.completed;

                    return (
                        <li
                            key={prize}
                            className="spark-ladder-step"
                            data-state={
                                current
                                    ? 'current'
                                    : prize <= run.payout
                                      ? 'earned'
                                      : 'upcoming'
                            }
                            data-safe={safe || undefined}
                            aria-current={current ? 'step' : undefined}
                        >
                            <span className="spark-ladder-position">
                                {position}
                            </span>
                            <bdi dir="ltr" className="spark-ladder-value">
                                {score.format(prize)}
                            </bdi>
                            {safe && <ShieldCheck aria-label="תחנת ביטחון" />}
                        </li>
                    );
                })}
            </ol>
            <dl className="spark-ladder-totals">
                <div>
                    <dt>בקופה</dt>
                    <dd>
                        <bdi dir="ltr">{score.format(run.payout)}</bdi>
                    </dd>
                </div>
                <div>
                    <dt>מובטח</dt>
                    <dd>
                        <bdi dir="ltr">{score.format(guaranteed)}</bdi>
                    </dd>
                </div>
            </dl>
        </aside>
    );
}

export default function HebrewQuiz({
    team,
    player,
    available,
    progress,
    run,
    question,
}: Props) {
    const [processing, setProcessing] = useState(false);
    const [locking, setLocking] = useState(false);
    const [openedQuestion, setOpenedQuestion] = useState<number | null>(null);
    const [walkingQuestion, setWalkingQuestion] = useState<number | null>(null);
    const { errors } = usePage().props;
    const feedback = question?.feedback;
    const isBreak = Boolean(
        question && !feedback && openedQuestion !== question.id,
    );
    const walking = question !== null && walkingQuestion === question.id;
    const { soundEnabled, toggleSound, playCue, unlockSound, soundScope } =
        useQuizSound({
            suspense: Boolean(question && !isBreak && !feedback && !walking),
            tension: run ? (run.position - 1) / Math.max(1, run.total - 1) : 0,
        });

    const submit: QuizSubmit = (url, data = {}, cue) => {
        router.post(url, data, {
            onStart: () => {
                setProcessing(true);
                setLocking(cue === 'answer');
            },
            onFinish: () => {
                setProcessing(false);
                setLocking(false);
            },
            onSuccess: (page) => {
                if (cue === 'answer') {
                    const answered = page.props.question as Props['question'];

                    if (
                        answered?.feedback &&
                        answered.id === data.question &&
                        answered.feedback.selected === data.choice
                    ) {
                        playCue(
                            answered.feedback.correct ? 'correct' : 'incorrect',
                        );
                    }
                }

                if (cue === 'complete' || cue === 'exit') {
                    const finished = page.props.run as Props['run'];

                    if (
                        finished?.outcome ===
                        (cue === 'complete' ? 'won' : 'walked')
                    ) {
                        playCue(cue);
                    }
                }
            },
        });
    };

    const isPlaying = Boolean(run && question);
    const isComplete = Boolean(run?.completed);
    const target = run ? run.prizes[run.position - 1] : 0;
    const safeTarget = Boolean(run?.safetyPrizes.includes(target));
    let phase: StudioPhase = 'welcome';

    if (isComplete) {
        phase =
            run?.outcome === 'won'
                ? 'won'
                : run?.outcome === 'lost'
                  ? 'incorrect'
                  : 'walked';
    } else if (feedback) {
        phase = feedback.correct ? 'correct' : 'incorrect';
    } else if (isBreak) {
        phase =
            safeTarget || target === 1000000 ? 'checkpoint' : 'intermission';
    } else if (isPlaying) {
        phase = locking ? 'lock' : 'question';
    }

    const canWalk = Boolean(run && question && !feedback && run.payout > 0);

    return (
        <main
            ref={soundScope}
            dir="rtl"
            lang="he"
            className="spark-quiz"
            data-scene={phase}
            data-stage={
                isBreak
                    ? 'level-break'
                    : isPlaying
                      ? 'playing'
                      : isComplete
                        ? 'complete'
                        : 'welcome'
            }
        >
            <Head title="חידון הניצוצות" />
            <img
                className="spark-backdrop"
                src="/images/games/spark-studio.webp"
                width="1672"
                height="941"
                fetchPriority="high"
                alt=""
            />
            <StudioLights
                phase={phase}
                transitionKey={`${run?.id}:${question?.id}:${phase}`}
                tension={
                    run ? (run.position - 1) / Math.max(1, run.total - 1) : 0
                }
            />
            <div className="spark-shell">
                <header className="spark-header">
                    <div className="spark-wordmark">
                        <span
                            className="spark-wordmark-icon"
                            aria-hidden="true"
                        >
                            <span className="spark-shape" />
                        </span>
                        {isPlaying || isComplete ? (
                            <h1>חידון הניצוצות</h1>
                        ) : (
                            <span>ניצוצות</span>
                        )}
                    </div>
                    <div className="spark-header-actions">
                        <button
                            type="button"
                            className="spark-sound"
                            data-test="quiz-sound"
                            aria-label="צלילי החידון"
                            aria-pressed={soundEnabled}
                            title="צלילי החידון"
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
                            className="spark-exit"
                            data-test="exit-quiz"
                        >
                            <ArrowRight aria-hidden="true" />
                            חזרה להורים
                        </a>
                    </div>
                </header>

                {run && question ? (
                    <div className="spark-game-layout">
                        <section className="spark-game" aria-label="סבב החידון">
                            <div className="spark-round">
                                <div className="spark-round-heading">
                                    <div>
                                        <p className="spark-level">
                                            רמה {run.level}
                                        </p>
                                        <p className="spark-round-position">
                                            שאלה {run.position} מתוך {run.total}
                                        </p>
                                    </div>
                                    <p
                                        className="spark-stars"
                                        aria-live="polite"
                                    >
                                        <Star aria-hidden="true" />
                                        {run.stars} כוכבים בסבב
                                    </p>
                                </div>
                            </div>
                            {isBreak ? (
                                <div
                                    key={question.id}
                                    className="spark-level-break spark-finish"
                                >
                                    {safeTarget ? (
                                        <ShieldCheck aria-hidden="true" />
                                    ) : (
                                        <Star aria-hidden="true" />
                                    )}
                                    <h2>
                                        {target === 1000000
                                            ? 'שאלת המיליון'
                                            : safeTarget
                                              ? 'תחנת ביטחון'
                                              : `שלב ${run.position}`}
                                    </h2>
                                    <p className="spark-break-prize">
                                        <bdi dir="ltr">
                                            {score.format(target)}
                                        </bdi>
                                    </p>
                                    <p className="spark-stakes">
                                        {safeTarget
                                            ? 'תשובה נכונה מבטיחה את הסכום הזה עד סוף החידון.'
                                            : 'תשובה נכונה תעלה את הקופה לסכום הזה.'}
                                    </p>
                                    {!walking && (
                                        <div className="spark-break-actions">
                                            <button
                                                type="button"
                                                className="spark-primary"
                                                data-test="open-question"
                                                onClick={() => {
                                                    unlockSound();
                                                    playCue(
                                                        safeTarget
                                                            ? 'level'
                                                            : 'next',
                                                    );
                                                    setOpenedQuestion(
                                                        question.id,
                                                    );
                                                }}
                                            >
                                                <Play aria-hidden="true" />
                                                פתחו את השאלה
                                            </button>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <QuizQuestion
                                    key={question.id}
                                    run={run}
                                    question={question}
                                    team={team}
                                    processing={processing}
                                    walking={walking}
                                    submit={submit}
                                    onLock={() => playCue('lock')}
                                />
                            )}
                            {walking && (
                                <div
                                    className="spark-break-actions"
                                    role="group"
                                    aria-label="אישור פרישה"
                                >
                                    <p>
                                        לסיים עם{' '}
                                        <bdi dir="ltr">
                                            {score.format(run.payout)}
                                        </bdi>{' '}
                                        ניצוצות?
                                    </p>
                                    <button
                                        type="button"
                                        className="spark-primary"
                                        data-test="confirm-walk"
                                        disabled={processing}
                                        onClick={() =>
                                            submit(
                                                walk.url({
                                                    current_team: team,
                                                    run: run.id,
                                                }),
                                                { question: question.id },
                                                'exit',
                                            )
                                        }
                                    >
                                        כן, לסיים
                                    </button>
                                    <button
                                        type="button"
                                        className="spark-walk"
                                        data-test="cancel-walk"
                                        disabled={processing}
                                        onClick={() => setWalkingQuestion(null)}
                                    >
                                        להמשיך לשחק
                                    </button>
                                </div>
                            )}
                            {canWalk && !walking && (
                                <button
                                    type="button"
                                    className="spark-walk"
                                    data-test="walk-away"
                                    disabled={processing}
                                    onClick={() =>
                                        setWalkingQuestion(question.id)
                                    }
                                >
                                    לפרוש עם{' '}
                                    <bdi dir="ltr">
                                        {score.format(run.payout)}
                                    </bdi>{' '}
                                    ניצוצות
                                </button>
                            )}
                            <p className="spark-total">
                                {progress.stars} כוכבים שנצברו
                            </p>
                        </section>
                        <PrizeLadder run={run} />
                    </div>
                ) : (
                    <section className="spark-intro">
                        <div className="spark-intro-copy">
                            <div className="spark-level">
                                רמה {progress.highestLevel}
                            </div>
                            {run?.completed ? (
                                <>
                                    <h2 className="spark-intro-title">
                                        {run.outcome === 'won'
                                            ? 'הגעתם למיליון!'
                                            : run.outcome === 'walked'
                                              ? 'סיימתם עם הקופה!'
                                              : 'החידון הסתיים!'}
                                    </h2>
                                    {run.outcome && (
                                        <p
                                            className="spark-break-prize"
                                            data-test="final-payout"
                                        >
                                            <bdi dir="ltr">
                                                {score.format(run.payout)}
                                            </bdi>{' '}
                                            ניצוצות
                                        </p>
                                    )}
                                    <p className="spark-score">
                                        <Star aria-hidden="true" />
                                        צברת {run.stars} מתוך {run.total} כוכבים
                                    </p>
                                    {run.unlockedLevel !== null && (
                                        <p
                                            className="spark-unlock"
                                            role="status"
                                        >
                                            פתחת את רמה {run.unlockedLevel}!
                                        </p>
                                    )}
                                </>
                            ) : (
                                <>
                                    <p className="spark-greeting">
                                        שלום, <bdi>{player}</bdi>!
                                    </p>
                                    <h1 className="spark-intro-title">
                                        חידון <span>הניצוצות</span>
                                    </h1>
                                    <p className="spark-description">
                                        טפסו מ־<bdi dir="ltr">100</bdi> עד{' '}
                                        <bdi dir="ltr">1,000,000</bdi> ניצוצות.
                                        אפשר לפרוש עם הקופה. תשובה שגויה משאירה
                                        רק את הסכום שהבטחתם בתחנת ביטחון.
                                    </p>
                                </>
                            )}
                            {run?.cancelled && (
                                <p className="spark-notice" role="status">
                                    החידון הופסק כי אחת השאלות הוסרה.
                                </p>
                            )}
                            {available ? (
                                <button
                                    type="button"
                                    data-test="start-run"
                                    className="spark-primary"
                                    disabled={processing}
                                    onClick={() => {
                                        unlockSound();
                                        playCue('start');
                                        submit(start.url(team));
                                    }}
                                >
                                    {run?.completed ? (
                                        <RotateCcw aria-hidden="true" />
                                    ) : (
                                        <Play aria-hidden="true" />
                                    )}
                                    {run?.completed ? 'חידון חדש' : 'מתחילים'}
                                </button>
                            ) : (
                                <p className="spark-notice" role="status">
                                    החידון אינו זמין כרגע.
                                </p>
                            )}
                            <p className="spark-total">
                                {progress.stars} כוכבים שנצברו
                            </p>
                        </div>
                        <div className="spark-mark" aria-hidden="true">
                            <div className="spark-mark-orbit" />
                            <img
                                className="spark-emblem"
                                src="/images/games/spark-emblem.webp"
                                width="619"
                                height="620"
                                alt=""
                            />
                            {run?.outcome === 'won' && (
                                <Trophy className="spark-trophy" />
                            )}
                        </div>
                    </section>
                )}
                {errors.pool && (
                    <p className="spark-notice spark-pool-error" role="alert">
                        {errors.pool}
                    </p>
                )}
            </div>
        </main>
    );
}

function QuizQuestion({
    question,
    team,
    run,
    processing,
    walking,
    submit,
    onLock,
}: {
    question: NonNullable<Props['question']>;
    team: string;
    run: NonNullable<Props['run']>;
    processing: boolean;
    walking: boolean;
    submit: QuizSubmit;
    onLock: () => void;
}) {
    const [selectedChoice, setSelectedChoice] = useState<number>();
    const { feedback } = question;
    const showHint = question.hintAvailable || question.hint !== null;

    return (
        <>
            <div className="spark-question">
                {isMedia(question.prompt) ? (
                    <QuestionField field={question.prompt} />
                ) : (
                    <h2>
                        <QuestionField field={question.prompt} />
                    </h2>
                )}
            </div>
            {!feedback && !walking && (
                <div
                    className="spark-lifelines"
                    role="group"
                    aria-label="גלגלי הצלה"
                >
                    <button
                        type="button"
                        className="spark-hint"
                        data-test={
                            showHint ? 'reveal-hint' : 'lifeline-advisor'
                        }
                        disabled={processing || question.lifelines.advisorUsed}
                        onClick={() => {
                            const route = { current_team: team, run: run.id };

                            if (showHint) {
                                submit(hint.url(route), {
                                    question: question.id,
                                });

                                return;
                            }

                            submit(lifeline.url(route), {
                                question: question.id,
                                kind: 'advisor',
                            });
                        }}
                    >
                        <Lightbulb aria-hidden="true" />
                        {showHint ? 'תנו לי רמז' : 'התייעצות'}
                    </button>
                    <button
                        type="button"
                        className="spark-hint"
                        data-test="lifeline-fifty"
                        disabled={processing || question.lifelines.fiftyUsed}
                        onClick={() => {
                            setSelectedChoice(undefined);
                            submit(
                                lifeline.url({
                                    current_team: team,
                                    run: run.id,
                                }),
                                {
                                    question: question.id,
                                    kind: 'fifty',
                                },
                            );
                        }}
                    >
                        <bdi dir="ltr">50:50</bdi>
                    </button>
                    <button
                        type="button"
                        className="spark-hint"
                        data-test="lifeline-audience"
                        disabled={processing || question.lifelines.audienceUsed}
                        onClick={() =>
                            submit(
                                lifeline.url({
                                    current_team: team,
                                    run: run.id,
                                }),
                                {
                                    question: question.id,
                                    kind: 'audience',
                                },
                            )
                        }
                    >
                        <UsersRound aria-hidden="true" />
                        שאלו את הקהל
                    </button>
                </div>
            )}
            {question.hint && (
                <p className="spark-hint-text" role="status">
                    <Lightbulb aria-hidden="true" />
                    <span>
                        <BidiText value={question.hint} />
                    </span>
                </p>
            )}
            {question.lifelines.advisorChoice !== null && (
                <p className="spark-hint-text" role="status">
                    <Lightbulb aria-hidden="true" />
                    <span>
                        היועץ המדומה מציע לבחור בתשובה{' '}
                        {choiceLabels[question.lifelines.advisorChoice]}׳.
                    </span>
                </p>
            )}
            {question.lifelines.votes && (
                <p className="spark-stakes" role="status">
                    תוצאות הצבעת הקהל המדומה
                </p>
            )}
            <div
                key={`choices-${question.id}`}
                className="spark-choices"
                aria-label="אפשרויות תשובה"
                role="group"
            >
                {question.choices.map((choice, index) => {
                    const eliminated =
                        question.lifelines.eliminated.includes(index);
                    const selected = selectedChoice === index;
                    const votes = question.lifelines.votes?.[index];
                    const state = feedback
                        ? index === feedback.correctIndex
                            ? 'correct'
                            : index === feedback.selected
                              ? 'incorrect'
                              : 'inactive'
                        : eliminated
                          ? 'eliminated'
                          : selected
                            ? 'pending'
                            : undefined;
                    const media = !eliminated && isMedia(choice);

                    const button = (
                        <button
                            key={index}
                            type="button"
                            className="spark-choice"
                            disabled={
                                processing ||
                                Boolean(feedback) ||
                                eliminated ||
                                walking
                            }
                            data-test="choose-answer"
                            data-state={state}
                            aria-pressed={
                                feedback || eliminated ? undefined : selected
                            }
                            onClick={() => setSelectedChoice(index)}
                        >
                            <span
                                className="spark-choice-index"
                                aria-hidden="true"
                            >
                                {state === 'correct' ? (
                                    <Check />
                                ) : state === 'incorrect' ? (
                                    <X />
                                ) : (
                                    choiceLabels[index]
                                )}
                            </span>
                            <span className="spark-choice-text">
                                {eliminated ? (
                                    'התשובה הוסרה'
                                ) : media ? (
                                    <>
                                        בחירה:{' '}
                                        <BidiText value={choice.alternative} />
                                    </>
                                ) : (
                                    <QuestionField field={choice} />
                                )}
                            </span>
                            {votes !== undefined && (
                                <bdi
                                    dir="ltr"
                                    className="spark-vote"
                                    aria-label={`${votes} אחוזים מקולות הקהל`}
                                >
                                    {votes}%
                                </bdi>
                            )}
                        </button>
                    );

                    return media ? (
                        <div key={index} className="spark-media-choice">
                            <QuestionField field={choice} />
                            {button}
                        </div>
                    ) : (
                        button
                    );
                })}
            </div>
            {!feedback && !walking && (
                <div className="spark-answer-actions">
                    <button
                        type="button"
                        className="spark-primary"
                        data-test="lock-answer"
                        disabled={processing || selectedChoice === undefined}
                        onClick={() => {
                            if (selectedChoice === undefined) {
                                return;
                            }

                            onLock();
                            submit(
                                answer.url({
                                    current_team: team,
                                    run: run.id,
                                }),
                                {
                                    question: question.id,
                                    choice: selectedChoice,
                                },
                                'answer',
                            );
                        }}
                    >
                        <Lock aria-hidden="true" />
                        תשובה סופית
                    </button>
                </div>
            )}
            {feedback && (
                <div className="spark-answer-result">
                    <div
                        className="spark-feedback"
                        data-result={feedback.correct ? 'correct' : 'incorrect'}
                        role="status"
                    >
                        <span
                            className="spark-feedback-icon"
                            aria-hidden="true"
                        >
                            {feedback.correct ? <Check /> : <X />}
                        </span>
                        <div>
                            <p>
                                {feedback.correct
                                    ? 'נכון!'
                                    : 'התשובה הנכונה היא '}
                                {!feedback.correct && (
                                    <BidiText value={feedback.answer} />
                                )}
                            </p>
                            <p>
                                <BidiText value={feedback.explanation} />
                            </p>
                            {!feedback.correct && (
                                <p>
                                    סכום מובטח{' '}
                                    <bdi dir="ltr">
                                        {score.format(run.payout)}
                                    </bdi>{' '}
                                    ניצוצות.
                                </p>
                            )}
                        </div>
                    </div>
                    <button
                        type="button"
                        data-test="advance"
                        className="spark-primary"
                        disabled={processing}
                        onClick={() =>
                            submit(
                                advance.url({
                                    current_team: team,
                                    run: run.id,
                                }),
                                {
                                    question: question.id,
                                },
                                feedback.correct && run.position === run.total
                                    ? 'complete'
                                    : undefined,
                            )
                        }
                    >
                        {!feedback.correct || run.position === run.total
                            ? 'סיום החידון'
                            : 'לשאלה הבאה'}
                        <ArrowLeft aria-hidden="true" />
                    </button>
                </div>
            )}
        </>
    );
}
