import { useEffect, useEffectEvent, useRef } from 'react';
import type { GameScene } from '@/components/games/game-canvas';
import { GameCanvas } from '@/components/games/game-canvas';
import type { WheelSegment } from '@/components/games/spin-wheel-art';
import {
    createWheelArt,
    fullTurn,
    wheelCenterY,
    wheelSize,
} from '@/components/games/spin-wheel-art';
import './spin-wheel.css';

export type { WheelSegment } from '@/components/games/spin-wheel-art';

export type SpinOutcome = { id: string | number; segmentIndex: number };

type Props = {
    segments: WheelSegment[];
    outcome: SpinOutcome | null;
    onSettled?: (outcome: SpinOutcome) => void;
    onTick?: () => void;
};

const spinDuration = 2800;

export function SpinWheel(props: Props) {
    return <WheelDisplay key={JSON.stringify(props.segments)} {...props} />;
}

function WheelDisplay({ segments, outcome, onSettled, onTick }: Props) {
    const length = segments.length;
    const step = fullTurn / Math.max(1, length);
    const segmentIndex = outcome?.segmentIndex ?? null;
    const outcomeId = outcome?.id ?? null;
    const root = useRef<HTMLDivElement>(null);
    const fallback = useRef<HTMLCanvasElement>(null);
    const art = useRef<ReturnType<typeof createWheelArt> | null>(null);
    const spin = useRef({
        settledId: outcomeId,
        angle: (segmentIndex ?? 0) * -step,
        tickAt: -Infinity,
        landedAt: -Infinity,
        celebrate: false,
    });

    const drawFallback = useEffectEvent(() => {
        const canvas = fallback.current;
        const context = canvas?.getContext('2d');

        if (!canvas || !context) {
            return;
        }

        const { face, hub, pointer } = (art.current ??=
            createWheelArt(segments));
        const centerX = canvas.width / 2;
        const centerY = wheelCenterY * 2;

        context.clearRect(0, 0, canvas.width, canvas.height);
        context.save();
        context.translate(centerX, centerY);
        context.rotate(spin.current.angle);
        context.drawImage(face, -centerX, -centerY);
        context.restore();
        context.drawImage(hub, 0, 0);
        context.drawImage(pointer, 0, 0);
    });
    const notifyTick = useEffectEvent(() => onTick?.());
    const notifySettled = useEffectEvent((result: SpinOutcome) =>
        onSettled?.(result),
    );
    const isPoints = useEffectEvent(
        (index: number) => segments[index]?.kind === 'points',
    );

    useEffect(() => {
        const host = root.current;
        const state = spin.current;

        if (!host) {
            return;
        }

        if (outcomeId === null || segmentIndex === null) {
            state.settledId = null;
            state.angle = 0;
            state.tickAt = -Infinity;
            state.landedAt = -Infinity;
            delete host.dataset.wheelSegment;
            drawFallback();

            return;
        }

        if (
            !Number.isInteger(segmentIndex) ||
            segmentIndex < 0 ||
            segmentIndex >= length
        ) {
            return;
        }

        if (state.settledId === outcomeId) {
            host.dataset.wheelSegment = String(segmentIndex);
            drawFallback();

            return;
        }

        const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const graphics = host.querySelector('[data-graphics]');
        const canAnimate = () =>
            !motion.matches &&
            graphics?.getAttribute('data-graphics') === 'webgl2';
        const started = performance.now();
        const from = state.angle;
        const target = -step * segmentIndex;
        const distance =
            fullTurn * 5 +
            ((((target - from) % fullTurn) + fullTurn) % fullTurn);
        let crossing = Math.floor(from / step + 0.5);
        let frame = 0;

        const settle = (now: number) => {
            state.angle = target;
            state.settledId = outcomeId;
            state.landedAt = canAnimate() ? now : -Infinity;
            state.celebrate = isPoints(segmentIndex);
            host.dataset.wheelSegment = String(segmentIndex);
            delete host.dataset.wheelSpinning;
            drawFallback();
            notifySettled({ id: outcomeId, segmentIndex });
        };

        function move(now: number) {
            const progress = Math.max(0, (now - started) / spinDuration);

            if (progress >= 1 || !canAnimate()) {
                settle(now);

                return;
            }

            state.angle = from + distance * (1 - (1 - progress) ** 3);
            const nextCrossing = Math.floor(state.angle / step + 0.5);

            if (nextCrossing !== crossing) {
                crossing = nextCrossing;
                state.tickAt = now;
                notifyTick();
            }

            frame = requestAnimationFrame(move);
        }

        if (canAnimate()) {
            host.dataset.wheelSpinning = 'true';
            delete host.dataset.wheelSegment;
            frame = requestAnimationFrame(move);
        } else {
            settle(started);
        }

        return () => {
            cancelAnimationFrame(frame);
            delete host.dataset.wheelSpinning;
        };
    }, [length, outcomeId, segmentIndex, step]);

    const scene: GameScene = ({ pixi, stage }) => {
        const { face, hub, pointer } = (art.current ??=
            createWheelArt(segments));
        const textures = [face, hub, pointer].map((image) =>
            pixi.Texture.from(image, true),
        );
        const [wheel, cap, needle] = textures.map((texture) => {
            const sprite = new pixi.Sprite(texture);
            sprite.scale.set(0.5);
            sprite.pivot.set(wheelSize, wheelCenterY * 2);

            return sprite;
        });

        const assembly = new pixi.Container();
        const halo = new pixi.Graphics()
            .circle(0, 0, 174)
            .stroke({ color: 0xffe0a0, width: 4 });
        const selectedWedge = new pixi.Graphics()
            .moveTo(0, 0)
            .arc(0, 0, 160, (-Math.PI - step) / 2, (-Math.PI + step) / 2)
            .closePath()
            .fill({ color: 0xffe6ac, alpha: 0.2 });
        const sparks = new pixi.Container();
        const flecks = Array.from({ length: 22 }, (_, index) =>
            sparks.addChild(
                new pixi.Graphics()
                    .poly([0, -5, 2, 0, 0, 5, -2, 0])
                    .fill(index % 4 === 0 ? 0xc8f5e0 : 0xffd78f),
            ),
        );

        needle.pivot.y -= 183 * 2;
        needle.y = -183;
        halo.alpha = 0;
        selectedWedge.alpha = 0;
        sparks.visible = false;
        assembly.addChild(wheel, selectedWedge, halo, cap, needle, sparks);
        stage.addChild(assembly);

        return {
            resize(width, height) {
                const scale = Math.min(width, height) / wheelSize;
                assembly.scale.set(scale);
                assembly.position.set(
                    width / 2,
                    height / 2 + (wheelCenterY - wheelSize / 2) * scale,
                );
            },
            update() {
                const { angle, tickAt, landedAt, celebrate } = spin.current;
                const now = performance.now();
                const age = (now - landedAt) / 760;

                wheel.rotation = angle;
                const kick = Math.max(0, 1 - (now - tickAt) / 75);
                needle.rotation = -0.22 * kick * kick;
                halo.alpha = Math.max(0, 1 - age) * (celebrate ? 0.95 : 0.45);
                selectedWedge.alpha = Math.max(0, 1 - age);
                sparks.visible = celebrate && age < 1;

                if (sparks.visible) {
                    flecks.forEach((spark, index) => {
                        const direction =
                            (index / flecks.length) * fullTurn + 0.14;
                        const radius = 154 + age * (23 + (index % 5) * 5);
                        spark.position.set(
                            Math.cos(direction) * radius,
                            Math.sin(direction) * radius + age * age * 13,
                        );
                        spark.rotation = direction + age * 2;
                        spark.alpha = 1 - age;
                    });
                }
            },
            dispose() {
                textures.forEach((texture) => texture.destroy(true));
            },
        };
    };

    return (
        <div
            ref={root}
            className="spin-wheel"
            data-test="spin-wheel"
            aria-hidden="true"
        >
            <GameCanvas scene={scene} pixelBudget={420_000} />
            <canvas
                ref={fallback}
                width={wheelSize * 2}
                height={wheelSize * 2}
            />
        </div>
    );
}
