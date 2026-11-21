import { useEffect, useRef, useState } from 'react';

function tone(
    audio: AudioContext,
    frequency: number,
    start: number,
    length: number,
    volume: number,
) {
    const oscillator = audio.createOscillator();
    const envelope = audio.createGain();
    oscillator.type = 'triangle';
    oscillator.frequency.value = frequency;
    envelope.gain.setValueAtTime(0.001, start);
    envelope.gain.exponentialRampToValueAtTime(volume, start + 0.005);
    envelope.gain.exponentialRampToValueAtTime(0.001, start + length);
    oscillator.connect(envelope).connect(audio.destination);
    oscillator.start(start);
    oscillator.stop(start + length);
}

export function useWheelSound() {
    const audio = useRef<AudioContext | null>(null);
    const lastTick = useRef(-Infinity);
    const [soundEnabled, setSoundEnabled] = useState(false);

    useEffect(
        () => () => {
            void audio.current?.close().catch(() => {});
            audio.current = null;
        },
        [],
    );

    function toggleSound() {
        if (audio.current) {
            void audio.current.close().catch(() => {});
            audio.current = null;
            setSoundEnabled(false);

            return;
        }

        if (typeof AudioContext === 'undefined') {
            return;
        }

        const context = new AudioContext();
        audio.current = context;
        lastTick.current = -Infinity;
        setSoundEnabled(true);
        void context.resume().catch(() => {
            if (audio.current === context) {
                audio.current = null;
                setSoundEnabled(false);
                void context.close().catch(() => {});
            }
        });
    }

    function tick() {
        const context = audio.current;

        if (
            context?.state !== 'running' ||
            context.currentTime - lastTick.current < 0.036
        ) {
            return;
        }

        lastTick.current = context.currentTime;
        tone(context, 970, context.currentTime, 0.038, 0.045);
    }

    function land() {
        const context = audio.current;

        if (context?.state === 'running') {
            tone(context, 392, context.currentTime, 0.18, 0.075);
            tone(context, 587.3, context.currentTime + 0.06, 0.23, 0.055);
        }
    }

    function solved() {
        const context = audio.current;

        if (context?.state === 'running') {
            const start = context.currentTime;
            tone(context, 523.25, start, 0.18, 0.07);
            tone(context, 659.25, start + 0.105, 0.22, 0.06);
            tone(context, 783.99, start + 0.21, 0.24, 0.055);
            tone(context, 1046.5, start + 0.33, 0.38, 0.045);
        }
    }

    return { soundEnabled, toggleSound, tick, land, solved };
}
