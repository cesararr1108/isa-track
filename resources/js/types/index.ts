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

export type StatusMap = Record<string, { label: string; color: string }>;

export interface CarrierOption {
    id: string;
    name: string;
    vehicle_plate: string | null;
    vehicle_type?: VehicleType | null;
    capacity_kg?: number | null;
    capacity_packages?: number | null;
}

/** Fila del listado de pedidos. */
export interface OrderRow {
    id: string;
    tracking_code: string;
    external_reference: string | null;
    status: string;
    carrier_id: string | null;
    recipient_name: string;
    city: string;
    neighborhood: string | null;
    packages_count: number;
    latitude: number | null;
    longitude: number | null;
    delivery_fee: number | null;
    cash_on_delivery: number | null;
    zone_name: string | null;
    created_at: string;
}

export interface HistoryEntry {
    status: string;
    occurred_at: string;
    note: string | null;
    incident_reason: string | null;
    latitude: number | null;
    longitude: number | null;
}

/** Detalle completo de un pedido. */
export interface OrderDetail {
    id: string;
    tracking_code: string;
    external_reference: string | null;
    status: string;
    carrier_id: string | null;
    promised_at: string | null;
    created_at: string;
    cash_on_delivery: number | null;
    delivery_fee: number | null;
    zone: { id: string; name: string } | null;
    recipient: { name: string; phone: string; email: string | null };
    address: { line: string; city: string; neighborhood: string | null; reference: string | null; latitude: number | null; longitude: number | null };
    packages: { id: string; code: string; description: string; weight_kg: number | null }[];
    history: HistoryEntry[];
    delivery: null | {
        delivered_at: string;
        receiver_name: string | null;
        receiver_document: string | null;
        collected_total: number;
        payments: { method: string; amount: number; reference: string | null }[];
        photo_url: string | null;
        signature_url: string | null;
    };
}

export interface RouteStop {
    sequence: number;
    order_id: string;
    tracking_code: string | null;
    status: string | null;
    recipient_name: string | null;
    address: string | null;
    neighborhood: string | null;
    cash_on_delivery: number | null;
    latitude: number;
    longitude: number;
    weight_kg: number;
    packages: number;
    leg_km: number;
}

export interface DeliveryRoute {
    id: string;
    name: string;
    date: string;
    status: 'planned' | 'in_progress' | 'completed' | 'cancelled';
    carrier: { id: string; name?: string; vehicle_plate?: string | null; vehicle_type?: string | null };
    start: { latitude: number; longitude: number };
    stops_count: number;
    delivered_count: number;
    total_weight_kg: number;
    total_packages: number;
    distance_km: number;
    started_at: string | null;
    stops: RouteStop[];
}

export interface RoutableOrder {
    id: string;
    tracking_code: string;
    status: string;
    recipient_name: string;
    address: string;
    neighborhood: string | null;
    latitude: number;
    longitude: number;
    weight_kg: number;
    packages: number;
    carrier_id: string | null;
}

/** Recaudo del día de un mensajero (lo que lleva cobrado y si ya cerró caja). */
export interface CashSummary {
    carrier_id: string;
    date: string;
    collected: { orders_count: number; totals_by_method: Record<string, number>; total: number; expected_cash: number };
    closing: null | { result: 'balanced' | 'short' | 'over'; cash_received: number; difference: number };
}

/** Qué exige la prueba de entrega (config isa.delivery_proof.require). */
export interface ProofRequired {
    receiver_name: boolean;
    photo: boolean;
    signature: boolean;
    location: boolean;
}
