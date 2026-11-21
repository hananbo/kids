import { useEffect, useEffectEvent, useRef, useState } from 'react';

const mutePreference = 'hebrew-quiz-muted';
const mediaEvents = ['play', 'pause', 'ended', 'emptied', 'error'];
const chords = [
    [38, 50, 53, 59, 64],
    [38, 48, 55, 58, 65],
    [36, 48, 51, 57, 62],
    [33, 45, 52, 55, 61],
];

type CueNote = [offset: number, pitch: number, duration: number];
const cues = {
    start: [
        [0, 38, 0.65],
        [0.08, 57, 0.32],
        [0.23, 64, 0.42],
        [0.4, 65, 0.65],
    ],
    next: [
        [0, 52, 0.24],
        [0.11, 64, 0.28],
    ],
    lock: [
        [0, 33, 0.34],
        [0.035, 52, 0.31],
        [0.19, 58, 0.22],
    ],
    correct: [
        [0, 50, 0.52],
        [0, 66, 0.44],
        [0.13, 69, 0.42],
        [0.32, 76, 0.64],
    ],
    incorrect: [
        [0, 46, 0.54],
        [0, 65, 0.34],
        [0.22, 60, 0.53],
    ],
    level: [
        [0, 43, 0.85],
        [0.08, 55, 0.25],
        [0.24, 62, 0.32],
        [0.35, 69, 0.67],
        [0.52, 74, 0.8],
    ],
    complete: [
        [0, 38, 1.05],
        [0.08, 62, 0.32],
        [0.26, 66, 0.42],
        [0.4, 73, 0.45],
        [0.68, 50, 1],
        [0.68, 69, 1],
        [0.68, 78, 1.2],
    ],
    exit: [
        [0, 50, 0.72],
        [0.11, 65, 0.43],
        [0.3, 62, 0.72],
    ],
} satisfies Record<string, CueNote[]>;

type Cue = keyof typeof cues;

type QuizAudio = {
    context: AudioContext;
    output: GainNode;
    bed: GainNode;
    playingMedia: HTMLMediaElement[];
};

function frequency(pitch: number) {
    return 440 * 2 ** ((pitch - 69) / 12);
}

function tone(
    bus: GainNode,
    pitch: number,
    at: number,
    duration: number,
    volume: number,
    {
        attack = 0.015,
        wave = 'triangle',
        endPitch = pitch,
    }: { attack?: number; wave?: OscillatorType; endPitch?: number } = {},
) {
    const voice = bus.context.createOscillator();
    const gain = bus.context.createGain();
    const end = at + duration;

    voice.type = wave;
    voice.frequency.setValueAtTime(frequency(pitch), at);
    voice.frequency.exponentialRampToValueAtTime(frequency(endPitch), end);
    gain.gain.setValueAtTime(0.0001, at);
    gain.gain.exponentialRampToValueAtTime(volume, at + attack);
    gain.gain.exponentialRampToValueAtTime(0.0001, end);
    voice.connect(gain).connect(bus);
    voice.onended = () => {
        voice.disconnect();
        gain.disconnect();
    };
    voice.start(at);
    voice.stop(end);
}

function scoreBeat(
    bed: GainNode,
    beat: number,
    at: number,
    step: number,
    tension: number,
) {
    const bar = Math.floor(beat / 8) % chords.length;
    const [bass, ...chord] = chords[bar];
    const position = beat % 8;

    if (position === 0) {
        chord.forEach((pitch, index) => {
            tone(bed, pitch, at + index * 0.045, step * 8.6, 0.046, {
                attack: 0.6,
                wave: index % 2 === 0 ? 'sine' : 'triangle',
            });
        });
    }

    if (position === 0 || position === 3 || position === 6) {
        tone(bed, 47, at, 0.19, 0.17 + tension * 0.05, {
            wave: 'sine',
            endPitch: bass - 2,
        });
        tone(bed, bass + 12, at + 0.03, step * 1.6, 0.095);
    }

    if (
        position === 1 ||
        position === 4 ||
        position === 7 ||
        (tension > 0.45 && position === 5)
    ) {
        const pitch = chord[(position + bar) % chord.length] + 12;
        tone(bed, pitch, at, step * 1.4, 0.045 + tension * 0.035);
        tone(bed, pitch, at + step * 0.62, step * 1.75, 0.016, {
            wave: 'sine',
        });
    }
}

function createAudio(): QuizAudio {
    const context = new AudioContext();
    const output = context.createGain();
    const bed = context.createGain();

    bed.gain.value = 0;
    bed.connect(output).connect(context.destination);

    return { context, output, bed, playingMedia: [] };
}

