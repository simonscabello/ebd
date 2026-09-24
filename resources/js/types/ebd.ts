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

export type Audience = 'teacher' | 'student';

export type LessonMaterial = {
    id: number;
    type: MaterialTypeValue;
    type_label: string;
    audience: Audience;
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

export type LessonBlockKind =
    | 'roteiro'
    | 'extra_time'
    | 'accuracy_note'
    | 'teacher_note'
    | 'context'
    | 'theology'
    | 'curiosity'
    | 'application'
    | 'concept';

export type LessonBlock = {
    id: number;
    kind: LessonBlockKind;
    kind_label: string;
    audience: Audience;
    title: string | null;
    display_title: string;
    body_html: string | null;
    body?: string;
    drip_weekday: number | null;
    drip_weekday_label: string | null;
    position: number;
};

export type MeetingStatus = 'planned' | 'held' | 'cancelled';

export type ClassMeeting = {
    id: number;
    held_on: string;
    date_label: string;
    date_short: string;
    days_until: number;
    status: MeetingStatus;
    status_label: string;
    title: string | null;
    lesson_id: number | null;
    lesson?: {
        id: number;
        title: string;
        display_title: string;
        number: number | null;
        slug: string;
        status: LessonStatus;
    } | null;
    has_attendance: boolean;
    visitors_count: number;
    notes?: string | null;
};

export type LessonStatus = 'draft' | 'published';

export type Lesson = {
    id: number;
    title: string;
    number: number | null;
    display_title: string;
    slug: string;
    url: string;
    summary: string | null;
    scheduled_for: string | null;
    date_label: string | null;
    date_short: string | null;
    days_until: number | null;
    date_parts: { day: string; month: string } | null;
    bible_reference: string | null;
    key_verse: string | null;
    goal: string | null;
    status: LessonStatus;
    status_label: string;
    visibility: 'public' | 'members';
    is_public: boolean;
    classroom?: Classroom;
    series?: Series | null;
    authors?: string[];
    materials?: LessonMaterial[];
    readings?: LessonReading[];
    blocks?: LessonBlock[];
    teacher_blocks?: LessonBlock[];
    meetings?: ClassMeeting[];
    materials_count?: number;
    content_html?: string | null;
    topics?: string[];
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
