export type User = {
    id: number;
    name: string;
    first_name: string;
    email: string;
    is_admin: boolean;
    can_access_admin: boolean;
};

export type Auth = {
    user: User | null;
};
