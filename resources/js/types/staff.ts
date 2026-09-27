export type StaffStatus = 'active' | 'left';
export type StaffRole = 'school_admin' | 'accountant' | 'teacher';

export interface Staff {
    id: number;
    employee_code: string;
    name_en: string;
    name_bn: string | null;
    designation: string | null;
    phone: string | null;
    email: string | null;
    joining_date: string | null;
    status: StaffStatus;
    user_id: number | null;
}

export interface StaffRow extends Staff {
    user: { id: number; roles: { id: number; name: StaffRole }[] } | null;
}

export type StaffOption = Pick<Staff, 'id' | 'name_en' | 'name_bn' | 'designation'>;
