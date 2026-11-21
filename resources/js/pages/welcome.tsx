import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Check, Sparkles } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, home, login, register } from '@/routes';

const highlights = [
    'A thoughtful foundation for your team',
    'Secure, fast, and ready to shape',
    'Everything important stays in one place',
];

export default function Welcome() {
    const { auth, currentTeam, name } = usePage().props;
    const dashboardUrl = currentTeam ? dashboard(currentTeam.slug) : '/';

    return (
        <>
            <Head title="Welcome" />

            <div className="relative min-h-svh overflow-hidden px-5 py-5 sm:px-8 sm:py-7 lg:px-12">
                <div className="pointer-events-none absolute -top-32 -left-24 size-[30rem] rounded-full bg-primary/10 blur-3xl" />
                <div className="pointer-events-none absolute right-[-12rem] bottom-[-14rem] size-[38rem] rounded-full bg-secondary blur-3xl" />

                <header className="relative mx-auto flex max-w-7xl items-center justify-between rounded-2xl bg-card/75 px-4 py-3 shadow-soft backdrop-blur-xl sm:px-5">
                    <Link
                        href={home()}
                        className="flex items-center gap-3 rounded-xl outline-none focus-visible:ring-3 focus-visible:ring-ring/40"
                    >
                        <span className="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm">
                            <AppLogoIcon className="size-6 fill-current" />
                        </span>
                        <span className="text-sm font-semibold tracking-tight">
                            {name}
                        </span>
                    </Link>

                    <nav
                        className="flex items-center gap-2"
                        aria-label="Account"
                    >
                        {auth.user ? (
                            <Button asChild size="sm">
                                <Link href={dashboardUrl} prefetch>
                                    Open dashboard
                                    <ArrowRight />
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="ghost" size="sm">
                                    <Link href={login()} prefetch>
                                        Log in
                                    </Link>
                                </Button>
                                <Button asChild size="sm">
                                    <Link href={register()} prefetch>
                                        Get started
                                    </Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main className="relative mx-auto grid min-h-[calc(100svh-7rem)] max-w-7xl items-center gap-14 py-16 lg:grid-cols-[1.05fr_0.95fr] lg:gap-20 lg:py-20">
                    <section className="max-w-2xl">
                        <div className="mb-7 inline-flex items-center gap-2 rounded-full bg-accent px-3 py-1.5 text-xs font-semibold text-accent-foreground">
                            <Sparkles className="size-3.5" />A calmer way to get
                            things done
                        </div>

                        <h1 className="max-w-xl text-5xl leading-[0.98] font-semibold text-balance sm:text-6xl lg:text-7xl">
                            Give your best work room to breathe.
                        </h1>
                        <p className="mt-7 max-w-lg text-base leading-7 text-muted-foreground sm:text-lg sm:leading-8">
                            {name} brings your work, people, and next steps into
                            one clear space—without the visual noise.
                        </p>

                        <div className="mt-10 flex flex-wrap items-center gap-3">
                            <Button asChild size="lg">
                                <Link
                                    href={auth.user ? dashboardUrl : register()}
                                    prefetch
                                >
                                    {auth.user
                                        ? 'Go to your workspace'
                                        : 'Create your workspace'}
                                    <ArrowRight />
                                </Link>
                            </Button>
                            {!auth.user && (
                                <Button asChild size="lg" variant="ghost">
                                    <Link href={login()} prefetch>
                                        I have an account
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </section>

                    <section
                        className="relative mx-auto w-full max-w-xl"
                        aria-label="Product preview"
                    >
                        <div className="absolute inset-10 -z-10 rounded-full bg-primary/20 blur-3xl" />
                        <div className="rounded-[2rem] bg-card p-3 shadow-float">
                            <div className="rounded-[1.4rem] bg-muted/70 p-6 sm:p-8">
                                <div className="flex items-start justify-between gap-6">
                                    <div>
                                        <p className="text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                                            Today
                                        </p>
                                        <h2 className="mt-2 text-2xl font-semibold">
                                            A clear path forward
                                        </h2>
                                    </div>
                                    <span className="size-3 rounded-full bg-primary shadow-[0_0_0_7px_oklch(0.42_0.09_153/0.12)]" />
                                </div>

                                <div className="mt-10 grid gap-3">
                                    {highlights.map((highlight, index) => (
                                        <div
                                            key={highlight}
                                            className="flex items-center gap-4 rounded-2xl bg-card px-4 py-4 shadow-soft transition-transform duration-300 hover:-translate-y-0.5"
                                            style={{
                                                animationDelay: `${index * 100}ms`,
                                            }}
                                        >
                                            <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-accent text-accent-foreground">
                                                <Check className="size-4" />
                                            </span>
                                            <span className="text-sm font-medium">
                                                {highlight}
                                            </span>
                                        </div>
                                    ))}
                                </div>

                                <div className="mt-8 flex items-end gap-2 rounded-2xl bg-primary p-5 text-primary-foreground">
                                    {[42, 62, 48, 78, 58, 88, 72].map(
                                        (height, index) => (
                                            <span
                                                key={`${height}-${index}`}
                                                className="flex-1 rounded-full bg-primary-foreground/85"
                                                style={{ height }}
                                            />
                                        ),
                                    )}
                                </div>
                            </div>
                        </div>
                    </section>
                </main>
            </div>
        </>
    );
}
