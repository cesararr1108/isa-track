export interface PanelUser {
    id: string;
    name: string;
    email: string;
    phone: string | null;
    role: string;
    active: boolean;
    carrier_id: string | null;
    carrier: { id: string; photo_url: string | null } | null;
    extra_permissions: string[];
    revoked_permissions: string[];
    permissions: string[];
}

export interface RoleInfo {
    key: string;
    label: string;
    permissions: string[];
}

export interface AuditEntry {
    action: string;
    subject_type: string | null;
    subject_id: string | null;
    data: Record<string, unknown> | null;
    ip: string | null;
    user_name: string | null;
    at: string;
}
