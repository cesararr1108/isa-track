/** Tipos de los datos que el servidor envía a las páginas (props de Inertia). */
export interface AuthUser {
    id: string;
    name: string;
    email: string;
    role: string;
    role_label: string;
    permissions: string[];
    carrier: { name: string; photo_url: string | null } | null;
}

export interface NavItem {
    key: string;
    label: string;
    icon: string;
    href: string | null;
    active: boolean;
}

export interface SharedProps {
    [key: string]: unknown;
    app: { name: string };
    auth: { user: AuthUser | null };
    company: { id: string; name: string } | null;
    navigation: { group: string; items: NavItem[] }[];
    flash: { success: string | null };
    errors: Record<string, string>;
    map: { style: string; maptilerKey: string | null };
}

export type VehicleType = 'motorcycle' | 'bicycle' | 'car' | 'van' | 'truck' | 'on_foot';

export interface Carrier {
    id: string;
    name: string;
    type: 'own_driver' | 'external_company';
    document_number: string | null;
    phone: string | null;
    email: string | null;
    vehicle_plate: string | null;
    vehicle_type: VehicleType | null;
    capacity_kg: number | null;
    capacity_packages: number | null;
    has_photo: boolean;
    photo_url: string | null;
    user_email: string | null;
    active: boolean;
}

export type LatLng = [number, number];

export interface Zone {
    id: string;
    name: string;
    color: string;
    delivery_fee: number;
    estimated_minutes: number | null;
    active: boolean;
    polygon: LatLng[];
    center: LatLng;
}
