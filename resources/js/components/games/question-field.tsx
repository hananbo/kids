export type TextField = {
    type: 'text' | 'number';
    value: string;
};

type MediaField = {
    type: 'image' | 'audio' | 'video';
    url: string;
    alternative: string;
};

export type QuizField = TextField | MediaField;

const math =
    /(\(?[+-]?\d+(?:[.,]\d+)?(?:\s*[+\-−×*÷/=<>≤≥^]\s*[+-]?\d+(?:[.,]\d+)?)+\)?)/gu;

export function BidiText({ value }: { value: string }) {
    return value.split(math).map((part, index) =>
        index % 2 === 0 ? (
            part
        ) : (
            <bdi key={index} dir="ltr">
                {part}
            </bdi>
        ),
    );
}

export function isMedia(field: QuizField): field is MediaField {
    return 'url' in field;
}

export function QuestionField({ field }: { field: QuizField }) {
    switch (field.type) {
        case 'text':
            return <BidiText value={field.value} />;
        case 'number':
            return <bdi dir="ltr">{field.value}</bdi>;
        case 'image':
            return (
                <img
                    src={field.url}
                    alt={field.alternative}
                    loading="lazy"
                    className="max-h-64 w-full rounded-lg object-contain"
                />
            );
        case 'audio':
        case 'video': {
            const Player = field.type === 'audio' ? 'audio' : 'video';

            return (
                <figure key={field.url} className="grid min-w-0 gap-2">
                    <Player
                        src={field.url}
                        controls
                        preload="none"
                        aria-label={field.alternative}
                        className="max-h-64 w-full rounded-lg"
                    />
                    <figcaption>
                        <BidiText value={field.alternative} />
                    </figcaption>
                </figure>
            );
        }
    }
}
