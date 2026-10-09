export type User = {
    id: number;
    name: string;
    first_name: string;
    email: string | null;
    avatar_url: string | null;
    has_password: boolean;
    is_admin: boolean;
    can_access_admin: boolean;
    is_student: boolean;
    /** Aluno ou professor de ao menos uma classe. */
    is_member: boolean;
};

export type Auth = {
    user: User | null;
};
