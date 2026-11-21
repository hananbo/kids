import { Head, router, usePage } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { exit } from '@/routes/child-mode';
import { advance, answer, start } from '@/routes/quiz/play';

type Feedback = {
    answer: string;
    correct: boolean;
    explanation: string;
    selected: number;
};

type Props = {
    team: string;
    player: string;
    run: {
        id: number;
        position: number;
        total: number;
        completed: boolean;
        score: number | null;
    } | null;
    question: {
        id: number;
        prompt: string;
        choices: string[];
        feedback: Feedback | null;
    } | null;
};

export default function HebrewQuiz({ team, player, run, question }: Props) {
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
                <section className="grid gap-5 rounded-3xl bg-white p-5 shadow-sm sm:p-8 dark:bg-slate-900">
                    {run && question ? (
                        <>
                            <div className="grid gap-2">
                                <p className="font-medium">
                                    שאלה {run.position} מתוך {run.total}
                                </p>
                                <progress
                                    className="h-2 w-full accent-indigo-600"
                                    aria-label="התקדמות בחידון"
                                    value={run.position}
                                    max={run.total}
                                />
                            </div>
                            <h2 className="text-2xl font-semibold" dir="auto">
                                {question.prompt}
                            </h2>
                            <div className="grid gap-3">
                                {question.choices.map((choice, index) => (
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
                                        <bdi>{choice}</bdi>
                                    </Button>
                                ))}
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
                                            <bdi>{feedback.answer}</bdi>
                                        )}
                                    </p>
                                    <p dir="auto">{feedback.explanation}</p>
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
                                        תשובות נכונות: {run.score} מתוך{' '}
                                        {run.total}
                                    </p>
                                </>
                            ) : (
                                <h2 className="text-2xl font-semibold">
                                    שלום, <bdi>{player}</bdi>!
                                </h2>
                            )}
                            <Button
                                data-test="start-run"
                                className="min-h-12"
                                disabled={processing}
                                onClick={() => submit(start.url(team))}
                            >
                                {run?.completed ? 'חידון חדש' : 'מתחילים'}
                            </Button>
                        </>
                    )}
                    {errors.pool && <p role="alert">{errors.pool}</p>}
                </section>
            </div>
        </main>
    );
}
