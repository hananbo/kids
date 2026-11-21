import { useCallback, useEffect, useRef } from 'react';
import type { GameScene } from '@/components/games/game-canvas';
import { GameCanvas } from '@/components/games/game-canvas';

export type StudioPhase =
    | 'welcome'
    | 'intermission'
    | 'checkpoint'
    | 'question'
    | 'lock'
    | 'correct'
    | 'incorrect'
    | 'walked'
    | 'won';

const palette = {
    welcome: [0, 0xa46aff, 0x58ceff],
    intermission: [1, 0x9b68ff, 0x44e3ff],
    checkpoint: [2, 0xffc566, 0xffefad],
    question: [3, 0x6d90ff, 0x65e6ff],
    lock: [4, 0xffa731, 0xffe9aa],
    correct: [5, 0x40e893, 0xb6ffbb],
    incorrect: [6, 0xff635e, 0xffb796],
    walked: [7, 0x85c0ff, 0xffdc89],
    won: [8, 0xffc353, 0xfff4b8],
} as const;

const wheelPalette: Record<StudioPhase, readonly [number, number]> = {
    welcome: [0xfab76e, 0x8df2cd],
    intermission: [0x50cbb0, 0xffe5ae],
    checkpoint: [0xffc15b, 0xffefb3],
    question: [0x5bd8bc, 0xc6ffdf],
    lock: [0xff9e46, 0xffe0a2],
    correct: [0x53e89f, 0xdeffc0],
    incorrect: [0xf97962, 0xffbf97],
    walked: [0x8fb7d6, 0xffdb96],
    won: [0xffc758, 0xffffbd],
};

type StudioTheme = 'spark' | 'word-wheel';

const vertex = `#version 300 es
in vec2 aPosition;
out vec2 uv;

void main() {
    gl_Position = vec4(aPosition, 0.0, 1.0);
    uv = vec2(aPosition.x + 1.0, 1.0 - aPosition.y) * 0.5;
}`;

const fragment = `#version 300 es
precision highp float;

in vec2 uv;
uniform float time;
uniform float age;
uniform float aspect;
uniform float phase;
uniform float tension;
uniform float wheel;
uniform vec3 tint;
out vec4 light;

float halo(float d, float width) {
    return exp(-abs(d) / width);
}

float beam(float origin, float sweep, float spread) {
    float depth = max(0.0, uv.y - 0.045);
    float offset = (uv.x - origin - sweep * depth) / (0.006 + spread * depth);
    return exp(-offset * offset * 2.7)
        * smoothstep(0.03, 0.075, uv.y) * (1.0 - smoothstep(0.2, 0.86, depth));
}

void main() {
    vec2 p = (uv - vec2(0.5, 0.44)) * vec2(aspect, 1.0);
    float radius = length(p);
    float angle = atan(p.y, p.x);
    float sweep = sin(time * (0.64 + tension * 0.25));
    float beams = beam(0.078, 0.38 + sweep * 0.26, 0.25)
        + beam(0.922, -0.38 - sweep * 0.26, 0.25)
        + 0.65 * beam(0.29, -0.37 + sin(time * 0.71) * 0.2, 0.15)
        + 0.65 * beam(0.71, 0.37 + sin(time * 0.71 + 1.9) * 0.2, 0.15);
    float intensity = beams * (0.28 + tension * 0.06);
    float rimLight = smoothstep(0.25, 0.52, abs(uv.x - 0.5) + abs(uv.y - 0.44) * 0.45);
    float grid = abs(p.x) + abs(p.y) * 1.35;
    float early = 1.0 - smoothstep(0.2, 1.45, age);

    if (wheel > 0.5) {
        float arcSpeed = phase == 4.0 ? 3.8 : 0.56;
        float orbit = pow(max(0.0, cos(angle * 5.0 - time * arcSpeed)), 4.0);
        float arcs = halo(radius - 0.57, 0.013) + 0.65 * halo(radius - 0.69, 0.02);
        float runner = cos(uv.y * 11.0 - time * arcSpeed + uv.x * 4.0);
        intensity += arcs * orbit * (phase == 4.0 ? 0.63 : 0.27);
        intensity += rimLight * max(0.0, runner) * (phase == 4.0 ? 0.21 : 0.1);
    }

    if (phase <= 2.0 || phase == 8.0) {
        float aperture = mix(abs(p.x) + abs(p.y) * 1.52, radius * 1.25, wheel);
        float gate = 0.15 + 1.2 * min(age, 1.6);
        float rotation = cos(8.0 * angle - time * 1.2);
        float sweepWings = smoothstep(0.28, 0.88, rotation) * halo(radius - 0.46, 0.21);
        float prism = halo(aperture - 0.56, 0.009) + 0.7 * halo(aperture - 0.66, 0.019);
        intensity += (0.51 * halo(aperture - gate, 0.093) + 0.38 * halo(aperture - gate, 0.012)) * early;
        intensity += (0.58 * prism + 0.34 * sweepWings) * (0.77 + 0.23 * sweep);
    } else if (phase == 3.0) {
        float wipe = abs(p.x) + 0.34 * p.y - (age * 2.1 - 0.19);
        intensity += 0.62 * halo(wipe, 0.10) * early + 0.18 * halo(wipe, 0.009) * early;
        float lanes = halo(abs(uv.x - 0.5) - 0.44 + 0.075 * p.y, 0.023);
        intensity += lanes * (0.06 + 0.16 * pow(0.5 + 0.5 * cos(time * 2.1 + p.y * 11.0), 4.0));
    } else if (phase == 4.0) {
        float pair = abs(p.x) + p.y * 0.27;
        intensity += halo(pair - max(0.03, 0.76 - age * 2.9), 0.075) * 0.58;
        intensity += (0.35 + 0.09 * sweep) * rimLight;
    } else if (phase == 5.0 || phase == 7.0) {
        float wave = halo(grid - age * 1.05, 0.075);
        float flare = pow(max(0.0, cos(angle * 7.0 - age * 2.4)), 6.0) * halo(radius - age * 0.64, 0.16);
        intensity += (0.85 * wave + 0.32 * flare) * early + 0.22 * rimLight;
    } else if (phase == 6.0) {
        float downstroke = halo(uv.y - age * 1.3, 0.17);
        intensity += (0.43 * downstroke * early + 0.27 * rimLight) * (1.0 - 0.3 * min(age, 1.0));
    }

    float alpha = min(intensity, 0.78);
    vec3 color = mix(tint, vec3(0.79, 0.89, 1.0), min(0.58, intensity * 0.7));
    light = vec4(color * alpha, alpha);
}`;

