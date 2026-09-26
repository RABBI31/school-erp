import type { NamedRecord } from '@/types/setup';

export type Gender = 'male' | 'female' | 'other';
export type StudentStatus = 'active' | 'left' | 'transferred' | 'graduated';
export type GuardianRelation = 'father' | 'mother' | 'other';

interface LocalizedName {
    name_en: string;
    name_bn: string;
}

export interface Student {
    id: number;
    student_uid: string;
    name_en: string;
    name_bn: string | null;
    date_of_birth: string | null;
    gender: Gender;
    blood_group: string | null;
    religion: string | null;
    father_name: string | null;
    mother_name: string | null;
    present_address: string | null;
    permanent_address: string | null;
    birth_reg_no: string | null;
    admission_date: string;
    status: StudentStatus;
}

export interface Guardian {
    id: number;
    name_en: string;
    name_bn: string | null;
    relation: GuardianRelation;
    phone: string;
    alt_phone: string | null;
    occupation: string | null;
    nid: string | null;
    pivot?: { is_primary: boolean };
    students: Pick<Student, 'id' | 'name_en' | 'name_bn' | 'student_uid'>[];
}

export type GuardianMatch = Pick<Guardian, 'id' | 'name_en' | 'name_bn' | 'relation' | 'phone' | 'students'>;

export interface Enrollment {
    id: number;
    student_id: number;
    academic_year_id: number;
    section_id: number;
    group_id: number | null;
    roll: number;
    optional_subject_id: number | null;
    status: string;
}

export interface EnrollmentWithRelations extends Enrollment {
    academic_year: { id: number; name: string };
    section: { id: number; name: string; school_class: LocalizedName & { id: number } };
    group: NamedRecord | null;
    optional_subject: (LocalizedName & { id: number }) | null;
}

export interface StudentProfile extends Student {
    guardians: Guardian[];
    enrollments: EnrollmentWithRelations[];
}

/** A row of the student list: an enrollment with its student. */
export interface StudentRow extends Enrollment {
    student: Pick<Student, 'id' | 'student_uid' | 'name_en' | 'name_bn' | 'gender' | 'status'>;
    section: { id: number; name: string; school_class: LocalizedName & { id: number } };
    group: NamedRecord | null;
}

export interface SectionOption {
    id: number;
    name: string;
    school_class_id: number;
    class_name_en: string;
    class_name_bn: string;
    has_groups: boolean;
    shift: string;
    version: string;
}

export interface OptionalSubjectOption extends LocalizedName {
    id: number;
    school_class_id: number;
    group_id: number | null;
}

export interface StudentFormOptions {
    academicYearOptions: NamedRecord[];
    sections: SectionOption[];
    groups: NamedRecord[];
    optionalSubjects: OptionalSubjectOption[];
    genders: Gender[];
    religions: string[];
    bloodGroups: string[];
    relations: GuardianRelation[];
}
