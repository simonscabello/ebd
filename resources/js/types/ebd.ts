/**
 * Tipos espelhando os API Resources do Laravel (app/Http/Resources).
 */

export type Classroom = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
};

export type Series = {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    starts_on: string | null;
    ends_on: string | null;
    classroom_id: number;
    classroom?: Classroom;
    lessons_count?: number;
};

export type MaterialTypeValue =
    | 'pdf'
    | 'file'
    | 'link'
    | 'video'
    | 'audio'
    | 'reference';

export type LessonMaterial = {
    id: number;
    type: MaterialTypeValue;
    type_label: string;
    title: string;
    description: string | null;
    url: string | null;
    embed_url: string | null;
    is_primary: boolean;
    position: number;
    file: {
        name: string | null;
        extension: string;
        mime_type: string | null;
        size: string | null;
        open_url: string;
        download_url: string;
    } | null;
};

export type LessonReading = {
    id: number;
    weekday: number | null;
    weekday_label: string | null;
    weekday_short: string | null;
    is_today: boolean;
    reference: string;
    notes: string | null;
    position: number;
};

export type LessonQuestion = {
    id: number;
    body: string;
    position: number;
};

export type LessonStatus = 'draft' | 'published' | 'completed';

export type Lesson = {
    id: number;
    title: string;
    slug: string;
    url: string;
    summary: string | null;
    scheduled_for: string | null;
    date_label: string | null;
    date_short: string | null;
    days_until: number | null;
    date_parts: { day: string; month: string } | null;
    bible_reference: string | null;
    status: LessonStatus;
    status_label: string;
    visibility: 'public' | 'members';
    is_public: boolean;
    classroom?: Classroom;
    series?: Series | null;
    authors?: string[];
    materials?: LessonMaterial[];
    readings?: LessonReading[];
    questions?: LessonQuestion[];
    questions_count?: number;
    materials_count?: number;
    bible_text?: string | null;
    content_html?: string | null;
    topics?: string[];
    teacher_notes_html?: string | null;
    headline?: string | null;
};

export type Option<T = string> = { value: T; label: string };

export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
};