function rgb(tint: number) {
    return [(tint >> 16) / 255, ((tint >> 8) & 255) / 255, (tint & 255) / 255];
}

function studioScene(
    read: () => {
        phase: StudioPhase;
        transitionKey: string;
        tension: number;
        theme: StudioTheme;
    },
): GameScene {
    return ({ pixi, stage }) => {
        const geometry = new pixi.Geometry({
            attributes: {
                aPosition: {
                    buffer: new Float32Array([-1, -1, 3, -1, -1, 3]),
                    format: 'float32x2',
                    stride: 8,
                },
            },
        });
        const lightUniforms = new pixi.UniformGroup({
            time: { type: 'f32', value: 0 },
            age: { type: 'f32', value: 0 },
            aspect: { type: 'f32', value: 1 },
            phase: { type: 'f32', value: 0 },
            tension: { type: 'f32', value: 0 },
            wheel: { type: 'f32', value: 0 },
            tint: {
                type: 'vec3<f32>',
                value: new Float32Array(rgb(palette.welcome[1])),
            },
        });
        const { uniforms } = lightUniforms;
        const shader = pixi.Shader.from({
            gl: { vertex, fragment },
            resources: { lightUniforms },
        });
        const mesh = new pixi.Mesh({ geometry, shader });
        stage.addChild(mesh);

        const sparks = Array.from({ length: 58 }, (_, index) => {
            const glyph = new pixi.Graphics()
                .poly([
                    0, -6, 1.6, -1.3, 4, 0, 1.6, 1.3, 0, 6, -1.6, 1.3, -4, 0,
                    -1.6, -1.3,
                ])
                .fill(0xffffff);
            stage.addChild(glyph);

            return {
                glyph,
                turn: index * 2.399963,
                drift: ((index * 29) % 61) / 61,
                offset: ((index * 37) % 67) / 67,
            };
        });
        let width = 1;
        let height = 1;
        let previous = '';

        return {
            resize(nextWidth, nextHeight) {
                width = nextWidth;
                height = nextHeight;
                uniforms.aspect = width / height;
            },
            update(delta) {
                const { phase, transitionKey, tension, theme } = read();
                const [index, baseTint, baseHighlight] = palette[phase];
                const [tint, highlight] =
                    theme === 'word-wheel'
                        ? wheelPalette[phase]
                        : [baseTint, baseHighlight];
                uniforms.wheel = theme === 'word-wheel' ? 1 : 0;
                const changing = previous !== transitionKey;

                if (changing) {
                    previous = transitionKey;
                    uniforms.age = 0;
                    uniforms.phase = index;
                }

                uniforms.time += delta;
                uniforms.age += delta;
                uniforms.tension = tension;

                const [r, g, b] = rgb(tint);
                const fade = Math.min(1, delta * 8);
                uniforms.tint[0] += (r - uniforms.tint[0]) * fade;
                uniforms.tint[1] += (g - uniforms.tint[1]) * fade;
                uniforms.tint[2] += (b - uniforms.tint[2]) * fade;

                const { time, age } = uniforms;
                const burst =
                    phase === 'correct' ||
                    phase === 'checkpoint' ||
                    phase === 'won';
                const radiance = Math.min(width, height);
                const gravity = phase === 'won' ? 0.42 : 0.63;

                for (const [
                    i,
                    { glyph, turn, drift, offset },
                ] of sparks.entries()) {
                    glyph.tint = i % 4 === 0 ? tint : highlight;

                    if (burst) {
                        const flight =
                            phase === 'won'
                                ? age - offset * 0.85
                                : age - drift * 0.38;
                        glyph.visible = flight > 0 && flight < 2.7;

                        if (glyph.visible) {
                            const heading = turn;
                            const travel =
                                flight * (0.22 + offset * 0.47) * radiance;
                            glyph.position.set(
                                width * (i % 2 === 0 ? 0.11 : 0.89) +
                                    Math.cos(heading) * travel,
                                height * (0.46 + drift * 0.2) +
                                    Math.sin(heading) * travel -
                                    flight * radiance * 0.44 +
                                    flight * flight * gravity * radiance * 0.33,
                            );
                            glyph.alpha =
                                (1 - Math.min(1, flight / 2.7)) * 0.95;
                            glyph.scale.set(0.55 + offset * 1.15);
                            glyph.rotation = heading + flight * 2.3;
                        }
                    } else {
                        glyph.visible = i < 22 && phase !== 'incorrect';

                        if (glyph.visible) {
                            const rise =
                                (offset + time * (0.052 + 0.04 * drift)) % 1;
                            const side = i % 2 === 0 ? 0.12 : 0.88;
                            glyph.position.set(
                                width *
                                    (side +
                                        Math.sin(time * 0.6 + turn) * 0.084 +
                                        (drift - 0.5) * 0.15),
                                height * (1.07 - rise),
                            );
                            glyph.alpha =
                                Math.sin(Math.PI * rise) *
                                (0.24 + 0.37 * drift);
                            glyph.scale.set(0.26 + 0.42 * drift);
                            glyph.rotation = time * 0.31 + turn;
                        }
                    }
                }
            },
            dispose() {
                geometry.destroy();
                shader.destroy();
            },
        };
    };
}

export function StudioLights({
    phase,
    transitionKey,
    tension,
    theme = 'spark',
}: {
    phase: StudioPhase;
    transitionKey: string;
    tension: number;
    theme?: StudioTheme;
}) {
    const state = useRef({ phase, transitionKey, tension, theme });
    const scene = useCallback<GameScene>(
        (renderer) => studioScene(() => state.current)(renderer),
        [],
    );

    useEffect(() => {
        state.current = { phase, transitionKey, tension, theme };
    }, [phase, tension, theme, transitionKey]);

    const accent = rgb(
        theme === 'word-wheel' ? wheelPalette[phase][0] : palette[phase][1],
    )
        .map((channel) => `${channel * 100}%`)
        .join(' ');

    return (
        <GameCanvas
            scene={scene}
            pixelBudget={400_000}
            className={`${theme}-studio-effects`}
            canvasClassName={`${theme}-studio-canvas`}
            data-studio-lighting={phase}
            style={{
                background: `radial-gradient(ellipse at 84% 8%, rgb(${accent} / 21%), transparent 60%), radial-gradient(ellipse at 12% 44%, rgb(${accent} / 12%), transparent 42%)`,
            }}
        />
    );
}
