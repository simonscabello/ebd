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
        meeting_on: string;
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
                    description="A lição começa como rascunho. Depois você adiciona leituras, blocos de aprofundamento e materiais e publica."
                />
                <LessonForm
                    classrooms={classrooms}
                    series={series}
                    visibilities={visibilities}
                    initial={{
                        classroom_id: defaults.classroom_id,
                        series_id: defaults.series_id,
                        number: '',
                        title: '',
                        slug: '',
                        meeting_on: defaults.meeting_on,
                        bible_reference: '',
                        key_verse: '',
                        goal: '',
                        summary: '',
                        content:
                            '## I. \n\n### 1. \n\n## II. \n\n## III. \n\n## Conclusão\n\n',
                        visibility: 'public',
                        author_ids: [],
                    }}
                />
            </Page>
        </>
    );
}