function syncMedia(audio: QuizAudio, scope: HTMLElement | null) {
    for (const media of audio.playingMedia) {
        if (!scope?.contains(media)) {
            media.pause();
        }
    }

    const wasPlaying = audio.playingMedia.length > 0;
    audio.playingMedia = Array.from(
        scope?.querySelectorAll<HTMLMediaElement>('audio, video') ?? [],
    ).filter((element) => !element.paused && !element.ended && !element.error);
    const playing = audio.playingMedia.length > 0;

    if (playing !== wasPlaying) {
        audio.output.gain.setValueAtTime(
            playing ? 0 : 1,
            audio.context.currentTime,
        );
    }

    return playing;
}

export function useQuizSound({
    suspense,
    tension,
}: {
    suspense: boolean;
    tension: number;
}) {
    const soundScope = useRef<HTMLElement>(null);
    const engine = useRef<QuizAudio | null>(null);
    const muted = useRef(false);
    const [soundEnabled, setSoundEnabled] = useState(false);

    function unlockSound() {
        if (muted.current || typeof AudioContext === 'undefined') {
            return;
        }

        const audio = (engine.current ??= createAudio());
        syncMedia(audio, soundScope.current);

        if (audio.context.state === 'running') {
            setSoundEnabled(true);

            return;
        }

        void audio.context
            .resume()
            .then(() => {
                if (engine.current === audio) {
                    setSoundEnabled(true);
                }
            })
            .catch(() => {});
    }

    async function playCue(cue: Cue) {
        const audio = engine.current;

        if (!audio || syncMedia(audio, soundScope.current)) {
            return;
        }

        if (audio.context.state !== 'running') {
            await audio.context.resume().catch(() => {});
        }

        if (
            engine.current !== audio ||
            audio.context.state !== 'running' ||
            syncMedia(audio, soundScope.current)
        ) {
            return;
        }

        const start = audio.context.currentTime + 0.01;
        cues[cue].forEach(([offset, pitch, duration]) => {
            tone(audio.output, pitch, start + offset, duration, 0.13);
        });
    }

    function toggleSound() {
        muted.current = engine.current !== null;

        try {
            sessionStorage.setItem(mutePreference, muted.current ? '1' : '0');
        } catch {
            // Playback still works if storage is unavailable.
        }

        if (!muted.current) {
            unlockSound();
            void playCue('next');

            return;
        }

        const audio = engine.current;
        engine.current = null;
        setSoundEnabled(false);
        void audio?.context.close().catch(() => {});
    }

    const unlockOnGesture = useEffectEvent((event: Event) => {
        if (
            event.isTrusted &&
            event.target instanceof Element &&
            !event.target.closest('[data-test="quiz-sound"], audio, video')
        ) {
            unlockSound();
        }
    });

    useEffect(() => {
        const scope = soundScope.current;

        try {
            muted.current = sessionStorage.getItem(mutePreference) === '1';
        } catch {
            // Playback still works if storage is unavailable.
        }

        if (!scope) {
            return;
        }

        const gesture = (event: Event) => unlockOnGesture(event);
        const mediaChanged = () => {
            if (engine.current) {
                syncMedia(engine.current, scope);
            }
        };
        const observer = new MutationObserver(() => {
            if (engine.current?.playingMedia.length) {
                mediaChanged();
            }
        });

        observer.observe(scope, { childList: true, subtree: true });
        scope.addEventListener('click', gesture, true);
        scope.addEventListener('keydown', gesture, true);
        mediaEvents.forEach((event) =>
            scope.addEventListener(event, mediaChanged, true),
        );

        return () => {
            observer.disconnect();
            scope.removeEventListener('click', gesture, true);
            scope.removeEventListener('keydown', gesture, true);
            mediaEvents.forEach((event) =>
                scope.removeEventListener(event, mediaChanged, true),
            );
            void engine.current?.context.close().catch(() => {});
            engine.current = null;
        };
    }, []);

    const currentTension = useEffectEvent(() => tension);

    useEffect(() => {
        const audio = engine.current;

        if (!suspense || !soundEnabled || !audio) {
            return;
        }

        const { context, bed } = audio;
        let nextAt = context.currentTime + 0.025;
        let beat = 0;

        function schedule() {
            if (context.state !== 'running') {
                return;
            }

            if (nextAt < context.currentTime) {
                nextAt = context.currentTime + 0.025;
            }

            if (nextAt < context.currentTime + 0.12) {
                const tension = currentTension();
                const step = 0.29 - tension * 0.085;
                scoreBeat(bed, beat, nextAt, step, tension);
                beat += 1;
                nextAt += step;
            }
        }

        bed.gain.setTargetAtTime(1, context.currentTime, 0.08);
        schedule();
        const timer = window.setInterval(schedule, 45);

        return () => {
            window.clearInterval(timer);

            if (context.state !== 'closed') {
                bed.gain.setTargetAtTime(0, context.currentTime, 0.045);
            }
        };
    }, [suspense, soundEnabled]);

    return { soundEnabled, toggleSound, playCue, unlockSound, soundScope };
}
