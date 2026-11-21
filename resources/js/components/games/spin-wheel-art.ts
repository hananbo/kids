export type WheelSegment = {
    kind: 'points' | 'bankrupt' | 'lose-turn';
    label: string;
};

export const wheelSize = 400;
export const wheelCenterY = 206;
export const fullTurn = Math.PI * 2;

const colors = {
    teal: ['#28756c', '#103732'],
    deepTeal: ['#24605d', '#112d2b'],
    ivory: ['#f2dbac', '#af8751'],
    plum: ['#714c66', '#392639'],
    rose: ['#8a494f', '#48252d'],
    blue: ['#3e5267', '#222d40'],
} as const;

const pointColors = [
    colors.teal,
    colors.ivory,
    colors.teal,
    colors.plum,
    colors.deepTeal,
    colors.teal,
    colors.ivory,
    colors.teal,
];

function layer(paint: (context: CanvasRenderingContext2D) => void) {
    const canvas = document.createElement('canvas');
    canvas.width = wheelSize * 2;
    canvas.height = wheelSize * 2;
    const context = canvas.getContext('2d');

    if (context) {
        context.scale(2, 2);
        context.translate(wheelSize / 2, wheelCenterY);
        paint(context);
    }

    return canvas;
}

function disc(
    context: CanvasRenderingContext2D,
    radius: number,
    fill: string | CanvasGradient,
) {
    context.beginPath();
    context.arc(0, 0, radius, 0, fullTurn);
    context.fillStyle = fill;
    context.fill();
}

function brass(context: CanvasRenderingContext2D, radius: number) {
    const fill = context.createLinearGradient(-radius, -radius, radius, radius);
    fill.addColorStop(0, '#6f401f');
    fill.addColorStop(0.23, '#d5a360');
    fill.addColorStop(0.36, '#fff0bf');
    fill.addColorStop(0.49, '#b77839');
    fill.addColorStop(0.62, '#80431f');
    fill.addColorStop(0.82, '#e5b774');
    fill.addColorStop(1, '#6f401f');

    return fill;
}

function paintFace(
    context: CanvasRenderingContext2D,
    segments: WheelSegment[],
) {
    context.save();
    context.shadowColor = '#0b0706';
    context.shadowBlur = 15;
    context.shadowOffsetY = 3;
    disc(context, 177, '#382016');
    context.restore();

    disc(context, 176, brass(context, 175));
    disc(context, 172, '#50301d');
    disc(context, 170, brass(context, -165));
    disc(context, 165, '#3c2b22');
    disc(context, 163, '#e8be7d');

    const wedge = fullTurn / segments.length;

    segments.forEach(({ kind, label }, index) => {
        const angle = -Math.PI / 2 + wedge * index;
        const start = angle - wedge / 2;
        const end = angle + wedge / 2;
        const palette =
            kind === 'bankrupt'
                ? colors.rose
                : kind === 'lose-turn'
                  ? colors.blue
                  : pointColors[index % pointColors.length];
        const fill = context.createRadialGradient(0, 0, 24, 0, 0, 162);
        fill.addColorStop(0, palette[1]);
        fill.addColorStop(0.72, palette[0]);
        fill.addColorStop(1, palette[1]);

        context.beginPath();
        context.moveTo(0, 0);
        context.arc(0, 0, 161, start, end);
        context.closePath();
        context.fillStyle = fill;
        context.fill();
        context.strokeStyle = '#e3ba79';
        context.lineWidth = 1.1;
        context.stroke();

        context.beginPath();
        context.arc(0, 0, 147, start + 0.045, end - 0.045);
        context.strokeStyle = '#f7ce9070';
        context.stroke();

        context.save();
        context.rotate(angle + Math.PI / 2);

        // The top wedge reads upright, including after the wheel lands.
        context.font =
            kind === 'points' ? 'bold 31px Georgia' : 'bold 24px Arial';
        context.direction = kind === 'points' ? 'ltr' : 'rtl';
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.fillStyle = palette === colors.ivory ? '#332316' : '#fff1cf';
        context.fillText(label, 0, -113, 85);

        context.beginPath();
        context.moveTo(0, -76);
        context.lineTo(4, -70);
        context.lineTo(0, -64);
        context.lineTo(-4, -70);
        context.closePath();
        context.fillStyle = '#f4d0979e';
        context.fill();
        context.restore();

        const pegX = Math.cos(start) * 168;
        const pegY = Math.sin(start) * 168;
        context.save();
        context.translate(pegX, pegY);
        disc(context, 3.2, '#342019');
        disc(context, 2.35, '#ffe4aa');
        context.restore();
    });
}

function paintHub(context: CanvasRenderingContext2D) {
    context.save();
    context.shadowColor = '#1a130b';
    context.shadowBlur = 9;
    context.shadowOffsetY = 3;
    disc(context, 42, '#57331f');
    context.restore();

    disc(context, 41, brass(context, 41));
    disc(context, 36.5, '#52331f');
    disc(context, 34.5, '#e9bd7a');
    const enamel = context.createLinearGradient(-24, -24, 26, 31);
    enamel.addColorStop(0, '#347d70');
    enamel.addColorStop(0.55, '#194842');
    enamel.addColorStop(1, '#162b29');
    disc(context, 33, enamel);

    context.beginPath();

    for (let point = 0; point < 8; point += 1) {
        const angle = -Math.PI / 2 + (point * Math.PI) / 4;
        const radius = point % 2 === 0 ? 20 : 5;
        context.lineTo(Math.cos(angle) * radius, Math.sin(angle) * radius);
    }

    context.closePath();
    context.fillStyle = brass(context, 20);
    context.fill();
    disc(context, 3, '#fff0bf');
}

function paintPointer(context: CanvasRenderingContext2D) {
    context.translate(0, -183);
    const fill = context.createLinearGradient(-17, 0, 18, 28);
    fill.addColorStop(0, '#9d5d2e');
    fill.addColorStop(0.38, '#ffe3a5');
    fill.addColorStop(1, '#bb793b');

    context.beginPath();
    context.moveTo(0, -11);
    context.lineTo(16, -4);
    context.quadraticCurveTo(19, -2, 17, 6);
    context.lineTo(0, 37);
    context.lineTo(-17, 6);
    context.quadraticCurveTo(-19, -2, -16, -4);
    context.closePath();
    context.lineJoin = 'round';
    context.lineWidth = 2;
    context.strokeStyle = '#442513';
    context.stroke();
    context.fillStyle = fill;
    context.fill();

    disc(context, 5.3, '#8c5029');
    disc(context, 3.5, '#f9d79a');
}

export function createWheelArt(segments: WheelSegment[]) {
    return {
        face: layer((context) => paintFace(context, segments)),
        hub: layer(paintHub),
        pointer: layer(paintPointer),
    };
}
