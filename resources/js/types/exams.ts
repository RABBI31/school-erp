export type ExamStatus = 'draft' | 'marks_entry' | 'published';
export type MarkPart = 'cq' | 'mcq' | 'practical';

/** Marks and points arrive as decimal strings, e.g. "85.00". */
export type Decimal = string;

export interface ExamListItem {
    id: number;
    name_en: string;
    name_bn: string;
    start_date: string | null;
    status: ExamStatus;
    published_at: string | null;
    subjects_count: number;
}

export interface ExamSubjectRow {
    id: number;
    school_class_id: number;
    cq_full: Decimal | null;
    cq_pass: Decimal | null;
    mcq_full: Decimal | null;
    mcq_pass: Decimal | null;
    practical_full: Decimal | null;
    practical_pass: Decimal | null;
    total_full: Decimal;
    total_pass: Decimal;
    marks_count: number;
    subject: { id: number; name_en: string; name_bn: string; code: string; combined_key: string | null; group: { id: number; name: string } | null };
}

export interface GradeRuleRow {
    id?: number;
    min_percent: Decimal | number;
    max_percent: Decimal | number;
    letter: string;
    point: Decimal | number;
}

export interface GradingSchemeRow {
    id: number;
    name: string;
    is_default: boolean;
    rules: GradeRuleRow[];
}

export interface MarkOption {
    section_id: number;
    exam_subject_id: number;
    section_name: string;
    class_name_en: string;
    class_name_bn: string;
    subject_name_en: string;
    subject_name_bn: string;
}

export interface MarkRow {
    enrollment_id: number;
    roll: number;
    name_en: string;
    name_bn: string | null;
    cq: Decimal | null;
    mcq: Decimal | null;
    practical: Decimal | null;
    total: Decimal | null;
    is_absent: boolean;
}

export interface ResultRow {
    id: number;
    roll: number;
    student_id: number;
    student_uid: string;
    name_en: string;
    name_bn: string | null;
    total_marks: Decimal;
    gpa: Decimal;
    gpa_without_optional: Decimal;
    letter: string;
    fail_count: number;
    merit_position_section: number | null;
    merit_position_class: number | null;
}

export interface MarkAuditRow {
    id: number;
    changed_at: string;
    changed_by: string | null;
    student_name_en: string;
    student_name_bn: string | null;
    section: string;
    roll: number;
    subject_name_en: string;
    subject_name_bn: string;
    old: Record<string, Decimal | boolean | null> | null;
    new: Record<string, Decimal | boolean | null>;
}
