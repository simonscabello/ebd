import { Head } from '@inertiajs/react';
import { LessonForm } from '@/components/admin/lesson-form';
import { Page, PageHeader } from '@/components/page';
import type { Classroom, Option, Series } from '@/types';

type Props = {
    classrooms: Classroom[];
    series: Series[];
    defaults: {
        classroom_id: number | null;
        series_id: number | null;
        scheduled_for: string;
    };
    visibilities: Option[];
};

export default function CreateLesson({
    classrooms,
    series,
    defaults,
    visibilities,
}: Props) {
    return (
        <>
            <Head title="Nova lição" />
            <Page>
                <PageHeader
                    title="Nova lição"
                    description="A lição começa como rascunho. Depois você adiciona leituras, materiais e perguntas e publica."
                />
                <LessonForm
                    classrooms={classrooms}
                    series={series}
                    visibilities={visibilities}
                    initial={{
                        classroom_id: defaults.classroom_id,
                        series_id: defaults.series_id,
                        title: '',
                        slug: '',
                        scheduled_for: defaults.scheduled_for,
                        bible_reference: '',
                        bible_text: '',
                        summary: '',
                        content: '## Introdução\n\n',
                        teacher_notes: '',
                        visibility: 'public',
                        author_ids: [],
                    }}
                />
            </Page>
        </>
    );
}
