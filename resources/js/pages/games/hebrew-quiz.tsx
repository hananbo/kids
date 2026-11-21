import { Head, router, usePage } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { useState } from 'react';
import type { QuizField } from '@/components/games/question-field';
import {
    BidiText,
    isMedia,
    QuestionField,
} from '@/components/games/question-field';
import { Button } from '@/components/ui/button';
import { exit } from '@/routes/child-mode';
import { advance, answer, hint, start } from '@/routes/quiz/play';

type Feedback = {
    answer: string;
    correct: boolean;
    explanation: string;
    selected: number;
};

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
    } | null;
    question: {
        id: number;
        prompt: QuizField;
        choices: QuizField[];
        hintAvailable: boolean;
        hint: string | null;
        feedback: Feedback | null;
    } | null;
};

export default function HebrewQuiz({
    team,
    player,
    available,
    progress,
    run,
    question,
}: Props) {
    const [processing, setProcessing] = useState(false);
    const { errors } = usePage().props;
    const feedback = question?.feedback;

    function submit(
        url: string,
        data: { question?: number; choice?: number } = {},
    ) {
        router.post(url, data, {
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <main
            dir="rtl"
            lang="he"
            className="min-h-dvh bg-indigo-50 px-4 py-6 text-indigo-950 dark:bg-slate-950 dark:text-white"
        >
            <Head title="חידון הניצוצות" />
            <div className="mx-auto grid max-w-xl gap-5">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                        <Sparkles className="size-5" aria-hidden="true" />
                        <h1 className="text-xl font-bold">חידון הניצוצות</h1>
                    </div>
                    <a
                        href={exit.url()}
                        className="inline-flex min-h-11 items-center rounded-md px-2 text-sm underline underline-offset-4"
                    >
                        חזרה להורים
                    </a>
                </header>
                <p className="text-sm font-medium">
                    רמה {question && run ? run.level : progress.highestLevel}
                    {' · '}
                    {progress.stars} כוכבים שנצברו
                </p>
                <section className="grid gap-5 rounded-3xl bg-white p-5 shadow-sm sm:p-8 dark:bg-slate-900">
                    {run && question ? (
                        <>
                            <div className="grid gap-2">
                                <div className="flex flex-wrap justify-between gap-2">
                                    <p className="font-medium">
                                        שאלה {run.position} מתוך {run.total}
                                    </p>
                                    <p className="text-sm" aria-live="polite">
                                        {run.stars} כוכבים בסבב
                                    </p>
                                </div>
                                <progress
                                    className="h-2 w-full accent-indigo-600"
                                    aria-label="התקדמות בחידון"
                                    value={run.position}
                                    max={run.total}
                                />
                            </div>
                            {isMedia(question.prompt) ? (
                                <QuestionField field={question.prompt} />
                            ) : (
                                <h2 className="text-2xl font-semibold">
                                    <QuestionField field={question.prompt} />
                                </h2>
                            )}
                            {question.hintAvailable && (
                                <Button
                                    variant="secondary"
                                    className="min-h-11 justify-self-start"
                                    data-test="reveal-hint"
                                    disabled={processing}
                                    onClick={() =>
                                        submit(
                                            hint.url({
                                                current_team: team,
                                                run: run.id,
                                            }),
                                            { question: question.id },
                                        )
                                    }
                                >
                                    תנו לי רמז
                                </Button>
                            )}
                            {question.hint && (
                                <p
                                    className="rounded-xl bg-indigo-50 p-3 dark:bg-indigo-950"
                                    role="status"
                                >
                                    <BidiText value={question.hint} />
                                </p>
                            )}
                            <div className="grid gap-3">
                                {question.choices.map((choice, index) => {
                                    const button = (
                                        <Button
                                            key={index}
                                            variant={
                                                feedback?.selected === index
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                            disabled={
                                                processing || Boolean(feedback)
                                            }
                                            data-test="choose-answer"
                                            className="min-h-12 justify-start rounded-xl px-4 py-2 text-wrap"
                                            onClick={() =>
                                                submit(
                                                    answer.url({
                                                        current_team: team,
                                                        run: run.id,
                                                    }),
                                                    {
                                                        question: question.id,
                                                        choice: index,
                                                    },
                                                )
                                            }
                                        >
                                            {isMedia(choice) ? (
                                                <>
                                                    בחירה:{' '}
                                                    <BidiText
                                                        value={
                                                            choice.alternative
                                                        }
                                                    />
                                                </>
                                            ) : (
                                                <QuestionField field={choice} />
                                            )}
                                        </Button>
                                    );

                                    return isMedia(choice) ? (
                                        <div
                                            key={index}
                                            className="grid gap-2 rounded-xl border p-3"
                                        >
                                            <QuestionField field={choice} />
                                            {button}
                                        </div>
                                    ) : (
                                        button
                                    );
                                })}
                            </div>
                            {feedback && (
                                <div
                                    className="grid gap-3 rounded-xl bg-indigo-50 p-4 dark:bg-indigo-950"
                                    role="status"
                                >
                                    <p className="font-semibold">
                                        {feedback.correct
                                            ? 'נכון!'
                                            : 'התשובה הנכונה היא '}
                                        {!feedback.correct && (
                                            <BidiText value={feedback.answer} />
                                        )}
                                    </p>
                                    <p>
                                        <BidiText
                                            value={feedback.explanation}
                                        />
                                    </p>
                                </div>
                            )}
                            {feedback && (
                                <Button
                                    data-test="advance"
                                    className="min-h-12"
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
                                        )
                                    }
                                >
                                    {run.position === run.total
                                        ? 'סיום החידון'
                                        : 'לשאלה הבאה'}
                                </Button>
                            )}
                        </>
                    ) : (
                        <>
                            {run?.completed ? (
                                <>
                                    <h2 className="text-2xl font-semibold">
                                        החידון הסתיים!
                                    </h2>
                                    <p>
                                        צברת {run.stars} מתוך {run.total} כוכבים
                                    </p>
                                    {run.unlockedLevel !== null && (
                                        <p
                                            className="font-semibold"
                                            role="status"
                                        >
                                            פתחת את רמה {run.unlockedLevel}!
                                        </p>
                                    )}
                                </>
                            ) : (
                                <h2 className="text-2xl font-semibold">
                                    שלום, <bdi>{player}</bdi>!
                                </h2>
                            )}
                            {run?.cancelled && (
                                <p role="status">
                                    החידון הופסק כי אחת השאלות הוסרה.
                                </p>
                            )}
                            {available ? (
                                <Button
                                    data-test="start-run"
                                    className="min-h-12"
                                    disabled={processing}
                                    onClick={() => submit(start.url(team))}
                                >
                                    {run?.completed ? 'חידון חדש' : 'מתחילים'}
                                </Button>
                            ) : (
                                <p role="status">החידון אינו זמין כרגע.</p>
                            )}
                        </>
                    )}
                    {errors.pool && <p role="alert">{errors.pool}</p>}
                </section>
            </div>
        </main>
    );
}
