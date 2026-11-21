import type { Container, WebGLRenderer } from 'pixi.js';
import type * as Pixi from 'pixi.js';
import { useEffect, useEffectEvent, useRef } from 'react';
import type { ComponentPropsWithoutRef } from 'react';

export type GameScene = (engine: {
    pixi: typeof Pixi;
    stage: Container;
    canvas: HTMLCanvasElement;
}) => {
    resize?: (width: number, height: number) => void;
    update?: (deltaSeconds: number, reducedMotion: boolean) => void;
    dispose?: () => void;
};

type Props = Omit<ComponentPropsWithoutRef<'div'>, 'children'> & {
    scene: GameScene;
    canvasClassName?: string;
    reducedMotion?: 'fallback' | 'render';
    interactive?: boolean;
    pixelBudget?: number;
};

const maxPixels = 1_250_000;

export function GameCanvas({
    scene,
    canvasClassName = '',
    reducedMotion = 'fallback',
    interactive = false,
    pixelBudget = maxPixels,
    ...props
}: Props) {
    const hostRef = useRef<HTMLDivElement>(null);
    const createScene = useEffectEvent(scene);
    const styleCanvas = useEffectEvent((canvas: HTMLCanvasElement) => {
        canvas.className = `absolute inset-0 size-full ${canvasClassName}`;
    });

    useEffect(() => {
        const host = hostRef.current;

        if (!host) {
            return;
        }

        const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        let cancelled = false;
        let started = false;
        let teardown: (() => void) | undefined;
        let sync = () => {};

        async function start(host: HTMLDivElement) {
            const [pixi] = await Promise.all([
                import('pixi.js'),
                import('pixi.js/unsafe-eval'),
            ]);

            if (cancelled) {
                return;
            }

            const app = new pixi.Application<WebGLRenderer>();

            try {
                await app.init({
                    width: 1,
                    height: 1,
                    preference: ['webgl'],
                    backgroundAlpha: 0,
                    autoStart: false,
                    powerPreference: 'low-power',
                    eventMode: interactive ? 'static' : 'none',
                });
            } catch {
                app.stage.destroy({ children: true });

                return;
            }

            teardown = () =>
                app.destroy({ removeView: true }, { children: true });

            if (cancelled || app.renderer.context.webGLVersion !== 2) {
                teardown();
                teardown = undefined;

                return;
            }

            const { canvas, stage } = app;
            const game = createScene({ pixi, canvas, stage });
            let resizeFrame = 0;
            let contextLost = false;
            let width = 0;
            let height = 0;
            const resize = new ResizeObserver(([{ contentRect }]) => {
                cancelAnimationFrame(resizeFrame);
                // Resizing the canvas inside the observer can trigger its own layout cycle.
                resizeFrame = requestAnimationFrame(() => {
                    width = contentRect.width;
                    height = contentRect.height;

                    if (width > 0 && height > 0 && !contextLost) {
                        resizeScene();
                    }

                    sync();
                });
            });

            function resizeScene() {
                const resolution = Math.min(
                    window.devicePixelRatio || 1,
                    1.5,
                    // Pixi rounds both backing dimensions to whole pixels.
                    Math.sqrt(
                        Math.min(pixelBudget, maxPixels) / (width * height),
                    ) -
                        0.5 / Math.min(width, height),
                );
                app.renderer.resize(width, height, resolution);
                game.resize?.(width, height);

                // Resizing clears the buffer. Paint before showing the canvas.
                game.update?.(0, motion.matches);
                app.render();
            }

            function loseContext() {
                contextLost = true;
                sync();
            }

            function restoreContext() {
                contextLost = false;

                if (width > 0 && height > 0) {
                    resizeScene();
                }

                sync();
            }

            sync = () => {
                canvas.hidden =
                    (motion.matches && reducedMotion === 'fallback') ||
                    contextLost ||
                    width === 0 ||
                    height === 0;
                host.dataset.graphics = canvas.hidden ? 'fallback' : 'webgl2';

                if (canvas.hidden || document.hidden) {
                    app.stop();
                } else {
                    app.start();
                }
            };
            teardown = () => {
                resize.disconnect();
                cancelAnimationFrame(resizeFrame);
                document.removeEventListener('visibilitychange', sync);
                canvas.removeEventListener('webglcontextlost', loseContext);
                canvas.removeEventListener(
                    'webglcontextrestored',
                    restoreContext,
                );
                app.destroy({ removeView: true }, { children: true });
                game.dispose?.();
                host.dataset.graphics = 'fallback';
            };

            styleCanvas(canvas);
            canvas.hidden = true;
            host.appendChild(canvas);
            resize.observe(host);
            canvas.addEventListener('webglcontextlost', loseContext);
            canvas.addEventListener('webglcontextrestored', restoreContext);
            document.addEventListener('visibilitychange', sync);
            app.ticker.add(({ deltaMS }) =>
                game.update?.(Math.min(0.05, deltaMS / 1000), motion.matches),
            );
        }

        const applyMotion = () => {
            if (!started && (reducedMotion === 'render' || !motion.matches)) {
                started = true;
                void start(host).catch(() => {
                    teardown?.();
                    teardown = undefined;
                });
            }

            sync();
        };

        motion.addEventListener('change', applyMotion);
        applyMotion();

        return () => {
            cancelled = true;
            motion.removeEventListener('change', applyMotion);

            // An in-flight init disposes its renderer once ready.
            teardown?.();
        };
    }, [interactive, pixelBudget, reducedMotion]);

    return (
        <div
            ref={hostRef}
            aria-hidden={!interactive}
            data-graphics="fallback"
            {...props}
        />
    );
}
