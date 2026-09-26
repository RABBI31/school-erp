export interface AcademicYear {
    id: number;
    name: string;
    starts_on: string;
    ends_on: string;
    is_current: boolean;
}

export interface NamedRecord {
    id: number;
    name: string;
}

export interface SchoolClass {
    id: number;
    name_en: string;
    name_bn: string;
    numeric_order: number;
    has_groups: boolean;
}

export type ClassOption = Pick<SchoolClass, 'id' | 'name_en' | 'name_bn'> & Partial<Pick<SchoolClass, 'has_groups'>>;

export interface Section {
    id: number;
    school_class_id: number;
    shift_id: number;
    version_id: number;
    name: string;
    capacity: number | null;
    school_class: ClassOption;
    shift: NamedRecord;
    version: NamedRecord;
}

export interface Subject {
    id: number;
    school_class_id: number;
    group_id: number | null;
    name_en: string;
    name_bn: string;
    code: string;
    combined_key: string | null;
    is_optional_allowed: boolean;
    display_order: number;
    school_class: ClassOption;
    group: NamedRecord | null;
}
